<?php

use PHPUnit\Framework\TestCase;
use Scy\Fhz\Message;
use Scy\Fhz\Parser;

class ParserTest extends TestCase
{
    public function testMessageCreationFromBytes()
    {
        $msg = (new Parser())->fromBytes("\x81\x06\xc9\x82\x02\x01\x1f\x60");
        /** @noinspection UnnecessaryAssertionInspection */
        $this->assertInstanceOf(Message::class, $msg);
    }

    public function testMessageCreationFromTypeAndPayload()
    {
        $msg = (new Parser())->fromTypeAndPayload("\xc9", "\x02\x01\x1f\x60");
        $this->assertEquals("\x81\x06\xc9\x82\x02\x01\x1f\x60", $msg->getRawBytes());
    }

    public function testTypeTooLong()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Parser())->fromTypeAndPayload('foo', 'abc');
    }

    public function testTypeTooShort()
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Parser())->fromTypeAndPayload('', 'abc');
    }
}
