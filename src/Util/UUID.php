<?php
namespace Charm\Util;

use Charm\Id;
use JsonSerializable;

class UUID implements JsonSerializable {

    /**
     * A namespace for UUID v3 and v5: Name string is a fully-qualified domain name
     *
     * {@see https://datatracker.ietf.org/doc/html/rfc4122#appendix-C}
     */
    public const NAMESPACE_DNS = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    /**
     * A namespace for UUID v3 and v5: Name string is a URL
     *
     * {@see https://datatracker.ietf.org/doc/html/rfc4122#appendix-C}
     */
    public const NAMESPACE_URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

    /**
     * A namespace for UUID v3 and v5: Name string is an ISO OID
     *
     * {@see https://datatracker.ietf.org/doc/html/rfc4122#appendix-C}
     */
    public const NAMESPACE_OID = '6ba7b812-9dad-11d1-80b4-00c04fd430c8';

    /**
     * A namespace for UUID v3 and v5: Name string is an X.500 DN (in 
     * DER or a text output format)
     *
     * {@see https://datatracker.ietf.org/doc/html/rfc4122#appendix-C}
     */
    public const NAMESPACE_X500 = '6ba7b814-9dad-11d1-80b4-00c04fd430c8';

    /**
     * The nil UUID is special form of UUID that is specified to have all
     * 128 bits set to zero.
     *
     * {@see https://datatracker.ietf.org/doc/html/rfc4122#section-4.1.7}
     */
    public const NIL = '00000000-0000-0000-0000-000000000000';

    protected string $uuid;

    /**
     * @param string|stringable $stringable
     */
    public function __construct($stringable) {
        if (!is_string($stringable) && !(is_object($stringable) && method_exists($stringable, '__toString'))) {
            throw new \TypeError("Expects a string|stringable value");
        }

        $this->uuid = strtolower((string) $stringable);
        $this->assertValid();
    }

    private function assertValid(): void {
        if (1 !== preg_match('|^[0-9a-f]{8}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{4}\-[0-9a-f]{12}$|', $this->uuid, $matches)) {
            throw new \TypeError("Invalid UUID string '".$this->uuid."'");
        }
    }

    /**
     * @param string|self $uuid
     */
    public static function fromUUID($uuid) {
        return new self( (string) $uuid );
    }

    public static function uuid1(): self {
        return self::fromString(Id::uuid1());
    }

    public static function uuid3($ns, string $name): self {
        return self::fromString(Id::uuid3($ns, $name));
    }

    public static function uuid4(): self {
        return self::fromString(Id::uuid4());
    }

    public static function uuid5($ns, string $name): self {
        return self::fromString(Id::uuid5($ns, $name));
    }

    /**
     * Convert a 36 character UUID string to an UUID
     */
    public static function fromString(string $uuid): UUID {
        return new UUID($uuid);
    }

    /**
     * Function that may help increase verbosity of your code
     */
    public function toString(): string {
        return $this->__toString();
    }

    /**
     * Return the UUID as a 32 character hex string
     */
    public function toHex(): string {
        return str_replace("-", "", $this->uuid);
    }

    /**
     * Convert a 32 character hex string to an UUID (not to be confused
     * with an UUID v3 or v5!)
     */
    public static function fromHex(string $hex): UUID {
        $chunks = str_split($hex, 4);
        if (!isset($chunks[7]) || strlen($chunks[7]) !== 4) {
            throw new TypeError("Expecting at least 32 hex characters");
        }
        return new UUID("{$chunks[0]}{$chunks[1]}-{$chunks[2]}-{$chunks[3]}-{$chunks[4]}-{$chunks[5]}{$chunks[6]}{$chunks[7]}");
    }

    /**
     * Convert an 128 bit integer string to an UUID
     */
    public static function fromInteger(string $integer): UUID {
        return self::fromHex(base_convert($integer, 10, 16));
    }

    /**
     * Get 128 bit integer representation of the UUID as a string
     */
    public function toInteger(): string {
        return base_convert($this->toHex(), 16, 10);
    }

    /**
     * Convert a binary UUID string to an UUID
     */
    public static function fromBytes(string $bytes): UUID {
        return self::fromHex(bin2hex(substr($bytes, 0, 16)));
    }

    /**
     * Convert the UUID into a 16 byte binary string
     */
    public function toBytes(): string {
        return hex2bin(str_replace('-', '', $this->uuid));
    }

    public function jsonSerialize(): string {
        return $this->__toString();
    }

    public function __toString(): string {
        return $this->uuid;
    }
}
