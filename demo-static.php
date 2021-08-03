<?php
use Charm\Id;

require('vendor/autoload.php');

if (PHP_SAPI === 'cli') {
    echo "Note that the CLI does not support opcache, which makes the first ID generated a bit slower.\n\n";
}

$t = microtime(true);
echo "First UUID: ";
echo Id::make()."\n";
echo "Time for first UUID version 4 generated: ".intval( (microtime(true) - $t) * 1000000)." microsec\n";

$t = microtime(true);
echo "\nSecond UUID: ";
echo Id::make()."\n";
echo "Time for next UUID: ".intval( (microtime(true) - $t) * 1000000)." microsec\n";
