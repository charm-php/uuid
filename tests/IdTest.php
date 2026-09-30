<?php

use Charm\Id;
use Charm\UUID as LegacyUUID;
use Charm\Util\IdFactory;
use Charm\Util\UUID;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdTest extends TestCase
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-%s[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public static function generators(): array
    {
        return [
            'uuid1' => ['1', fn() => Id::uuid1()],
            'uuid4' => ['4', fn() => Id::uuid4()],
            'comb' => ['4', fn() => Id::comb()],
            'factory v1' => ['1', fn() => (new IdFactory(IdFactory::TYPE_UUID_V1))->v1()],
            'legacy v1' => ['1', fn() => LegacyUUID::v1()],
            'legacy v4' => ['4', fn() => LegacyUUID::v4()],
        ];
    }

    #[DataProvider('generators')]
    public function testGeneratesValidUniqueUuids(string $version, Closure $generate): void
    {
        $seen = [];
        for ($i = 0; $i < 5000; $i++) {
            $uuid = $generate();
            $this->assertMatchesRegularExpression(sprintf(self::UUID_PATTERN, $version), $uuid);
            $seen[$uuid] = true;
        }
        $this->assertCount(5000, $seen);
    }

    public function testNameBasedUuidsMatchRfcVectors(): void
    {
        $this->assertSame('5df41881-3aed-3515-88a7-2f4a814cf09e', Id::uuid3(UUID::NAMESPACE_DNS, 'www.example.com'));
        $this->assertSame('2ed6657d-e927-568b-95e1-2665a8aea6a2', Id::uuid5(UUID::NAMESPACE_DNS, 'www.example.com'));
    }

    public function testCombIsIncreasing(): void
    {
        $previous = Id::comb();
        for ($i = 0; $i < 1000; $i++) {
            $next = Id::comb();
            $this->assertGreaterThan(0, strcmp($next, $previous), "$next after $previous");
            $previous = $next;
        }
    }

    public static function flakeTypes(): array
    {
        return [
            'snowflake' => [IdFactory::TYPE_SNOWFLAKE],
            'instaflake' => [IdFactory::TYPE_INSTAFLAKE],
            'sonyflake' => [IdFactory::TYPE_SONYFLAKE],
        ];
    }

    /**
     * Also a regression test for charm-php/uuid#3: phpunit.xml.dist fails on deprecations, and
     * IdFactory used to create a dynamic property in its constructor.
     */
    #[DataProvider('flakeTypes')]
    public function testFlakesArePositiveAndUnique(int $type): void
    {
        $factory = new IdFactory($type, ['machineId' => 42]);
        $seen = [];
        for ($i = 0; $i < 200; $i++) {
            $id = $factory();
            $this->assertIsInt($id);
            $this->assertGreaterThan(0, $id);
            $seen[$id] = true;
        }
        $this->assertCount(200, $seen);
    }

    public function testUuidConversionsRoundTrip(): void
    {
        $uuid = UUID::fromString(UUID::NAMESPACE_DNS);
        $this->assertSame('6ba7b8109dad11d180b400c04fd430c8', $uuid->toHex());
        $this->assertSame(UUID::NAMESPACE_DNS, (string) UUID::fromHex($uuid->toHex()));
        $this->assertSame(UUID::NAMESPACE_DNS, (string) UUID::fromBytes($uuid->toBytes()));
        $this->assertSame('"' . UUID::NAMESPACE_DNS . '"', json_encode($uuid));
    }

    public function testIntegerConversionIsExact(): void
    {
        $this->assertSame('143098242404177361603877621312831893704', UUID::fromString(UUID::NAMESPACE_DNS)->toInteger());
        $this->assertSame(UUID::NAMESPACE_DNS, (string) UUID::fromInteger('143098242404177361603877621312831893704'));
        $this->assertSame('0', UUID::fromString(UUID::NIL)->toInteger());
        $this->assertSame(UUID::NIL, (string) UUID::fromInteger('0'));
        $max = 'ffffffff-ffff-ffff-ffff-ffffffffffff';
        $this->assertSame('340282366920938463463374607431768211455', UUID::fromString($max)->toInteger());
        $this->assertSame($max, (string) UUID::fromInteger('340282366920938463463374607431768211455'));
    }

    public function testRejectsInvalidInput(): void
    {
        $this->expectException(TypeError::class);
        UUID::fromHex('abc');
    }

    public function testRejectsInvalidUuidString(): void
    {
        $this->expectException(TypeError::class);
        UUID::fromString('not-a-uuid');
    }
}
