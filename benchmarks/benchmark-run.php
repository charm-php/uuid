<?php
/**
 * File is designed to measure the time needed to perform a single function
 * call. In many scenarios, the function will be performed once, and the overhead
 * cost of bootstrapping the library is important.
 */
require_once(__DIR__.'/../vendor/autoload.php');

$result = [];

$spec = unserialize($argv[1]);
if (!is_array($spec)) {
    echo serialize([
        '_exception' => new Exception("Could not unserialize the spec")
    ]);
    return;
}

if (isset($spec['eval'])) {
    $result['eval'] = $spec['eval'];
}

ob_start();
$ts = hrtime(true);
try {
    $repetitions = $spec['repetitions'] ?? 1;
    for ($iteration = 0; $iteration < $repetitions; $iteration++) {
        if (isset($spec['eval'])) {
            //fwrite(STDERR, $spec['eval']."\n");
            eval($spec['eval']);
        } else {

        }
    }
} catch (\Throwable $e) {
    ob_end_clean();
    echo serialize([
        '_exception' => $e
    ]);
    return;
}
$output = ob_get_contents();
ob_end_clean();
$result['_output'] = $output;
$result['time'] = (hrtime(true)-$ts) / 1000000000;
$result['includes'] = count(get_included_files());

echo serialize($result);
