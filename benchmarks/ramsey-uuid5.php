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
echo "\nUUID v5\n";
echo "=======\n\n";

echo "Comparing 100 000 UUID v5 for compatability with ramsey/uuid: \n";
for ($i = 0; $i < 100000; $i++) {
    $name = random_bytes(mt_rand(1, 30));
    $ruid = (string) Ramsey\Uuid\Uuid::uuid5($ns, $name);
    $cuid = (string) Charm\Id::uuid5($ns, $name);

    if ($ruid !== $cuid) {
        echo "MISMATCHING UUIDs:\nCharm:  $cuid\nRamsey: $ruid\n";
        exit(1);
    }
}
echo " - all UUIDs identical.\n";
echo "Took ".(microtime(true) - $ts)." seconds\n\n\n";

$ts = microtime(true);
echo "Benchmarking 100k using ramsey/uuid:\n";
for ($i = 0; $i < 100000; $i++) {
    $name = (string) mt_rand(0,999999999);
    $ruid = (string) Ramsey\Uuid\Uuid::uuid5($ns, $name);
}
echo "Took ".($rtime = (microtime(true) - $ts))." seconds\n\n";

$ts = microtime(true);
echo "Benchmarking 100k using charm/uuid:\n";
for ($i = 0; $i < 100000; $i++) {
    $name = (string) mt_rand(0,999999999);
    $ruid = (string) Charm\Id::uuid5($ns, $name);
}
echo "Took ".($ctime = (microtime(true) - $ts))." seconds\n\n";

if ($ctime < $rtime) {
    echo "charm/uuid is ".number_format($rtime/$ctime, 2)." times faster\n";
} else {
    echo "ramsey/uuid is ".number_format($rtime/$ctime, 2)." times faster\n";
}
