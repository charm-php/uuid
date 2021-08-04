<?php
/**
 * File is designed to measure the time needed to perform a single function
 * call. In many scenarios, the function will be performed once, and the overhead
 * cost of bootstrapping the library is important.
 */
require_once(__DIR__.'/../vendor/autoload.php');

$included_files = count(get_included_files());
$memory_rest = memory_get_usage();
$memory_peak = memory_get_peak_usage();
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

$result['iteration_count'] = 0;

ob_start();
$ts = hrtime(true);
try {
    if (isset($spec['duration'])) {
        $stopTime = microtime(true) + $spec['duration'];
        do {
            $result['iteration_count']++;
            eval($spec['eval']);
        } while (microtime(true) < $stopTime);
    } elseif (isset($spec['iterations'])) {
        $iterations = $spec['iterations'] ?? 1;
        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            $result['iteration_count']++;
            eval($spec['eval']);
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
$result['includes'] = count(get_included_files()) - $included_files;
$result['memory_rest'] = memory_get_usage() - $memory_rest;
$result['memory_peak'] = memory_get_peak_usage() - $memory_peak;
gc_collect_cycles();
$result['memory_gc'] = memory_get_usage() - $memory_rest;


echo serialize($result);
