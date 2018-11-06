<?php

namespace Scy\Fhz;

class Parser
{
    protected $messageClass;

    const MESSAGE_CLASSES = [
        TemperatureMessage::class,
    ];

    public function __construct(string $messageClass = Message::class)
    {
        $reflClass = new \ReflectionClass($messageClass);
        if ($messageClass !== Message::class && !$reflClass->isSubclassOf(Message::class)) {
            throw new \InvalidArgumentException('message class must be a subclass of ' . Message::class);
        }
        $this->messageClass = $messageClass;
    }

    public function fromBytes(string $bytes): Message
    {
        foreach (static::MESSAGE_CLASSES as $class) {
            $method = "$class::accepts";
            if ($method($bytes)) {
                return new $class($bytes);
            }
        }
        return new $this->messageClass($bytes);
    }

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
