<?php
namespace Charm\Util;

/**
 * Warning! This library has not been tested with *threaded* PHP where each thread shares memory. In
 * particular, the `self::$sequenceNumberOffset++` expression may be of concern as it is not atomic. You
 * may want to wrap the generation of UUIDs in a `Thread::synchronized()` closure. It is not sufficient
 * to have separate instances of IdFactory for each thread.
 */
class IdFactory {

    const TYPE_UUID_V1 = 0;
    const TYPE_UUID_V4 = 1;
    const TYPE_UUID_COMB = 5;
    const TYPE_SNOWFLAKE = 2;
    const TYPE_INSTAFLAKE = 3;
    const TYPE_SONYFLAKE = 4;

    const OPTIONS = [
        /**
         * Unix Timestamp which serves as the epoch for the snowflake, instaflake and similar UIDs where the
         * epoch is adjustable. A negative offset is allowed. Default is '2019-01-01 00:00:00'
         */
        'epoch' => 1546300800,

        /**
         * The sequence number is incremented by 1 for every new non-random ID generated. This helps protect
         * against the unlikely event that two ID numbers are generated in the exact same microsecond.
         *
         * The initial sequence number is by default the current PID number from `getmypid()` multiplied by the
         * prime number 19423.
         *
         * Different algorithms use a limited number of bits from this sequence number, so it is possible - while
         * very unlikely that two instances of IdFactory running in different processes will calculate the exact
         * same sequence number on the exact same microsecond. This is more likely if you have many CPU cores and
         * each process is generating a lot of IDs continously.
         *
         * If the PID changes, the initial sequence number will be recalculated.
         *
         */
        'initialSequenceNumberFunction' => null,

        /**
         * Override the machine ID. This ID is used in all ID schemes, except UUID v4 which is completely random.
         * The ID is a 48 bit integer, where UUID v1 uses all of it. It will be retrieved from the first network
         * cards' mac address, the 'machine-id' in Linux, or the 'MachineGuid' from the Windows registry. If no
         * id can be found, we will use the Kubernetes UID from the hostname and finally generate a cryptographic
         * random integer.
         *
         * Only UUID v1 uses the entire 48 bits. The ID schemes will truncate the id to a predetermined number
         * of bits:
         *
         *   UUID v1            Uses 48 bits from the machineId (the size of a standard mac address)
         *
         *   UUID v4            Does not use the machineId
         *
         *   snowflake          Uses the 10 least significant bits. If you want to specify datacenter id and
         *                      machine id separately, you can use the following formula to combine the values:
         *                      `($datacenterId & 0x1F) << 5 | ($machineId & 0x1F)`, where $datacenterId and
         *                      $machineId is an integer between 0 and 31.
         *
         *   instaflake         Uses the 13 least significant bits for its "shardId".
         *
         *   sonyflake          Uses the 16 least significant bits for its machine id.
         */
        'machineId' => null,

        /**
         * The UUID standard RFC 41dd expects a mac address, but it also allows using a random number if no mac
         * address is allowed. Set this to false if you do not wish to expose the mac address.
         */
        'allowMacAddress' => true,

        /**
         * Allow using the pod UID to extract a unique machine ID, if kubernetes is detected.
         */
        'allowKubernetesId' => true,

        /**
         * Allow unique machine ID from /var/lib/dbus/machine-id or on Windows, the registry entry in 
         * 'HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Cryptography\MachineGuid'?
         */
        'allowMachineId' => true,

        /**
         * Custom UID provider callback. If this is provided, the function will be invoked to retrieve a UID from
         * the system. If the function returns NULL, we try to get an UID via other methods. The function should
         * apply `$returnValue |= 1 << 40` if the ID is NOT a mac address.
         */
        'customMachineIdFunction' => null,
    ];

    protected int $type;
    protected int $epoch;
    protected int $initialSequenceNumber;
    protected int $hrOffset;
    protected ?int $_machineId = null;    
    private array $options;

    /**
     * Used by `self::getSequenceNumber()` to trigger generating a new initial sequence number
     */
    private ?int $pid = null;

    protected static int $sequenceNumberOffset = 0;

    public function __construct(int $type=self::TYPE_UUID_V4, array $options=[]) {
        $this->type = $type;
        $this->options = $options + self::OPTIONS;
        $this->epoch = $this->options['epoch'];
        $this->nanoTime();
    }

    /**
     * Returns a UNIX time stamp with close to nanosecond precision. Note that the
     * *first* call to this function will be rounded to microsecond precision.
     * Nanosecond precision is achieved from consecutive calls to this function.
     */
    public static function nanoTime(int $offset=0) {
        static $diff = null;
        $nt = hrtime();
        if ($diff === null) {
            $mt2 = explode(" ", microtime());
            $diff = [
                (int) $mt2[1] - $nt[0],
                $mt2[0] * 1000000000 - $nt[1],
            ];
        }

        $res = [ $nt[0] + $diff[0], $nt[1] + $diff[1] ];
        if ($res[1] < 0) {
            $res[0]--;
            $res[1] += 1000000000;
        } elseif ($res[1] >= 1000000000) {
            $res[0]++;
            $res[1] -= 1000000000;
        }
        return $res;
    }

    /**
     * Returns a 64 bit timestamp, where 36 bits is used for the seconds and
     * 28 bits is used for the fractions of a second elapsed since the Unix
     * epoch. The precision is about4 nanoseconds (a second is divided into
     * 268-millionths). The number will overflow in the year 4147.
     */
    public static function hexNanoTime(int $offset = 0) {
        $nt = static::nanoTime($offset);
        $fraction = $nt[1];
        $binaryFraction = 0;
        for ($i = 0; $i < 28; $i++) {
            $binaryFraction <<= 1;
            $fraction *= 2;
            if ($fraction >= 1000000000) {
                $binaryFraction |= 1;
                $fraction -= 1000000000;
            }
        }
        return sprintf('%09x%07x', $nt[0], $binaryFraction);
    }

    /**
     * Generate a "comb uuid", which is sequential (sorted) and validates as a
     * UUID v4. 60 bits is used for the time stamp, where 24 bits is used for
     * the fraction of a second and the timestamp begins at UNIX epoch.
     *
     * Specification:
     *
     *   Timestamp          60 bit      36 bits seconds and 24 bits fractions of seconds since unix epoch
     *   Clock Sequence     14 bit      A "uniquifying" clock sequence which increments by one for every ID generated
     *   Machine ID         48 bit      A unique number for the current computer. Should be globally unique.
     *
     * Return value is a 128 bit UUID string encoded according to the standard, or as a 128 bit big-endian binary string.
     */
    public function comb() {
        $ts = self::hexNanoTime();
        $res =
            substr($ts, 0, 8).
            '-'.substr($ts, 8, 4).
            '-4'.substr($ts, 12, 3).
            '-'.sprintf('%04x-%012x', ($this->getSequenceNumber() & 0x3FFF) + 0x8000, $this->getMachineId() & 0xFFFFFFFFFFFF);

        return $res;
    }

    /**
     * Generate an UUID(1) based on a time stamp and machine ID. Traditionally this
     * used to be fetched from the mac address, but RFC 4122 allows the use of another
     * ID.
     *
     * Specification:
     *
     *   Timestamp          60 bit      The number of 100-nanosecond intervals since
     *                                  the start of the Julian calendar
     *   Machine ID         48 bit      A unique number for the current computer.
     *                                  Should be globally unique.
     *   Clock Sequence     13 bit      A "uniquifying" clock sequence which increments
     *                                  by one for every ID generated to protect against
     *                                  simultaneous IDs generated on the same computer.
     *
     * Return value is a 128 bit UUID string encoded according to the standard, or as a
     * 128 bit big-endian binary string.
     */
    public function v1(bool $binary=false) {
        // Timestamp 60 bit (since we don't have an accurate enough clock, we make up the last decimal
        $ts = (int) (microtime(true) * 10000000) + mt_rand(0,9) + 0x01b21dd213814000;

        // Machine ID 48 bit
        $nodeId = $this->getMachineId() & 0xFFFFFFFFFFFF;

        $clockSequence = $this->getSequenceNumber() & 0x3FFF;

        $timeLow = $ts & 0xFFFFFFFF;                                // 32 bit
        $timeMid = ($ts >> 32) & 0xFFFF;                            // 16 bit
        $timeHiAndVersion = ($ts >> 48) & 0xFFF;                    // 12 bit
        $timeHiAndVersion |= (1 << 12);
        $clockSeqHiAndReserved = ($clockSequence & 0x3F00) >> 8;    // 8 bit
        $clockSeqHiAndReserved |= 0x80;
        $clockSeqLow = $clockSequence & 0xFF;                       // 8 bit

        if ($binary) {
            $l1 = $timeLow;                                         // 32 bit
            $l2 = ($timeMid << 16) | $timeHiAndVersion;             // 32 bit
            $l3 = ($clockSeqHiAndReserved << 24) | ($clockSeqLow << 16) | ($nodeId >> 32);      // 32 bit
            $l4 = $nodeId & 0xFFFF;

            return pack('NNNN', $l1, $l2, $l3, $l4);
        }

        return sprintf('%08x-%04x-%04x-%02x%02x-%012x', $timeLow, $timeMid, $timeHiAndVersion, $clockSeqHiAndReserved, $clockSeqLow, $nodeId);
    }

    /**
     * Generate an UUID v3 based on a namespace and a string. This UUID is intended
     * to be used as a unique identifier for things.
     */
    public static function v3($namespace, string $name) {
        $uuid = UUID::fromUUID($namespace);

        $bytes = md5($uuid->toBytes() . $name);
        $bytes[12] = '3';
        $bytes[16] = dechex(hexdec($bytes[16]) & ~4 | 8);

        return (string) UUID::fromHex($bytes);
    }

    /**
     * Generate a UUID(4) - a cryptographically random identifier. This number can be safely generated
     * without any configuration.
     */
    public static function v4(): string {
        $hex = bin2hex($bytes = random_bytes(18));
        $hex[8] = '-';
        $hex[13] = '-';
        $hex[14] = '4';
        $hex[18] = '-';
        $hex[19] = '89ab'[ord($bytes[9]) >> 6];
        $hex[23] = '-';
        return $hex;
    }

    /**
     * Generate an UUID v5 based on a namespace and a string. This UUID is intended
     * to be used as a unique identifier for things.
     */
    public static function v5($namespace, string $name) {
        $uuid = UUID::fromUUID($namespace);

        $bytes = sha1($uuid->toBytes() . $name);
        $bytes[12] = '5';
        $bytes[16] = dechex(hexdec($bytes[16]) & ~4 | 8);

        return (string) UUID::fromHex($bytes);
    }

    /**
     * Generate a snowflake ID.
     */
    public function snowflake(): int {
        return ((intval((microtime(true) - $this->epoch) * 1000) & 0x1FFFFFFFFFF) << 22)
            | ((($this->_machineId ?? $this->getMachineId()) & 0x3FF) << 12)
            | ($this->getSequenceNumber() & 0xFFF);
    }

    /**
     * Generate an instaflake ID
     */
    public function instaflake(): int {
        return ((intval((microtime(true) - $this->epoch) * 1000) & 0x1FFFFFFFFFF) << 22)
            | ((($this->_machineId ?? $this->getMachineId()) & 0x1FFF) << 10)
            | ($this->getSequenceNumber() & 0x3FF);
    }

    /**
     * Generate a SonyFlake ID
     */
    public function sonyflake(): int {
        return ((intval((microtime(true) - $this->epoch) * 100) & 0x7FFFFFFF) << 24)
            | (($this->getSequenceNumber() & 0xFF) << 16)
            | (($this->_machineId ?? $this->getMachineId()) & 0xFFFF);
    }

    /**
     * Retrieve a 48 bit integer value which is supposed to uniquely identify this computer and protect
     * against collisions from other random number generators.
     */
    public function getMachineId(): int {
        if ($this->_machineId !== null) {
            return $this->_machineId;
        }

        if ($this->options['customMachineIdFunction']) {
            $res = call_user_func($this->options['customMachineIdFunction']);
            if (is_int($res)) {
                return $this->_machineId = ($res & 0xFFFFFFFFFFFF);
            }
        }

        if (\PHP_OS_FAMILY === 'Windows' && $this->options['allowMachineId']) {
            // Fetch MachineGuid from the registry
            $res = shell_exec('reg query '.escapeshellarg('HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Cryptography').' -v '.escapeshellarg('MachineGuid'));
            if ($res) {
                $res = explode("\n", trim($res));
                if (isset($res[1])) {
                    $res = explode("    ", $res[1])[3];
                    $res = md5($res);
                    $res = substr($res, -16);
                    $res[0] = dechex(hexdec($res[0]) & 7);
                    $id = hexdec($res);

                    // multicast bit in mac address should be on to indicate that this is not a mac address
                    $id |= 1 << 40;
                    return $this->_machineId = $id;
                }
            }
        }

        if (\PHP_OS_FAMILY !== 'Windows' && $this->options['allowMacAddress']) {
            foreach (glob('/sys/class/net/*/address') as $addressFile) {
                if ($addressFile === '/sys/class/net/lo/address') {
                    /* loopback device is not usable */
                    continue;
                }
                $mac = trim(str_replace(":", "", file_get_contents($addressFile)));
                if (strlen($mac) > 0) {
                    return $this->_machineId = hexdec($mac);
                }
            }
        }

        if (\PHP_OS_FAMILY !== 'Windows' && $this->options['allowMachineId']) {
            $res = null;
            if (file_exists('/var/lib/dbus/machine-id')) {
                $res = trim(file_get_contents('/var/lib/dbus/machine-id'));
            } elseif (file_exists('/etc/machine-id')) {
                $res = trim(file_get_contents('/etc/machine-id'));
            }
            if ($res) {
                $res = substr($res, -16);
                $res[0] = dechex(hexdec($res[0]) & 7);
                $id = hexdec($res);
                // multicast bit in number should be on to indicate that this is not a mac address
                $id |= 1 << 40;
                return $this->_machineId = $id;
            }
        }

        if ($this->options['allowKubernetesId'] && getenv('KUBERNETES_PORT')) {
            $hostname = gethostname();
            $res = preg_match('|\-[a-z0-9]{10}\-[a-z0-9]{5}$|', $hostname, $matches);
            if ($res === 1) {
                // seems like the hostname contains a 60 bit Kubernetes UID
                return $this->_machineId = hexdec(str_replace("-", "", $matches[0])) & 0xFFFFFFFFFFFF;
            }
        }

        // Finally fallback to a cryptographic random int
        return $this->_machineId = random_int(0, 0xFFFFFFFFFFFF | (1 << 40));
    }

    /**
     * Generates a new sequence number which can be used to generate a new ID.
     */
    public function getSequenceNumber(): int {
        if ($this->pid !== getmypid()) {
            $this->setInitialSequenceNumber();
            $this->pid = getmypid();
        }
        if (0x7FFFFFFFFFFFFFFF === ++self::$sequenceNumberOffset) {
            self::$sequenceNumberOffset = 0;
        }
        return ($this->initialSequenceNumber + self::$sequenceNumberOffset) & 0x7FFFFFFFFFFFFFFF;
    }

    /**
     * Set/reset the initial sequence number
     */
    protected function setInitialSequenceNumber(): void {
        if ($this->options['initialSequenceNumberFunction'] !== null) {
            $this->initialSequenceNumber = $this->options['initialSequenceNumberFunction']();
        }
        $this->initialSequenceNumber = 19423 * getmypid();
    }

    /**
     * Generate the ID type that was configured in the constructor.
     */
    public function __invoke() {
        switch ($this->type) {
            case self::TYPE_UUID_V1 :
                return $this->v1();
            case self::TYPE_UUID_V4 :
                return $this->v4();
            case self::TYPE_SNOWFLAKE :
                return $this->snowflake();
            case self::TYPE_INSTAFLAKE :
                return $this->instaflake();
            case self::TYPE_SONYFLAKE :
                return $this->sonyflake();
            case self::TYPE_UUID_COMB :
                return $this->comb();
            default :
                throw new Exception("Unknown type specified");
        }
    }

}
