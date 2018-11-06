<?php

namespace scy\Fhz;

class TemperatureMessage
{
    protected $address;
    protected $temperature;
    protected $humidity;
    protected $status;
    protected $created;

    // I've seen 10, 50 and 90 for byte 4.
    const UNDERSTANDS_REGEX = '/^\x04.\x05..\x01..\x00\x00....$/';

    public function __construct(string $data)
    {
        $this->address = \bin2hex(\substr($data, 6, 2));
        $this->status = \ord($data[10]);

        // Temperature and humidity values specified as their hex value taken as a string, i.e. 0x123 means 123.
        // Hex digits between a and f are invalid. The temperature drops directly from 0x100 to 0x099.
        $values = \bin2hex(\substr($data, 11, 3));
        if (!\ctype_digit($temp_hex = $values[3] . $values[0] . $values[1])) {
            throw new \InvalidArgumentException('temperature hex value is not in base 10');
        }
        $this->temperature = (float)$temp_hex / 10 * (($this->status & 0x80) ? -1 : 1);
        if (!\ctype_digit($hum_hex = $values[4] . $values[5] . $values[2])) {
            throw new \InvalidArgumentException('humidity hex value is not in base 10');
        }
        $this->humidity = (\ord($data[3]) & 0x01) ? null : (float)$hum_hex / 10;

        $this->created = new \DateTimeImmutable();
    }

    public static function understands(string $data)
    {
        // We accept types 0 (HMS100 TF) and 1 (HMS100 T).
        return \preg_match(static::UNDERSTANDS_REGEX, $data) && ((\ord($data[3]) & 0x0e) === 0);
    }

    public function getId()
    {
        return ($this->humidity === null ? 'HMS100T' : 'HMS100TF') . '_' . $this->address;
    }

    public function getHumidity()
    {
        return $this->humidity;
    }

    public function getTemperature()
    {
        return $this->temperature;
    }

    public function hasHumidity()
    {
        return $this->humidity !== null;
    }

    public function hasNewBattery()
    {
        return (bool)($this->status & 0x40);
    }

    public function isBatteryLow()
    {
        return (bool)($this->status & 0x20);
    }

    public function toArray()
    {
        $array = [
            'id' => $this->getId(),
            'time' => $this->created->format('c'),
            'temperature' => $this->getTemperature(),
            'battery_low' => $this->isBatteryLow(),
        ];
        if ($this->hasHumidity()) {
            $array['humidity'] = $this->getHumidity();
        }
        return $array;
    }

    public function __toString()
    {
        return \sprintf('[%s] %s: %.1f °C%s, battery %s',
            $this->created->format('c'),
            $this->getId(),
            $this->temperature,
            $this->hasHumidity() ? \sprintf(', %.1f %%', $this->getHumidity()) : '',
            $this->isBatteryLow() ? 'low' : 'ok'
        );
    }
}
