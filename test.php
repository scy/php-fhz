<?php

use Scy\Fhz\Connection;
use Scy\Fhz\TemperatureMessage;

require_once __DIR__ . '/vendor/autoload.php';

$logger = new \Monolog\Logger('fhz');
$c = new Connection($argv[1], $logger);
$guzzle = new \GuzzleHttp\Client([
    'base_uri' => 'https://dweet.io/dweet/quietly/for/',
]);

while (true) {
    $in = $c->read(0.5);
    if ($in !== false && TemperatureMessage::understands($in)) {
        $msg = new TemperatureMessage($in);
        $logger->info((string)$msg);
        try {
            $guzzle->post($msg->getId(), ['json' => $msg->toArray()]);
        } catch (\Exception $e) {
            // ignore, just try again next time
        }
    }
}
