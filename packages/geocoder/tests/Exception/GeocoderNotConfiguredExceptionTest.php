<?php

namespace Tito10047\Geocoder\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\GeocoderNotConfiguredException;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;

final class GeocoderNotConfiguredExceptionTest extends TestCase
{
    public function testIsCaughtAsAnUnavailableGeocoder(): void
    {
        $caught = null;
        try {
            throw new GeocoderNotConfiguredException('Meilisearch is not configured.');
        } catch (GeocoderUnavailableException $e) {
            // the fallbacks written for an outage keep working
            $caught = $e;
        }

        $this->assertInstanceOf(GeocoderNotConfiguredException::class, $caught);
        $this->assertSame('Meilisearch is not configured.', $caught->getMessage());
    }
}
