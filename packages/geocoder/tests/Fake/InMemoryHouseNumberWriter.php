<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\HouseNumber\HouseNumberWriterInterface;

final class InMemoryHouseNumberWriter implements HouseNumberWriterInterface
{
    /** @var list<HouseNumber> */
    public array $houseNumbers = [];
    public bool $closed = false;

    public function write(HouseNumber $houseNumber): void
    {
        $this->houseNumbers[] = $houseNumber;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
