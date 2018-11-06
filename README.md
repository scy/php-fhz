# scy/fhz

This is a PHP package to access the FHZ 1000 family of SRD860 transceivers and parse messages from sensors.

At least that's what it currently does.
The features are limited to what the author currently needs:

* connect to a FHZ 1000 PC from Linux (tested on WSL as well)
* receive temperature and humidity readings from several HMS100T and HMS100TF sensors and make them available as PHP objects

## Installation

`composer require scy/fhz` should be sufficient.
In case you want to try [the example script](example.php), make sure you install the development dependencies.
Recent versions of Composer do that automatically, older ones need the `--dev` switch.

## Usage

Create a `new Connection`, passing the path to the serial device your FHZ is connected to as a parameter.
You can optionally supply a PSR-3 logger as the second parameter.

Calling `read($seconds)` on the resulting object will listen for incoming transmissions and return the first incoming message.
If no messages arrived after `$seconds` (a float), the method will return `false`.
Messages that arrive while your `read()` is not running will be buffered by your operating system's IO buffer and returned one by one when you call `read()` the next time.
(That buffer is limited, so don't get too crazy.)

The message is returned as a a `Message` object or a subclass of it.

Currently, only one subclass is implemented: `TemperatureMessage`.
It represents a single reading of a HMS100T (temperature) or HMS100TF (temperature/humidity) sensor.
Use methods like `getTemperature()` to access the data.

You can also [have a look at some example code](example.php), which probably explains it better.

## Status

I'm currently integrating this library into a management and logging interface for my van.
The API isn't stable, but shouldn't change too much either.

Documentation, error handling and code structure could use some improvements.

## Meta

This library is free software released under the terms of the [MIT license](LICENSE.txt).
It is written and maintained by Tim Weber and its source code is hosted at <https://github.com/scy/php-fhz>.
