<?php
use Charm\Util\IdFactory;

require('vendor/autoload.php');

if (PHP_SAPI === 'cli') {
    echo "Note that the CLI does not support opcache, which makes the first ID generated a bit slower.\n\n";
}

$uuidService = new IdFactory(IdFactory::TYPE_UUID_V1);

$t = microtime(true);
echo "First UUID: ";
echo $uuidService()." <br>\n";
echo "Time for first UUID version 4 generated: ".intval( (microtime(true) - $t) * 1000000)." microsec <br>\n";

$t = microtime(true);
echo "<br>\nSecond UUID: ";
echo $uuidService()." <br>\n";
echo "Time for next UUID: ".intval( (microtime(true) - $t) * 1000000)." microsec <br>\n";
