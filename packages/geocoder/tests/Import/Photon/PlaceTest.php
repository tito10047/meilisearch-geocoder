<?php

namespace Tito10047\Geocoder\Tests\Import\Photon;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Tests\Fake\Places;

final class PlaceTest extends TestCase
{
    public function testOsmId(): void
    {
        $this->assertSame('N26037951', Places::create(objectType: 'N', objectId: 26037951)->osmId());
        $this->assertNull(Places::create(objectType: null, objectId: null)->osmId());
    }

    public function testName(): void
    {
        $this->assertSame('Viničky', Places::create()->name());
        $this->assertNull(Places::create(names: ['name:hu' => 'Szőlőske'])->name());
        $this->assertNull(Places::create(names: ['name' => ''])->name());
    }
}
