<?php
namespace Charm\Util;

use Closure;

class IdFactory {

    const TYPE_UUID_V1 = 0;
    const TYPE_UUID_V4 = 1;
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
         * Sequence number is incremented for every ID that is generated. This is used to ensure that the ID is
         * unique, even if two IDs are generated within the same time window on the same computer. The value is
         * initialized to a random 32 bit number. The different algorithms will truncate the number to use the
         * neccesary number of least significant bits.
         */
        'initialSequenceNumber' => null,

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
    protected int $sequenceNumber;
    private ?int $_machineId = null;

    public function __construct(int $type=self::TYPE_UUID_V1, array $options=[]) {
        $this->type = $type;
        $this->options = $options + self::OPTIONS;
        $this->epoch = $this->options['epoch'];
        $this->sequenceNumber = $this->getInitialSequenceNumber();
    }

    /**
     * Generate an UUID version 1 variant 1 (time stamp and machine ID). Traditionally this used to be fetched
     * from the mac address, but RFC 4122 allows the use of another ID.
     *
     * Specification:
     *
     *   Timestamp          60 bit      The number of 100-nanosecond intervals since the start of the Julian calendar
     *   Machine ID         48 bit      A unique number for the current computer. Should be globally unique.
     *   Clock Sequence     13 bit      A "uniquifying" clock sequence which increments by one for every ID generated
     *
     * Return value is a 128 bit UUID string encoded according to the standard, or as a 128 bit big-endian binary string.
     */
    public function v1(bool $binary=false) {
        // Timestamp 60 bit (since we don't have an accurate enough clock, we make up the last decimal
        $ts = (int) (microtime(true) * 10000000) + mt_rand(0,9) + 0x01b21dd213814000;

        // Machine ID 48 bit
        $nodeId = $this->getMachineId() & 0xFFFFFFFFFFFF;

        $clockSequence = $this->sequenceNumber++ & 0x3FFF;

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
     * Generate a cryptographically random version 4 UUID string.
     * UUID v4 is a random number with no time or spatial component.
     */
    public static function v4(): string {
        $hex = bin2hex($bytes = random_bytes(18));
        $hex[8] = '-';
        $hex[13] = '-';
        $hex[14] = '4';
        $hex[18] = '-';
        $hex[19] = '89ab'[ord($bytes[9]) % 4];
        $hex[23] = '-';
        return $hex;
    }

    /**
     * Generate a snowflake ID.
     */
    public function snowflake(): int {
        return ((((microtime(true) - $this->epoch) * 1000) & 0x1FFFFFFFFFF) << 22)
            | ((($this->_machineId ?? $this->getMachineId()) & 0x3FF) << 12)
            | ($this->sequenceNumber++ & 0xFFF);
    }

    /**
     * Generate an instaflake ID
     */
    public function instaflake(): int {
        return ((intval((microtime(true) - $this->epoch) * 1000) & 0x1FFFFFFFFFF) << 22)
            | ((($this->_machineId ?? $this->getMachineId()) & 0x1FFF) << 10)
            | ($this->sequenceNumber++ & 0x3FF);
    }

    public function sonyflake(): int {
        return ((intval((microtime(true) - $this->epoch) * 100) & 0x7FFFFFFF) << 24)
            | (($this->sequenceNumber++ & 0xFF) << 16)
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
            if (!$res) {
                break;
            }
            $res = explode("\n", trim($res));
            if (!isset($res[1])) {
                break;
            }
            $res = explode("    ", $res[1])[3];
            $res = md5($res);
            $res = substr($res, -16);
            $res[0] = dechex(hexdec($res[0]) & 7);
            $id = hexdec($res);

            // multicast bit in mac address should be on to indicate that this is not a mac address
            $id |= 1 << 40;
            return $this->_machineId = $id;
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

    private function getInitialSequenceNumber(): int {
        if ($this->options['initialSequenceNumber'] !== null) {
            return $this->options['initialSequenceNumber'];
        }
        $sequenceNumber = getmypid();
        $sequenceNumber |= $sequenceNumber << 16;
        return $sequenceNumber;
    }

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
            default :
                throw new Exception("Unknown type specified");
        }
    }

}
