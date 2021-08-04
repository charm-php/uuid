<?php

require(__DIR__.'/../vendor/autoload.php');

// arbitrary namespace UUID
$ts = microtime(true);
$ns = Charm\Id::make();
echo "Initialization of charm/uuid took ".($ctime = (microtime(true) - $ts))." seconds\n";

// allow ramsey/uuid to run once as well, to reduce 
$ts = microtime(true);
$tmp = Ramsey\Uuid\Uuid::uuid4();
echo "Initialization of ramsey/uuid took ".($rtime = (microtime(true) - $ts))." seconds\n";


$ts = microtime(true);
echo "\nUUID v4\n";
echo "=======\n\n";

$ts = microtime(true);
echo "Benchmarking 100k using ramsey/uuid:\n";
for ($i = 0; $i < 100000; $i++) {
    $ruid = (string) Ramsey\Uuid\Uuid::uuid4();
}
echo "Took ".($rtime = (microtime(true) - $ts))." seconds\n\n";

$ts = microtime(true);
echo "Benchmarking 100k using charm/uuid:\n";
for ($i = 0; $i < 100000; $i++) {
    $ruid = (string) Charm\Id::uuid4();
}
echo "Took ".($ctime = (microtime(true) - $ts))." seconds\n\n";

if ($ctime < $rtime) {
    echo "charm/uuid is ".number_format($rtime/$ctime, 2)." times faster\n";
} else {
    echo "ramsey/uuid is ".number_format($rtime/$ctime, 2)." times faster\n";
}

