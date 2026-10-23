<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Name\NameCatalogInterface;
use Tito10047\Geocoder\Name\NameKind;

/**
 * Translates from a list: "AT|city|Wien|sk" => "Viedeň".
 */
final readonly class FakeNameCatalog implements NameCatalogInterface
{
    /**
     * @param array<string, string> $translations by "cc|kind|name|language"
     */
    public function __construct(
        private array $translations = [],
    ) {
    }

    public function translate(string $countryCode, NameKind $kind, string $name, string $language): ?string
    {
        return $this->translations[$countryCode.'|'.$kind->value.'|'.$name.'|'.$language] ?? null;
    }
}
