<?php

// Call this script like this: php example.php /dev/ttyUSB0

use Scy\Fhz\Connection;
use Scy\Fhz\TemperatureMessage;

require_once __DIR__ . '/vendor/autoload.php';

// Some console debugging output.
$logger = new \Monolog\Logger('fhz');

// Connect to the FHZ via the device given on the command line.
$c = new Connection($argv[1], $logger);

// Our example uses dweet.io to publish the readings.
$guzzle = new \GuzzleHttp\Client([
    'base_uri' => 'https://dweet.io/dweet/quietly/for/',
]);

while (true) {
    // Try to read a message. This either returns false or a message object.
    $msg = $c->read(0.5);

    if ($msg instanceof TemperatureMessage) { // we have a temperature reading
        $logger->info($msg->getSummary());

        // Send the reading as a JSON array to dweet.io.
        try {
            $guzzle->post($msg->getName(), ['json' => $msg->toArray()]);
        } catch (\Exception $e) {
            // ignore, just try again next time
        }
    }
}
