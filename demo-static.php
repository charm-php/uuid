<?php
/**
 * Static usage to generate UUIDs
 *
 * This file demonstrates the COMB UUID generator. This type of UUID is disguised as an UUID version 4, but
 * instead of using a random number - it will use a unix timestamp with 26 bits fractions, a 14 bit sequence
 * number and 48 bits which are unique for your machine.
 */
use Charm\Id;

require(__DIR__.'/autoload.php');

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
