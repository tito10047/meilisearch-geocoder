<?php

namespace Tito10047\Geocoder\Tests\Photon;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Photon\Feature;
use Tito10047\Geocoder\Photon\FeatureCollection;
use Tito10047\Geocoder\Photon\Layer;

final class FeatureCollectionTest extends TestCase
{
    public function testSerializesAsGeoJson(): void
    {
        $feature = new Feature(new Coordinates(48.1, 17.1), Layer::City, 'SK', name: 'Bratislava');
        $collection = new FeatureCollection([$feature]);

        $this->assertCount(1, $collection);
        $this->assertSame(
            '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{"type":"city","name":"Bratislava","countrycode":"SK"},"geometry":{"type":"Point","coordinates":[17.1,48.1]}}]}',
            json_encode($collection, \JSON_UNESCAPED_UNICODE),
        );
        $this->assertSame('{"type":"FeatureCollection","features":[]}', json_encode(new FeatureCollection()));
    }

    public function testRejectsWhatIsNoFeature(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /* @phpstan-ignore argument.type (the invalid feature is the point of the test) */
        new FeatureCollection(['x']);
    }
}
