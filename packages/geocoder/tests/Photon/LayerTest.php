<?php

namespace Tito10047\Geocoder\Tests\Photon;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Photon\Layer;
use Tito10047\Geocoder\Search\Kind;

final class LayerTest extends TestCase
{
    public function testTheLayersOfPhoton(): void
    {
        $this->assertSame('house, street, locality, district, city, county, state, country, other', Layer::names());
    }

    public function testKinds(): void
    {
        $this->assertSame([Kind::Address, Kind::Poi], Layer::House->kinds());
        $this->assertSame([Kind::Street], Layer::Street->kinds());
        $this->assertSame([Kind::Place], Layer::District->kinds());
        $this->assertSame([], Layer::Country->kinds());
        $this->assertSame([], Layer::Other->kinds());
    }
}
