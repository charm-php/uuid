<?php
$benchmarks = [
    'Single UUID v1 generation time' => [
        'charm/uuid' => [
            'eval' => 'Charm\Id::uuid1();',
            'bootstraps' => 500,
            'repetitions' => 1,
        ],
        'ramsey/uuid' => [
            'eval' => 'Ramsey\Uuid\Uuid::uuid1();',
            'bootstraps' => 500,
            'repetitions' => 1,
        ],
        'pecl:uuid' => [
            'eval' => 'uuid_create(UUID_TYPE_TIME);',
            'bootstraps' => 500,
            'repetitions' => 1,
        ],
    ],
    'Generating 100000 UUID v1' => [
        'charm/uuid' => [
            'eval' => 'Charm\Id::uuid1();',
            'bootstraps' => 20,
            'repetitions' => 100000,
        ],
        'ramsey/uuid' => [
            'eval' => 'Ramsey\Uuid\Uuid::uuid1();',
            'bootstraps' => 20,
            'repetitions' => 100000,
        ],
        'pecl:uuid' => [
            'eval' => 'uuid_create(UUID_TYPE_TIME);',
            'bootstraps' => 20,
            'repetitions' => 100000,
        ],
    ],
    'Single UUID v4 generation time' => [
        'charm/uuid' => [
            'eval' => 'Charm\Id::uuid4();',
            'bootstraps' => 500,
            'repetitions' => 1,
        ],
        'ramsey/uuid' => [
            'eval' => 'Ramsey\Uuid\Uuid::uuid4();',
            'bootstraps' => 500,
            'repetitions' => 1,
        ],
        'pecl:uuid' => [
            'eval' => 'uuid_create(UUID_TYPE_RANDOM);',
            'bootstraps' => 500,
            'repetitions' => 1,
        ],
    ],
    'Generating 100000 UUID v4' => [
        'charm/uuid' => [
            'eval' => 'Charm\Id::uuid4();',
            'bootstraps' => 20,
            'repetitions' => 100000,
        ],
        'ramsey/uuid' => [
            'eval' => 'Ramsey\Uuid\Uuid::uuid4();',
            'bootstraps' => 20,
            'repetitions' => 100000,
        ],
        'pecl:uuid' => [
            'eval' => 'uuid_create(UUID_TYPE_RANDOM);',
            'bootstraps' => 20,
            'repetitions' => 100000,
        ],
    ],
];

$md = new MD();

$md->h1("Benchmark Results");

$testNumber = 1;
foreach ($benchmarks as $title => $libs) {
    $md->h2("#$testNumber: $title");
    $testNumber++;

    $again = false;
    do {
        $again = false;
        foreach ($libs as $lib => &$spec) {
            if (!isset($spec['_bootstraps'])) {
                $spec['_bootstraps'] = 0;
            }
            if ($spec['bootstraps'] > $spec['_bootstraps']) {
                $spec['_bootstraps']++;
                status(" * ".$spec['_bootstraps']." / ".$spec['bootstraps'].": $lib");
                $again = true;
                $result = launch($spec);
                $spec['_results'][] = $result;
            }
        }
        unset($spec);
    } while($again);

    foreach ($libs as $lib => $spec) {
        $result = [];
        $count = 0;
        foreach ($spec['_results'] as $temp) {
            $count++;
            foreach ($temp as $dim => $value) {
                if (is_int($value) || is_float($value)) {
                    if (!isset($result[$dim]["sum"])) {
                        $result[$dim]["sum"] = 0;
                    }
                    if (!isset($result[$dim]["average"])) {
                        $result[$dim]["average"] = 0;
                    }
                    if (!isset($result[$dim]["median"])) {
                        $result[$dim]["median"] = [];
                    }
                    if (!isset($result[$dim]["min"])) {
                        $result[$dim]["min"] = $value;
                    }
                    if (!isset($result[$dim]["max"])) {
                        $result[$dim]["max"] = $value;
                    }
                    if (!isset($result[$dim]["count"])) {
                        $result[$dim]["count"] = 0;
                    }
                    $result[$dim]["sum"] += $value;
                    $result[$dim]["count"]++;
                    $result[$dim]["median"][] = $value;
                    if ($value > $result[$dim]["max"]) {
                        $result[$dim]["max"] = $value;
                    }
                    if ($value < $result[$dim]["min"]) {
                        $result[$dim]["min"] = $value;
                    }
                    $result[$dim]["average"] = $result[$dim]["sum"] / $result[$dim]["count"];
                }
            }
        }
        foreach ($result as $k => $measurements) {
            foreach ($measurements as $kk => $v) {
                if (is_array($v) && count($v) > 0) {
                    sort($v);
                    $middle = floor(count($v) / 2);
                    $result[$k][$kk] = $v[$middle];
                }
            }
        }
        $libs[$lib]['_result'] = $result;
    }

    $rows = [
        ['Library', 'Runs', 'Average Time', 'Median Time', 'Fastest', 'Slowest', 'Included files' ],
    ];
    foreach ($libs as $lib => $spec) {
        $rows[] = [
            $lib,
            $spec['_result']['includes']['count'],
            number_format(1000*$spec['_result']['time']['average'], 2).' ms',
            number_format(1000*$spec['_result']['time']['median'], 2).' ms',
            number_format(1000*$spec['_result']['time']['min'], 2).' ms',
            number_format(1000*$spec['_result']['time']['max'], 2).' ms',
            $spec['_result']['includes']['average'],
        ];
    }

    $md->table($rows);

}
die();
class MD {
    private $fd;

    public function __construct($fd=STDOUT) {
        $this->fd = $fd;
    }

    public function write(string $string) {
        fwrite($this->fd, $string);
    }

    public function h1(string $title) {
        $this->write($title."\n");
        $this->write(str_repeat("=", mb_strlen($title))."\n\n");
    }
    public function h2(string $title) {
        $this->write($title."\n");
        $this->write(str_repeat("-", mb_strlen($title))."\n\n");
    }
    public function h3(string $title) {
        $this->write("### ".$title."\n\n");
    }
    public function p(string $paragraph) {
        $this->write(chunk_split($paragraph, 76, "\n")."\n");
    }
    public function table(array $rows) {
        $cols = [];
        foreach ($rows as $rn => $row) {
            foreach ($row as $cn => $col) {
                $len = mb_strlen($col);
                if (!isset($cols[$cn])) {
                    $cols[$cn] = $len;
                } elseif ($cols[$cn] < $len) {
                    $cols[$cn] = $len;
                }
            }
        }
        foreach ($rows as $rn => $row) {
            $this->write('|');

            foreach ($cols as $cn => $col) {
                $val = '';
                if (isset($row[$cn])) {
                    $val = $row[$cn];
                }
                if (ltrim($val, '0123456789') !== $val) {
                    $this->write(" ".str_pad($row[$cn], $col, " ", STR_PAD_LEFT)." |");
                } else {
                    $this->write(" ".str_pad($row[$cn], $col, " ", STR_PAD_RIGHT)." |");
                }
            }

            $this->write("\n");

            if ($rn === 0) {
                $this->write("|");
                foreach ($cols as $cn => $col) {
                    $this->write(str_repeat("-", $col + 2)."|");
                }
                $this->write("\n");
            }
        }
        $this->write("\n");
    }
}

die();
echo "Launching ramsey/uuid and charm/uuid 100 times each:\n";
for ($i = 0; $i < $count; $i++) {
    usleep(0);
    echo "#$i ramsey/uuid: ";
    $values['ramsey/uuid'] += $time = launch(__DIR__.'/ramsey-bootstrap-ramsey.php', $func);
    echo str_pad($time, 10, " ", STR_PAD_LEFT)." ns\n";

    usleep(0);
    echo "#$i charm/uuid:  ";
    $values['charm/uuid'] += $time = launch(__DIR__.'/ramsey-bootstrap-charm.php', $func);
    echo str_pad($time, 10, " ", STR_PAD_LEFT)." ns\n";
}
$slowest = 0;
$fastest = 100;
$fastestName = null;
$slowestName = null;
foreach ($values as $lib => $time) {
    $avg = $time / ($count * 1000000000);
    if ($avg > $slowest) {
        $slowest = $avg;
        $slowestName = $lib;
    }
    if ($avg < $fastest) {
        $fastest = $avg;
        $fastestName = $lib;
    }
    echo $lib." averaged ".($time / ($count * 1000000000))." seconds to generate the first UUID\n";
}

echo $fastestName." consumes ".number_format(($slowest-$fastest) * 1000, 1)." milliseconds less time to generate the first UUID\n";
//echo $fastestName." performs ".number_format((($slowest/$fastest)-1) * 100, 1)." % faster than ".$slowestName."\n";

function launch(array $specs): array {
    global $argv;
    $argv[1] = serialize($specs);
    $result = null;

    if (function_exists('pcntl_fork')) {
        // Forking is much faster than launching a new PHP interpreter
        $fname = tempnam(sys_get_temp_dir(), 'benchmarking');
        $fp = fopen($fname, 'w+');
        $pid = pcntl_fork();

        if ($pid == -1) {
            fclose($fp);
            unlink($fname);
            goto fork_failed;
        } elseif ($pid) {
            // we are the parent
            pcntl_waitpid($pid, $status);
            usleep(0);
            fseek($fp, 0);
            $resultString = stream_get_contents($fp);
            $result = unserialize($resultString);
        } else {
            // we are the child
            ob_start();
            ftruncate($fp, 0);
            require(__DIR__.'/benchmark-run.php');
            $result = unserialize(ob_get_contents());
            ob_end_clean();
            usleep(0);
            fseek($fp, 0);
            fwrite($fp, (string) serialize($result));
            die();
        }
        fclose($fp);
        unlink($fname);
    } else {
        fork_failed:
        // Fallback to shell_exec
        $t = shell_exec('exec php '.__DIR__.'/benchmark-run.php '.escapeshellarg(serialize($argv[1])));
        $result = unserialize($t);
    }
//echo "RETURNING: ";var_dump($result);
    return $result;
}

function status(string $status) {
    fwrite(STDERR, "\r".str_pad($status, 76)."\r");
}
