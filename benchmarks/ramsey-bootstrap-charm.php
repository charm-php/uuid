<?php
/**
 * File is designed to measure the time needed to perform a single function
 * call. In many scenarios, the function will be performed once, and the overhead
 * cost of bootstrapping the library is important.
 */
require_once(__DIR__.'/../vendor/autoload.php');

$func = $argv[1];

$ts = hrtime(true);
Charm\Id::$func();
echo hrtime(true)-$ts;

