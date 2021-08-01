<?php
namespace Charm;

class UUID {
    const NIL = '00000000-0000-0000-0000-000000000000';

    /**
     * Snowflake settings
     */
    public static $startTime = 1565251688;      // Default epoch for snowflake, instaflake, sonyflake is 2019-08-08 08:08:08
    public static $shardId = null;              // Worker/shard id for snowflake, instaflake or sonyflake (uses random number unless specified)
    public static $datacenterId = null;         // For snowflake a value between 0 and 31 (uses random number unless specified)

    private static $serial = null;

    private static $cache = [];

    /**
     * Generate a UUID v1 string. This function uses the machine-id instead
     * of the mac address.
     */
    public static function v1(string $nodeIdHex=null, int $clockSequence = null): string {
        // Timestamp 60 bit
        $ts = (int) (microtime(true) * 10000000) + mt_rand(0,9) + 0x01b21dd213814000;

        // Machine ID 48 bit
        if ($nodeIdHex === null) {
            $nodeIdHex = substr(md5(self::machineId()), 0, 12); // 48 bit
            $nodeIdHex[0] = dechex(hexdec($nodeIdHex[0]) & 1); // https://datatracker.ietf.org/doc/html/rfc4122
        }

        if ($clockSequence === null) {
            if (static::$serial === null) {
                static::$serial = mt_rand(0, 0x3FFF);
            }
            $clockSequence = static::$serial++;
        }

        $timeLow = $ts & 0xFFFFFFFF;
        $timeMid = ($ts >> 32) & 0xFFFF;
        $timeHiAndVersion = ($ts >> 48) & 0x0FFF;
        $timeHiAndVersion |= (1 << 12);
        $clockSeqHiAndReserved = ($clockSequence & 0x3F00) >> 8;
        $clockSeqHiAndReserved |= 0x80;
        $clockSeqLow = $clockSequence & 0xFF;

        return sprintf('%08x-%04x-%04x-%02x%02x-%s', $timeLow, $timeMid, $timeHiAndVersion, $clockSeqHiAndReserved, $clockSeqLow, $nodeIdHex);
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

    public static function snowflake() {
        if (static::$serial === null) {
            static::$serial = mt_rand(0, 0xFFF);
        }
        $snowflake = (intval((microtime(true) - static::$startTime) * 1000) & 0x1FFFFFFFFFF) << 22;
        $snowflake |= ((static::$datacenterId ?? mt_rand(0, 0x1F)) & 0x1F) << 17;
        $snowflake |= ((static::$shardId ?? mt_rand(0, 0x1f)) & 0x1F) << 12;
        $snowflake |= (static::$serial++ & 0xFFF);
        return $snowflake;
    }

    public static function instaflake() {
        if (static::$serial === null) {
            static::$serial = mt_rand(0, 0x1111111111);
        }
        $instaflake = (intval((microtime(true) - static::$startTime) * 1000) & 0x1FFFFFFFFFF) << 22;
        $instaflake |= ((static::$shardId ?? mt_rand(0, 0b1111111111111)) & 0b1111111111111) << 10;
        $instaflake |= (static::$serial++ & 0b1111111111);
        return $instaflake;
    }

    public static function sonyflake() {
        if (static::$serial === null) {
            static::$serial = mt_rand(0, 0xFF);
        }
        $sonyflake = (intval((microtime(true) - static::$startTime) * 100) & 0x7FFFFFFF) << 24;
        $sonyflake |= (static::$serial++ & 0xFF) << 16;
        $sonyflake |= (static::$shardId ?? mt_rand(0, 0xFFFF)) & 0xFFFF;
        return $sonyflake;
    }

    /**
     * Retrieve a unique ID for this computer. The form of the ID differs between operating systems.
     */
    public static function machineId(): ?string {
        if (empty(static::$cache['machine-id'])) {
            switch (\PHP_OS_FAMILY) {

                case 'Windows' :
                    static::$cache['machine-id'] = static::regQuery('HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Cryptography', 'MachineGuid');
                    break;

                default :
                    if (file_exists('/var/lib/dbus/machine-id')) {
                        static::$cache['machine-id'] = trim(file_get_contents('/var/lib/dbus/machine-id'));
                    } elseif (file_exists('/etc/machine-id')) {
                        static::$cache['machine-id'] = trim(file_get_contents('/etc/machine-id'));
                    }
                    break;

            }
        }
        return static::$cache['machine-id'] ?? null;
    }

    /**
     * Ask the Linux kernel to generate an UUID v4.
     */
    public static function v4_kernel() {
        return file_get_contents('/proc/sys/kernel/random/uuid');
    }

    /**
     * A 10 % slower generator which doesn't waste 2 bytes of entropy
     * for performance.
     */
    public static function v4_low_entropy(): string {
        $hex = bin2hex($bytes = random_bytes(16));
        $hex .= $hex[8].$hex[13].$hex[18].$hex[23];
        $hex[8] = '-';
        $hex[13] = '-';
        $hex[14] = '4';
        $hex[18] = '-';
        $hex[19] = '89ab'[ord($bytes[9]) >> 6];
        $hex[23] = '-';
        return $hex;
    }


    private static function regQuery(string $path, string $key): string {
        $res = explode("\n", trim(shell_exec('reg query '.escapeshellarg($path).' -v '.escapeshellarg($key))));
        if (isset($res[1])) {
            return explode("    ", $res[1])[3];
        }
        return $res;
    }

}


