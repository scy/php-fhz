<?php

namespace Scy\Fhz;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Connection
{
    protected $device;
    protected $logger;

    const TYPE_META = "\xc9";
    const TYPE_STATUS = "\x04";

    const MSG_INIT2 = "\x02\x01\x1f\x64";
    const MSG_SERIAL = "\xc9\x01\x84\x57\x02\x08";
    const MSG_ENABLE_HMS = "\xc9\x01\x86";
    const MSG_RECV_WORKAROUND = "\x01\x01\x01\x00\x01\x00\x00";

    public function __construct(string $device, LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: new NullLogger();
        $this->initSerialPort($device);
        // TODO: error handling
        $this->device = \fopen($device, 'r+b');
        $this->initFHZ();
        \stream_set_blocking($this->device, false);
    }

    public function read(float $timeout)
    {
        $read = [$this->device];
        $ignore = [];
        $t_sec = (int)$timeout;
        $t_usec = ($timeout - $t_sec) * 1000000;
        // TODO: error handling
        $changed = \stream_select($read, $ignore, $ignore, $t_sec, $t_usec);
        if (\count($read)) {
            return $this->receive();
        }
        return false;
    }

    protected function calculateChecksum(string $data)
    {
        $sum = 0;
        for ($i = \strlen($data) - 1; $i >= 0; $i--) {
            $sum += \ord($data[$i]);
        }

        // chr() will do the equivalent of '% 256' itself.
        return \chr($sum);
    }

    protected function initSerialPort(string $device)
    {
        // TODO: error handling
        \exec(\sprintf('stty -F %s raw cs8 9600 -parenb -cstopb', \escapeshellarg($device)), $output, $return);
    }

    protected function initFHZ()
    {
        $this->query(static::TYPE_META, static::MSG_INIT2);
        $this->query(static::TYPE_STATUS, static::MSG_SERIAL);
        $this->setDateTime();
        $this->send(static::TYPE_STATUS, static::MSG_ENABLE_HMS);
        $this->send(static::TYPE_STATUS, static::MSG_RECV_WORKAROUND);
    }

    protected function query(string $type, string $data)
    {
        $this->send($type, $data);
        return $this->receive();
    }

    protected function readBytes(int $count)
    {
        $str = '';
        while ($count > 0) {
            $chunk = \fread($this->device, $count);
            $str .= $chunk;
            // TODO: error handling
            $count -= \strlen($chunk);
        }
        return $str;
    }

    protected function receive()
    {
        $header = $this->readBytes(2);
        $body = $this->readBytes(\ord($header[1]));
        $this->logger->debug('-> ' . \implode(' ', \str_split(\bin2hex($header . $body), 2)));
        return $body;
    }

    protected function send(string $type, string $data)
    {
        if ($type === '') {
            throw new \InvalidArgumentException('type may not be an empty string');
        }
        $type = $type[0]; // only one character allowed
        $toSend = "\x81" . \chr(\strlen($data) + 2) . $type . $this->calculateChecksum($data) . $data;
        $this->logger->debug('<- ' . \implode(' ', \str_split(\bin2hex($toSend), 2)));
        // TODO: error handling
        \fwrite($this->device, $toSend);
    }

    protected function setDateTime()
    {
        $now = new \DateTimeImmutable();
        $datestr = $now->format('ymdHi');
        $data = "\x02\x01\x61";
        for ($i = 0; $i < 5; $i++) {
            $data .= \chr(\substr($datestr, 2 * $i, 2));
        }
        $this->send(static::TYPE_STATUS, $data);
    }
}
