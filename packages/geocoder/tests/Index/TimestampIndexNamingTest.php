<?php

namespace Tito10047\Geocoder\Tests\Index;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Storage\TableNames;

final class TimestampIndexNamingTest extends TestCase
{
    public function testNamesBuildsAfterTheirUtcStart(): void
    {
        $naming = new TimestampIndexNaming();

        $this->assertSame('geo', $naming->liveUid());
        $this->assertSame('geo_20261023120000', $naming->buildUid(new \DateTimeImmutable('2026-10-23 14:00:00', new \DateTimeZone('Europe/Bratislava'))));
    }

    public function testCustomLiveUid(): void
    {
        $naming = new TimestampIndexNaming('tankovac-geo');

        $this->assertSame('tankovac-geo_20261023120000', $naming->buildUid(new \DateTimeImmutable('2026-10-23 12:00:00 UTC')));
        $this->assertTrue($naming->isBuildUid('tankovac-geo_20261023120000'));
    }

    #[DataProvider('provideUids')]
    public function testRecognisesBuilds(string $uid, bool $build): void
    {
        $this->assertSame($build, (new TimestampIndexNaming())->isBuildUid($uid));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideUids(): iterable
    {
        yield 'build' => ['geo_20261023120000', true];
        yield 'live' => ['geo', false];
        yield 'another application' => ['geo_products', false];
        yield 'short timestamp' => ['geo_2026102312', false];
        yield 'another live uid' => ['geox_20261023120000', false];
        yield 'prefixed' => ['old_geo_20261023120000', false];
        yield 'trailing newline' => ["geo_20261023120000\n", false];
    }

    public function testBuildSuffixIsTheSuffixOfItsTables(): void
    {
        $naming = new TimestampIndexNaming();

        $suffix = $naming->buildSuffix('geo_20261023120000');

        $this->assertSame('_20261023120000', $suffix);
        $this->assertSame('geo_house_20261023120000', (new TableNames())->withSuffix($suffix)->houseNumbers);
    }

    public function testBuildSuffixRejectsAnotherIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TimestampIndexNaming())->buildSuffix('products');
    }

    #[DataProvider('provideInvalidLiveUids')]
    public function testRejectsInvalidLiveUids(string $uid): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TimestampIndexNaming($uid);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidLiveUids(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['my geo'];
        yield 'slash' => ['geo/1'];
        yield 'too long with the suffix' => [str_repeat('g', 390)];
    }
}
