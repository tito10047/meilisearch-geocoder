<?php

namespace Tito10047\Geocoder\Tests\Status;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Index\IndexStatus;
use Tito10047\Geocoder\Status\GeocoderState;
use Tito10047\Geocoder\Status\GeocoderStatus;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Fake\Builds;

final class GeocoderStatusTest extends TestCase
{
    public function testHoldsTheStateOfTheIndexAndTheBuild(): void
    {
        $index = self::index();
        $build = Builds::build(new TableNames());

        $status = new GeocoderStatus(GeocoderState::Ready, 'geo', '1.35.1', $index, liveBuild: $build);

        $this->assertSame($index, $status->index);
        $this->assertSame($build, $status->liveBuild);
        $this->assertTrue($status->state->isReady());
    }

    public function testAnUnreachableEngineCarriesItsError(): void
    {
        $status = new GeocoderStatus(GeocoderState::Unreachable, 'geo', error: 'Connection refused', buildError: 'The database went away.');

        $this->assertSame('Connection refused', $status->error);
        $this->assertSame('The database went away.', $status->buildError);
        $this->assertFalse($status->state->isReady());
    }

    /**
     * @return iterable<string, array{\Closure(): GeocoderStatus}>
     */
    public static function invalid(): iterable
    {
        yield 'empty uid' => [static fn (): GeocoderStatus => new GeocoderStatus(GeocoderState::NoIndex, '')];
        yield 'ready without index' => [static fn (): GeocoderStatus => new GeocoderStatus(GeocoderState::Ready, 'geo', '1.35.1')];
        yield 'index without being ready' => [static fn (): GeocoderStatus => new GeocoderStatus(GeocoderState::NoIndex, 'geo', '1.35.1', self::index())];
        yield 'unreachable without error' => [static fn (): GeocoderStatus => new GeocoderStatus(GeocoderState::Unreachable, 'geo')];
        yield 'not configured with an empty error' => [static fn (): GeocoderStatus => new GeocoderStatus(GeocoderState::NotConfigured, 'geo', error: '')];
        yield 'error while ready' => [static fn (): GeocoderStatus => new GeocoderStatus(GeocoderState::Ready, 'geo', '1.35.1', self::index(), 'error')];
        yield 'version of an unreachable engine' => [static fn (): GeocoderStatus => new GeocoderStatus(GeocoderState::Unreachable, 'geo', '1.35.1', error: 'error')];
        yield 'build with an error' => [static fn (): GeocoderStatus => new GeocoderStatus(GeocoderState::NoIndex, 'geo', liveBuild: Builds::build(new TableNames()), buildError: 'error')];
    }

    /**
     * @param \Closure(): GeocoderStatus $status
     */
    #[DataProvider('invalid')]
    public function testRejectsValuesNotFittingTheState(\Closure $status): void
    {
        $this->expectException(InvalidArgumentException::class);

        $status();
    }

    private static function index(): IndexStatus
    {
        return new IndexStatus('geo', 21, 1000, 2000, false, null, '1.35.1');
    }
}
