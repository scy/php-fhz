<?php

namespace Scy\Fhz;

class Parser
{
    protected $messageClass;

    const MESSAGE_CLASSES = [
        TemperatureMessage::class,
    ];

    /**
     * Create a new parser.
     *
     * @param string $messageClass The class that should be used for calculateChecksum and as fallback class for raw
     *                             messages. Usually you shouldn't supply this, but it can be useful for testing.
     */
    public function __construct(string $messageClass = Message::class)
    {
        try {
            $reflClass = new \ReflectionClass($messageClass);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(
                "cannot reflect on message class $messageClass: " . $e->getMessage()
            );
        }
        if ($messageClass !== Message::class && !$reflClass->isSubclassOf(Message::class)) {
            throw new \InvalidArgumentException('message class must be a subclass of ' . Message::class);
        }
        $this->messageClass = $messageClass;
    }

    /**
     * Create a new Message object or a subclass thereof based on binary message data.
     *
     * @param string $bytes The raw binary message, including start byte and everything.
     * @return Message
     * @throws \InvalidArgumentException if the data is malformed.
     */
    public function fromBytes(string $bytes): Message
    {
        // Check each of the registered classes for whether they'd accept this message.
        foreach (static::MESSAGE_CLASSES as $class) {
            $method = "$class::accepts";
            if ($method($bytes)) {
                return new $class($bytes);
            }
        }

        // If none of them accepts, use the basic (raw) message class.
        return new $this->messageClass($bytes);
    }

    /**
     * Create a new Message object or a subclass thereof based on a type and payload data.
     *
     * This is basically just a wrapper around fromBytes() that calculates the checksum for you.
     *
     * @param string $type    A single byte designating the message type, see the Message::TYPE_* constants.
     * @param string $payload The payload of the message.
     * @return Message
     * @throws \InvalidArgumentException if the data is malformed.
     */
    public function fromTypeAndPayload(string $type, string $payload): Message
    {
        if (\strlen($type) !== 1) {
            throw new \InvalidArgumentException('type has to be exactly one byte, is ' . \strlen($type));
        }
        return $this->fromBytes(
            "\x81" .
            \chr(\strlen($payload) + 2) .
            $type .
            \call_user_func([$this->messageClass, 'calculateChecksum'], $payload) .
            $payload
        );
    }
}
