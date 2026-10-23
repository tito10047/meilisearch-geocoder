<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Tito10047\Geocoder\Name\NameCatalogInterface;
use Tito10047\Geocoder\Name\NameKind;

/**
 * Translates from a list: "SK|country|SK|en" => "Slovakia".
 */
final class FakeNameCatalog implements NameCatalogInterface
{
    /** @var array<string, string> by "cc|kind|name|language" */
    public array $translations = [];

    public function translate(string $countryCode, NameKind $kind, string $name, string $language): ?string
    {
        return $this->translations[$countryCode.'|'.$kind->value.'|'.$name.'|'.$language] ?? null;
    }
}
