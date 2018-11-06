<?php

namespace scy\Fhz;

/** Represents a HMS100T or HMS100TF reading. */
class TemperatureMessage extends Message
{
    protected $address;
    protected $temperature;
    protected $humidity;
    protected $status;

    // I've seen 10, 50 and 90 for byte 4.
    const UNDERSTANDS_REGEX = '/^\x81\x0e\x04.\x05..\x01..\x00\x00....$/';

    /**
     * Parse a temperature message.
     *
     * The format looks like this:
     *
     * 81 0e 04 XX 05 ?T ?? 01 II II 00 00 SS TT ht HH
     *
     * XX:    checksum
     * ??:    meaning unknown
     * II II: sensor ID (changes when you replace the battery!)
     * SS:    status bitmask: 0x80: negative temperature; 0x40: new battery; 0x20: battery low
     * tTT:   temperature in 1/10 degree Celsius as 3-digit BCD
     * hHH:   humidity in percent as 3-digit BCD? (to be confirmed)
     *
     * @param string $data A raw binary message.
     */
    public function __construct(string $data)
    {
        parent::__construct($data);

        // The sensor's address, 2 bytes, usually displayed as four hex digits, so that's what we do here.
        $this->address = \bin2hex(\substr($data, 8, 2));
        // A status byte that contains whether the battery is low or has just been replaced.
        $this->status = \ord($data[12]);

        // Temperature and humidity values specified as their hex value taken as a string, i.e. 0x123 means 123.
        // Hex digits between a and f are invalid. The temperature drops directly from 0x100 to 0x099.
        $values = \bin2hex(\substr($data, 13, 3));
        $this->temperature = (float)($values[3] . $values[0] . $values[1]) / 10 * (($this->status & 0x80) ? -1 : 1);
        $this->humidity = (\ord($data[5]) & 0x01) ? null : (float)($values[4] . $values[5] . $values[2]) / 10;
    }

    /** @inheritdoc */
    protected static function validate(string $data)
    {
        // We accept types 0 (HMS100 TF) and 1 (HMS100 T).
        if (!\preg_match(static::UNDERSTANDS_REGEX, $data)) {
            throw new \InvalidArgumentException('invalid message format');
        }
        if ((\ord($data[5]) & 0x0e) !== 0) {
            throw new \InvalidArgumentException(
                'only device types 0 and 1 supported, type is ' . \dechex(\ord($data[5]) & 0x0f)
            );
        }
        $values = \bin2hex(\substr($data, 13, 3));
        if (!\ctype_digit($values[3] . $values[0] . $values[1])) {
            throw new \InvalidArgumentException('temperature hex value is not in base 10');
        }
        if (!\ctype_digit($values[4] . $values[5] . $values[2])) {
            throw new \InvalidArgumentException('humidity hex value is not in base 10');
        }
    }

    /**
     * @return float Humidity in percent. Note that HMS100T sensors will return null here (they don't measure humidity)
     *               and that HMS100TF sensors are notoriously bad.
     */
    public function getHumidity(): float
    {
        return $this->humidity;
    }

    /**
     * @return string The 4-digit hex ID of the sensor.
     */
    public function getId(): string
    {
        return $this->address;
    }

    /**
     * @return string Either "HMS100T" or "HMS100TF" (depending on the sensor type), followed by an underscore and the
     *                4-digit hex ID of the sensor.
     */
    public function getName(): string
    {
        return ($this->humidity === null ? 'HMS100T' : 'HMS100TF') . '_' . $this->address;
    }

    /**
     * @return float Temperature in degrees Celsius.
     */
    public function getTemperature(): float
    {
        return $this->temperature;
    }

    /**
     * @return bool Whether this sensor reports humidity, i.e. whether it's an HMS100TF.
     */
    public function hasHumidity(): bool
    {
        return $this->humidity !== null;
    }

    /**
     * @return bool Whether this message announces that the sensor has a new battery (and thus its ID changed, I think?)
     */
    public function hasNewBattery(): bool
    {
        return (bool)($this->status & 0x40);
    }

    /**
     * @return bool Whether this sensor's battery is low and should be replaced.
     */
    public function isBatteryLow(): bool
    {
        return (bool)($this->status & 0x20);
    }

    /**
     * @return array This message's data as an array, including `device_id`, `received`, `temperature`, `battery_low`
     *                and possibly `humidity`.
     */
    public function toArray(): array
    {
        return \array_merge(parent::toArray(), [
            'device_id' => $this->getId(),
            'device_name' => $this->getName(),
            'temperature' => $this->getTemperature(),
            'battery_low' => $this->isBatteryLow(),
        ], $this->hasHumidity() ? ['humidity' => $this->getHumidity()] : []);
    }

    /**
     * @return string This message's data as a string for human consumption.
     */
    public function getSummary(): string
    {
        return \sprintf('[%s] %s: %.1f °C%s, battery %s',
            $this->received->format('c'),
            $this->getName(),
            $this->temperature,
            $this->hasHumidity() ? \sprintf(', %.1f %%', $this->getHumidity()) : '',
            $this->isBatteryLow() ? 'low' : 'ok'
        );
    }
}
