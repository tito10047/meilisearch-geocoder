<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\HouseNumber\HouseNumberStoreInterface;
use Tito10047\Geocoder\StreetKey;

/**
 * Finds the house numbers given to it whose number starts with the typed one.
 */
final class FakeHouseNumberStore implements HouseNumberStoreInterface
{
    /**
     * @var list<array{int, string, int}> the street key, the number and the limit of every lookup
     */
    public array $lookups = [];

    /**
     * @param list<HouseNumber> $houseNumbers
     */
    public function __construct(
        private readonly array $houseNumbers = [],
    ) {
    }

    public function find(StreetKey $streetKey, string $number, int $limit): array
    {
        $this->lookups[] = [$streetKey->value, $number, $limit];
        $found = array_filter($this->houseNumbers, static fn (HouseNumber $houseNumber): bool => $houseNumber->streetKey->value === $streetKey->value && str_starts_with($houseNumber->number, $number));

        return \array_slice(array_values($found), 0, $limit);
    }
}
