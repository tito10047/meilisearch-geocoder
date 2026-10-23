<?php

namespace Tito10047\Geocoder\Tests\Status;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Status\GeocoderState;

final class GeocoderStateTest extends TestCase
{
    /**
     * @return iterable<string, array{GeocoderState, bool}>
     */
    public static function states(): iterable
    {
        yield 'ready' => [GeocoderState::Ready, true];
        yield 'no index' => [GeocoderState::NoIndex, false];
        yield 'unreachable' => [GeocoderState::Unreachable, false];
        yield 'not configured' => [GeocoderState::NotConfigured, false];
    }

    #[DataProvider('states')]
    public function testOnlyReadyAnswersSearches(GeocoderState $state, bool $ready): void
    {
        $this->assertSame($ready, $state->isReady());
    }
}
