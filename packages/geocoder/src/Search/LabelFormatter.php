<?php

namespace Tito10047\Geocoder\Search;

use Tito10047\Geocoder\Name\NameCatalogInterface;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Search\Text\NameNormalizer;
use Tito10047\Geocoder\Search\Text\NameNormalizerInterface;

/**
 * "Name, town, country": "Stephansplatz, Viedeň, Rakúsko", "Hlavná 12, Košice, Slovensko".
 *
 * The town is replaced by the region when there is none or when it is the suggestion itself (a
 * village is its own town): "Bystré, Prešovský kraj, Slovensko". A part equal to an earlier one
 * (without diacritics and case) is left out. The country is named in the requested language by
 * the catalog; without a language or a translation, by its ISO code ("Wien, AT"), as the local
 * name of a country is not stored.
 */
final readonly class LabelFormatter implements LabelFormatterInterface
{
    private const SEPARATOR = ', ';

    public function __construct(
        private NameCatalogInterface $catalog,
        private NameNormalizerInterface $normalizer = new NameNormalizer(),
    ) {
    }

    public function format(Suggestion $suggestion, ?string $language): string
    {
        $parts = [];
        $this->add($parts, $suggestion->name);
        foreach ([$suggestion->city, $suggestion->region] as $area) {
            if (null !== $area && $this->add($parts, $area)) {
                break;
            }
        }
        $this->add($parts, $this->country($suggestion->countryCode, $language));

        return implode(self::SEPARATOR, $parts);
    }

    /**
     * Adds the part unless it is empty or equal to one already there.
     *
     * @param array<string, string> $parts the parts by their normalized form
     *
     * @return bool whether the part was added
     */
    private function add(array &$parts, string $part): bool
    {
        $part = trim($part);
        $key = $this->normalizer->normalize($part);
        if ('' === $key || isset($parts[$key])) {
            return false;
        }
        $parts[$key] = $part;

        return true;
    }

    private function country(string $countryCode, ?string $language): string
    {
        if (null === $language) {
            return $countryCode;
        }

        return $this->catalog->translate($countryCode, NameKind::Country, $countryCode, $language) ?? $countryCode;
    }
}
