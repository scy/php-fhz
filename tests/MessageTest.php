<?php

use PHPUnit\Framework\TestCase;
use Scy\Fhz\Message;

class MessageTest extends TestCase
{
    public function testMinimumMessageLength()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/4 bytes/');
        new Message("\x81\x06\xc9");
    }

    public function testStartByte()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/first data byte has to be 0x81/');
        new Message("\x83\x06\xc9\x82\x02\x01\x1f\x60");
    }

    public function testLengthHeader()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/according to header, payload should be/');
        new Message("\x81\x08\xc9\x82\x02\x01\x1f\x60");
    }

    public function testChecksum()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/checksum is.*should be/');
        new Message("\x81\x06\xc9\x83\x02\x01\x1f\x60");
    }
}
