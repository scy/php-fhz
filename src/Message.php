<?php

namespace Scy\Fhz;

class Message
{
    protected $raw;
    protected $received;

    /**
     * Create a new (raw) message based on this data.
     *
     * A FHZ message looks like this:
     *
     * 0x81 (start byte)
     * length (uint8, including the start byte and this one)
     * message type (uint8)
     * message checksum (uint8, see calculateChecksum)
     * actual payload (byte[])
     *
     * @param string $data The binary data of the message.
     * @throws \InvalidArgumentException if the data is malformed.
     */
    public function __construct(string $data)
    {
        static::validate($data);
        $this->raw = $data;
        try {
            $this->received = new \DateTimeImmutable();
        } catch (\Exception $e) {
            // Should never happen. Ignore this.
        }
    }

    /**
     * Check whether this class would accept the given data when you'd pass it to the constructor.
     *
     * @param string $data The binary message data.
     * @return bool
     */
    public static function accepts(string $data)
    {
        try {
            static::validate($data);
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Calculate the checksum for a FHZ message.
     *
     * The checksum is a simple uint8 sum of all the in the message. We simply add all the bytes and only return the
     * least significant byte of the sum, which corresponds to doing `sum % 256`.
     *
     * @param string $data The payload to send, excluding start (0x81), length and type bytes.
     * @return string The single checksum byte.
     */
    public static function calculateChecksum(string $data): string
    {
        $sum = 0;
        for ($i = \strlen($data) - 1; $i >= 0; $i--) {
            $sum += \ord($data[$i]);
        }

        // chr() will do the equivalent of '% 256' itself.
        return \chr($sum);
    }

    /**
     * Check the data for errors.
     *
     * Subclasses can override this method to decide whether they want to accept a certain message (format) or not.
     *
     * @param string $data Binary message data.
     */
    protected static function validate(string $data)
    {
        $length = \strlen($data);
        if ($length < 4) {
            throw new \InvalidArgumentException("data has to be at least 4 bytes long, is $length");
        }
        if ($data[0] !== "\x81") {
            throw new \InvalidArgumentException('first data byte has to be 0x81, is 0x' . \bin2hex($data[0]));
        }
        if (\ord($data[1]) !== $length - 2) {
            throw new \InvalidArgumentException(
                'according to header, payload should be ' . \ord($data[1]) . ' bytes long, is ' . ($length - 2)
            );
        }
        if ($data[3] !== ($checksum = static::calculateChecksum(\substr($data, 4)))) {
            throw new \InvalidArgumentException(
                'checksum is 0x' . \bin2hex($data[3]) . ', should be 0x' . \bin2hex($checksum)
            );
        }
    }

    /**
     * @return string The one-byte checksum of this message in binary form.
     */
    public function getChecksum(): string
    {
        return $this->raw[3];
    }

    /**
     * @return string The raw message data as space-separated hexadecimal bytes.
     */
    public function getHexDump(): string
    {
        return \implode(' ', \str_split(\bin2hex($this->getRawBytes()), 2));
    }

    /**
     * @return string The raw binary payload of this message, excluding start, length, type and checksum bytes.
     */
    public function getPayload(): string
    {
        return \substr($this->raw, 4);
    }

    /**
     * @return string The raw binary data of this message, from start byte to end of payload.
     */
    public function getRawBytes(): string
    {
        return $this->raw;
    }

    /**
     * @return \DateTimeImmutable When this message was received (or rather, when the object was created).
     */
    public function getReceivedTime(): \DateTimeImmutable
    {
        return $this->received;
    }

    /**
     * @return string A human-readable string representation of the message.
     */
    public function getSummary(): string
    {
        return 'raw message, contents ' . $this->getHexDump();
    }

    /**
     * @return string The one-byte type designator of this message.
     */
    public function getType(): string
    {
        return $this->raw[2];
    }

    /**
     * @return array A key/value representation of this message, suitable for JSON conversion.
     */
    public function toArray(): array
    {
        return [
            'received' => $this->getReceivedTime()->format('c'),
            'dump' => $this->getHexDump(),
        ];
    }

    /**
     * @return string The raw byte format of this message, from start byte to end of payload.
     */
    public function __toString()
    {
        return $this->raw;
    }
}
