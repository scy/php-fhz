<?php

namespace Scy\Fhz;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Connection
{
    protected $device;
    protected $parser;
    protected $logger;

    // Message types. I've named them myself, did not found any documentation about how they are really called.
    const TYPE_META = "\xc9";
    const TYPE_STATUS = "\x04";

    /** @var string This is what FHEM uses to initialize the FHZ. */
    const MSG_INIT2 = "\x02\x01\x1f\x64";
    /** @var string This is what FHEM uses to receive the FHZ serial number. */
    const MSG_SERIAL = "\xc9\x01\x84\x57\x02\x08";
    /** @var string This is what FHEM uses to enable HMS reception. */
    const MSG_ENABLE_HMS = "\xc9\x01\x86";
    /** @var string This is a FS20 command to a dummy actor. FHEM uses it to make sure that the FHZ reports incoming messages. */
    const MSG_RECV_WORKAROUND = "\x01\x01\x01\x00\x01\x00\x00";

    /**
     * Open a connection to a FHZ device.
     *
     * @param string               $device The path to the USB serial device.
     * @param LoggerInterface|null $logger If you want to provide a PSR-3 logger, you can do it here.
     * @param Parser|null          $parser The parser class you wish to use. Leave at null for the default parser.
     */
    public function __construct(string $device, LoggerInterface $logger = null, Parser $parser = null)
    {
        $this->logger = $logger ?: new NullLogger();
        $this->parser = $parser ?: new Parser();
        $this->initSerialPort($device);
        // TODO: error handling for the fopen call
        $this->device = \fopen($device, 'r+b');
        $this->initFHZ();
        \stream_set_blocking($this->device, false);
    }

    /**
     * Receive the next incoming message.
     *
     * @param float $timeout How long to wait for a message, in seconds. This is how long this method will block (max).
     * @return bool|Message false if no message was received, else the parsed message.
     */
    public function read(float $timeout)
    {
        // Prepare stream_select().
        $read = [$this->device];
        $ignore = [];
        $t_sec = (int)$timeout;
        $t_usec = ($timeout - $t_sec) * 1000000;

        // TODO: error handling
        \stream_select($read, $ignore, $ignore, $t_sec, $t_usec);
        if (\count($read)) { // there's something waiting
            return $this->receive();
        }

        return false;
    }

    /**
     * Call `stty` to initialize the serial port with the parameters used by the FHZ.
     *
     * The settings are based on what FHEM does.
     *
     * @param string $device The device path.
     */
    protected function initSerialPort(string $device)
    {
        // TODO: error handling
        \exec(\sprintf('stty -F %s raw cs8 9600 -parenb -cstopb -crtscts -icanon -parmrk -icrnl -echoe -echok -echoctl -echo -isig -opost', \escapeshellarg($device)), $output, $return);
    }

    /**
     * Send some commands to set up the FHZ and hopefully put it in a usable state.
     *
     * Again, this is somewhat mimicking FHEM's behavior.
     */
    protected function initFHZ()
    {
        $this->query($this->parser->fromTypeAndPayload(static::TYPE_META, static::MSG_INIT2));
        $this->query($this->parser->fromTypeAndPayload(static::TYPE_STATUS, static::MSG_SERIAL));
        $this->setDateTime();
        $this->send($this->parser->fromTypeAndPayload(static::TYPE_STATUS, static::MSG_ENABLE_HMS));
        $this->send($this->parser->fromTypeAndPayload(static::TYPE_STATUS, static::MSG_RECV_WORKAROUND));
    }

    /**
     * Send a command, wait for the response and return it.
     *
     * Internally, this just calls send() and receive() after each other.
     *
     * @param Message $msg The message to send.
     * @return Message The response to the command.
     * @throws \InvalidArgumentException if the data received is malformed.
     */
    protected function query(Message $msg): Message
    {
        $this->send($msg);
        return $this->receive();
    }

    /**
     * Read exactly n bytes from the FHZ.
     *
     * This is required since fread() will read _up to_ $count bytes. But if the FHZ is currently sending the response
     * via USB _while_ we read, fread() won't return all of it. Since FHZ messages have a predetermined length, this is
     * an easy solution.
     *
     * @param int $count The number of bytes to read.
     * @return string The bytes that were read.
     */
    protected function readBytes(int $count): string
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

    /**
     * Read exactly one message from the FHZ.
     *
     * This function will block until a complete message has been read, so make sure you only call it when you're sure
     * that there's a message waiting or when you're ready to wait for it.
     *
     * @return Message The message that was received.
     * @throws \InvalidArgumentException if the data received is malformed.
     */
    protected function receive(): Message
    {
        $data = $this->readBytes(2);
        $data .= $this->readBytes(\ord($data[1]));
        $this->logger->debug('-> ' . \implode(' ', \str_split(\bin2hex($data), 2)));
        return $this->parser->fromBytes($data);
    }

    /**
     * Send a message to the FHZ.
     *
     * @param Message $msg The message that should be sent.
     */
    protected function send(Message $msg)
    {
        $toSend = $msg->getRawBytes();
        $this->logger->debug('<- ' . \implode(' ', \str_split(\bin2hex($toSend), 2)));
        // TODO: error handling
        \fwrite($this->device, $toSend);
    }

    /**
     * Tell the FHZ what the current date and time is.
     *
     * I don't think this is required for HMS operation, we do it nevertheless.
     *
     * The message payload starts with 0x02 0x01 0x61 and then year, month, day, hours, minutes, each as one byte.
     *
     * @todo Not sure whether these should be provided in BCD form, we use uint8 right now.
     */
    protected function setDateTime()
    {
        try {
            $now = new \DateTimeImmutable();
        } catch (\Exception $e) {
            // Should not happen, but if it does, we simply don't do anything.
            return;
        }
        $datestr = $now->format('ymdHi');
        $data = "\x02\x01\x61";
        for ($i = 0; $i < 5; $i++) {
            $data .= \chr(\substr($datestr, 2 * $i, 2));
        }
        $this->send($this->parser->fromTypeAndPayload(static::TYPE_STATUS, $data));
    }
}
