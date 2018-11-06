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
    // Try to read a message. This either returns false or a raw string that we can pass to a message class constructor.
    $in = $c->read(0.5);

    if ($in !== false && TemperatureMessage::understands($in)) { // we have a temperature reading
        // Parse the message and log a string representation of it.
        $msg = new TemperatureMessage($in);
        $logger->info((string)$msg);

        // Send the reading as a JSON array to dweet.io.
        try {
            $guzzle->post($msg->getId(), ['json' => $msg->toArray()]);
        } catch (\Exception $e) {
            // ignore, just try again next time
        }
    }
}
