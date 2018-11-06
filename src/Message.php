<?php

namespace Scy\Fhz;

class Message
{
    protected $raw;
    protected $received;

    public function __construct(string $data)
    {
        static::validate($data);
        $this->raw = $data;
        $this->received = new \DateTimeImmutable();
    }

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
     * @return string The one-byte checksum of this message.
     */
    public function getChecksum(): string
    {
        return $this->raw[3];
    }

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
     * @return string The raw byte format of this message, from start byte to end of payload.
     */
    public function getRawBytes(): string
    {
        return $this->raw;
    }

    public function getReceivedTime(): \DateTimeImmutable
    {
        return $this->received;
    }

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
