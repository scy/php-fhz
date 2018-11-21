<?php

use PHPUnit\Framework\TestCase;
use Scy\Fhz\Parser;
use scy\Fhz\TemperatureMessage;

class TemperatureMessageTest extends TestCase
{
    public function testAccepts(): TemperatureMessage
    {
        $bytes = "\x81\x0e\x04\xc5\x05\x10\xa0\x01\xb6\x40\x00\x00\x00\x18\x01\x00";
        /** @var TemperatureMessage $msg */
        $msg = (new Parser())->fromBytes($bytes);
        $this->assertInstanceOf(TemperatureMessage::class, $msg);
        return $msg;
    }

    /**
     * @depends testAccepts
     * @param TemperatureMessage $msg
     */
    public function testSummary(TemperatureMessage $msg)
    {
        $this->assertRegExp('/^\[.*\] HMS100TF_b640: 11.8 °C, 0.0 %, battery ok$/', $msg->getSummary());
    }

    /**
     * @depends testAccepts
     * @param TemperatureMessage $msg
     */
    public function testToArray(TemperatureMessage $msg)
    {
        $array = $msg->toArray();
        $this->assertArraySubset([
            'device_id' => 'b640',
            'device_name' => 'HMS100TF_b640',
            'temperature' => 11.8,
            'humidity' => 0,
            'battery_low' => false,
        ], $array);
    }

    /**
     * Make sure that the ID is a string even when its hex digits are [0-9] only.
     */
    public function testIdIsString()
    {
        $bytes = "\x81\x0e\x04\xc5\x05\x10\xa0\x01\x36\x40\x00\x00\x00\x18\x01\x00";
        /** @var TemperatureMessage $msg */
        $msg = (new Parser())->fromBytes($bytes);
        $this->assertSame('3640', $msg->getId());
    }
}
