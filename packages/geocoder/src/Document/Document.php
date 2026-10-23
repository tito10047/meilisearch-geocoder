<?php

namespace Tito10047\Geocoder\Document;

use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * One document of the Meilisearch index: a place, a street or a point of interest.
 *
 * toArray() and fromArray() define the format stored in the index and in the NDJSON files; every
 * reader and writer of documents goes through them.
 */
final readonly class Document
{
    /**
     * @param string                $id          "R109166" (OSM type and id) or "s<street key>" for a street
     * @param string                $type        the OSM value: "city", "village", "hotel", "station", "street", …
     * @param string                $name        the local name
     * @param list<string>          $names       searchable: the local name first, then its distinct translations
     * @param list<string>          $cities      searchable: the town of its address and its distinct translations ([] when the address names no town)
     * @param array<string, string> $namesI18n   displayed: translations of the name differing from it, by language ("sk" => "Viedeň")
     * @param string                $city        the town the document is in, in its local name ("" when none)
     * @param string                $region      the region (state) the document is in ("" when unknown)
     * @param string                $countryCode ISO 3166-1 alpha-2, upper case
     * @param int                   $rank        the custom ranking rule "rank:desc": type of place × 1000 + importance × 999
     * @param string                $suburb      the part of the town the document is in, in its local name ("" when the address names none)
     * @param string                $county      the county (district) the document is in, in its local name ("" when unknown)
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public function __construct(
        public string $id,
        public DocumentKind $kind,
        public string $type,
        public string $name,
        public array $names,
        public array $cities,
        public array $namesI18n,
        public string $city,
        public string $region,
        public string $countryCode,
        public ?string $postcode,
        public Coordinates $coordinates,
        public int $rank,
        public string $suburb = '',
        public string $county = '',
    ) {
        if ('' === $id) {
            throw new InvalidArgumentException('The id of a document must not be empty.');
        }
        if ('' === $name) {
            throw new InvalidArgumentException(\sprintf('The name of the document "%s" must not be empty.', $id));
        }
        if (1 !== preg_match('/^[A-Z]{2}$/D', $countryCode)) {
            throw new InvalidArgumentException(\sprintf('The country code of the document "%s" must be two upper case letters, "%s" given.', $id, $countryCode));
        }
        if ($rank < 0) {
            throw new InvalidArgumentException(\sprintf('The rank of the document "%s" must not be negative, %d given.', $id, $rank));
        }
    }

    /**
     * The document as stored in Meilisearch; empty translations are left out.
     *
     * @return array{id: string, kind: string, type: string, name: string, names: list<string>, cities: list<string>, names_i18n?: array<string, string>, suburb: string, city: string, county: string, region: string, cc: string, postcode: string|null, _geo: array{lat: float, lng: float}, rank: int}
     */
    public function toArray(): array
    {
        $document = [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'type' => $this->type,
            'name' => $this->name,
            'names' => $this->names,
            'cities' => $this->cities,
            'suburb' => $this->suburb,
            'city' => $this->city,
            'county' => $this->county,
            'region' => $this->region,
            'cc' => $this->countryCode,
            'postcode' => $this->postcode,
            '_geo' => ['lat' => $this->coordinates->latitude, 'lng' => $this->coordinates->longitude],
            'rank' => $this->rank,
        ];
        if ([] !== $this->namesI18n) {
            $document['names_i18n'] = $this->namesI18n;
        }

        return $document;
    }

    /**
     * Reads a document stored in Meilisearch (a search hit) or in an NDJSON file.
     *
     * The fields that are only searched ("names", "cities") may be missing: an index does not
     * have to display them. So may the suburb and the county, which documents of an older format lack.
     *
     * @param array<array-key, mixed> $document
     *
     * @throws InvalidArgumentException when a required field is missing or has an invalid value
     */
    public static function fromArray(array $document): self
    {
        $geo = $document['_geo'] ?? null;
        if (!\is_array($geo)) {
            throw new InvalidArgumentException('The document has no "_geo" field.');
        }

        $kind = DocumentKind::tryFrom(self::string($document, 'kind'));
        if (null === $kind) {
            throw new InvalidArgumentException(\sprintf('The document has an unknown kind "%s".', self::string($document, 'kind')));
        }

        $postcode = $document['postcode'] ?? null;
        if (null !== $postcode && !\is_string($postcode)) {
            throw new InvalidArgumentException('The field "postcode" of the document must be a string or null.');
        }

        return new self(
            id: self::string($document, 'id'),
            kind: $kind,
            type: self::string($document, 'type'),
            name: self::string($document, 'name'),
            names: self::strings($document, 'names'),
            cities: self::strings($document, 'cities'),
            namesI18n: self::translations($document, 'names_i18n'),
            city: self::string($document, 'city', ''),
            region: self::string($document, 'region', ''),
            countryCode: self::string($document, 'cc'),
            postcode: $postcode,
            coordinates: new Coordinates(self::float($geo, 'lat'), self::float($geo, 'lng')),
            rank: self::int($document, 'rank'),
            suburb: self::string($document, 'suburb', ''),
            county: self::string($document, 'county', ''),
        );
    }

    /**
     * @param array<array-key, mixed> $document
     */
    private static function string(array $document, string $field, ?string $default = null): string
    {
        $value = $document[$field] ?? $default;
        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('The field "%s" of the document must be a string.', $field));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $document
     *
     * @return list<string>
     */
    private static function strings(array $document, string $field): array
    {
        $value = $document[$field] ?? [];
        if (!\is_array($value) || !array_is_list($value) || [] !== array_filter($value, static fn (mixed $item): bool => !\is_string($item))) {
            throw new InvalidArgumentException(\sprintf('The field "%s" of the document must be a list of strings.', $field));
        }

        /* @var list<string> $value */
        return $value;
    }

    /**
     * @param array<array-key, mixed> $document
     *
     * @return array<string, string>
     */
    private static function translations(array $document, string $field): array
    {
        $value = $document[$field] ?? [];
        if (!\is_array($value)) {
            throw new InvalidArgumentException(\sprintf('The field "%s" of the document must be a map of strings.', $field));
        }

        $translations = [];
        foreach ($value as $language => $translation) {
            if (!\is_string($language) || !\is_string($translation)) {
                throw new InvalidArgumentException(\sprintf('The field "%s" of the document must be a map of strings.', $field));
            }
            $translations[$language] = $translation;
        }

        return $translations;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function float(array $data, string $field): float
    {
        $value = $data[$field] ?? null;
        if (!\is_int($value) && !\is_float($value)) {
            throw new InvalidArgumentException(\sprintf('The field "%s" of the document must be a number.', $field));
        }

        return (float) $value;
    }

    /**
     * @param array<array-key, mixed> $document
     */
    private static function int(array $document, string $field): int
    {
        $value = $document[$field] ?? null;
        if (!\is_int($value)) {
            throw new InvalidArgumentException(\sprintf('The field "%s" of the document must be an integer.', $field));
        }

        return $value;
    }
}
