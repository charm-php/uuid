<?php
namespace Charm;

use Charm\Util\{IdFactory, IdFactoryError};

/**
 * Class provides an entrypoint to create IDs. When developing using
 * dependency injection or service containers, the 'Charm\Util\IdFactory'
 * class should be used instead.
 */
final class Id {

    /**
     * Configure the default ID factory. This must be done before any
     * of the other functions are used, or the default configuration
     * will be used.
     *
     * @param int $type         One of the Charm\Util\IdFactory::TYPE_* constants
     * @param array $options    Options as documented in {@see Charm\Util\IdFactory::DEFAULTS}
     * @param bool $forceConfig Allows you to override the configuration even if it has already been configured
     */
    public static function configure(int $type, array $options=[], bool $forceConfig=false) {
        if (self::$factory !== null && !$forceConfig) {
            throw new IdFactoryError("The factory is already configured");
        }

        self::$factory = new IdFactory($type, $options);
    }

    /**
     * Generate a new ID according to the configuration.
     */
    public static function make() {
        if (self::$factory === null) {
            return self::uuid4();
        }
        return self::getFactory()();
    }

    /**
     * Generate a new UUID version 1
     */
    public static function uuid1(): string {
        return self::getFactory()->v1();
    }

    /**
     * Generate a new UUID version 4
     */
    public static function uuid4(): string {
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
     * Generate a combined time GUID
     */
    public static function comb(): string {
        return self::getFactory()->comb();
    }

    /**
     * Generate a new 'snowflake' id
     */
    public static function snowflake(): int {
        return self::getFactory()->snowflake();
    }

    /**
     * Generate a new 'instaflake' id
     */
    public static function instaflake(): int {
        return self::getFactory()->instaflake();
    }

    /**
     * Generate a new 'sonyflake' id
     */
    public static function sonyflake(): int {
        return self::getFactory()->sonyflake();
    }

    /**
     * Holds the configured factury, if one exists.
     */
    private static ?IdFactory $factory = null;

    /**
     * Returns the configured factory or a new instance
     */
    private static function getFactory() {
        return self::$factory ?? new IdFactory();
    }

}
