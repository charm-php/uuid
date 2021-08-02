<?php
namespace Charm;

use Charm\Util\IdFactory;

require('Util/IdFactory.php');

final class Id {

    /**
     * Configure the default ID factory. This must be done before any
     * of the other functions are used, or the default configuration
     * will be used.
     */
    public static function configure(int $type, array $options=[]) {
        if (self::$factory !== null) {
            throw new IdFactoryError("The factory is already configured");
        }

        self::$factory = new IdFactory($type, $options);
    }

    /**
     * Generate a new ID according to the configuration.
     */
    public static function id() {
        return (self::$factory ?? self::getFactory())();
    }

    /**
     * Generate a new UUID version 1
     */
    public static function uuid1(): string {
        return (self::$factory ?? self::getFactory())->v1();
    }

    /**
     * Generate a new UUID version 4
     */
    public static function uuid4(): string {
        return (self::$factory ?? self::getFactory())->v4();
    }

    /**
     * Generate a new 'snowflake' id
     */
    public static function snowflake(): int {
        return (self::$factory ?? self::getFactory())->snowflake();
    }

    /**
     * Generate a new 'instaflake' id
     */
    public static function instaflake(): int {
        return (self::$factory ?? self::getFactory())->instaflake();
    }

    /**
     * Generate a new 'sonyflake' id
     */
    public static function sonyflake(): int {
        return (self::$factory ?? self::getFactory())->sonyflake();
    }

    private static ?IdFactory $factory = null;
    private static function getFactory() {
        return self::$factory = new IdFactory();
    }

}
