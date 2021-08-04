<?php

use Charm\Util\IdFactory;

// How many processes to make?
$forks = 10;

// Which second should we start?
$second = floor(microtime(true) + 1.1);

require(__DIR__.'/../vendor/autoload.php');

$fp = fopen(__DIR__.'/uuids.txt', 'a');
$service = new IdFactory(IdFactory::TYPE_UUID_V1);

$pids = [];
for($i = 0; $i < $forks; $i++) {
    $pid = pcntl_fork();
    if ($pid == -1) {
        die("Unable to fork\n");
    } elseif ($pid) {
        $pids[] = $pid;
    } else {
        echo "Starting worker $i\n";
        $myPid = getmypid();
        while (microtime(true) < $second) {
            usleep(0);
        }
        echo "Loop starting for worker $i\n";
        $count = 0;
        while (true) {
            fwrite($fp, $service()."\n");
            if ($count++ % 10 === 0) {
                if ($second != floor(microtime(true))) {
                    echo "Worker $i finished\n";
                    die();
                }
            }
        }
        die();
    }
}
foreach ($pids as $pid) {
    pcntl_waitpid($pid, $status);
}
