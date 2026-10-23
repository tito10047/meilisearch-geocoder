<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\InvalidDumpException;

/**
 * Reads the JSON Lines of a Photon dump, format version 0.1.
 *
 * @see https://github.com/komoot/photon/blob/master/docs/json-dump-format-0.1.0.md
 */
final readonly class PhotonDumpReader implements DumpReaderInterface
{
    private const HEADER_TYPE = 'NominatimDumpFile';
    private const COUNTRY_INFO_TYPE = 'CountryInfo';
    private const PLACE_TYPE = 'Place';
    /** The versions of the format this reader understands: 0.1.x. */
    private const SUPPORTED_VERSION = '/^0\.1(\.\d+)*$/D';
    /** Deep enough for a record; the geometries are cut out before decoding. */
    private const JSON_DEPTH = 64;
    private const GEOMETRY_KEY = '"geometry":{';

    public function __construct(
        private LineSourceInterface $lines = new AutoDetectingLineSource(),
    ) {
    }

    public function read(string $dump): iterable
    {
        return $this->records($dump, $this->lines->lines($dump));
    }

    /**
     * @param iterable<int, string> $lines
     *
     * @return \Generator<int, DumpHeader|CountryInfo|Place>
     */
    private function records(string $dump, iterable $lines): \Generator
    {
        $header = false;
        foreach ($lines as $number => $line) {
            $record = $this->decode($dump, $number, $line);
            $type = $record['type'] ?? null;
            $content = $record['content'] ?? null;

            if (!$header) {
                if (self::HEADER_TYPE !== $type) {
                    throw new InvalidDumpException($dump, $number, \sprintf('a Photon dump starts with a "%s" header, this is not one.', self::HEADER_TYPE));
                }
                $header = true;
                yield $this->header($dump, $number, $content);
                continue;
            }

            if (self::PLACE_TYPE === $type) {
                foreach ($this->list($dump, $number, $content) as $place) {
                    yield $this->place($dump, $number, $place);
                }
            } elseif (self::COUNTRY_INFO_TYPE === $type) {
                yield $this->countryInfo($dump, $number, $content);
            } elseif (self::HEADER_TYPE === $type) {
                throw new InvalidDumpException($dump, $number, 'a dump has only one header.');
            }
        }

        if (!$header) {
            throw new InvalidDumpException($dump, 0, 'it is empty, a Photon dump starts with a header.');
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(string $dump, int $number, string $line): array
    {
        try {
            $record = json_decode($this->withoutGeometries($line), true, self::JSON_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidDumpException($dump, $number, \sprintf('invalid JSON (%s).', $exception->getMessage()), $exception);
        }
        if (!\is_array($record)) {
            throw new InvalidDumpException($dump, $number, 'a record must be a JSON object.');
        }

        return $record;
    }

    /**
     * The line without its "geometry" objects, which are most of a dump and are never used: the
     * boundary of a country is megabytes of coordinates, ten times that once decoded.
     *
     * A key "geometry" followed by an object cannot be the content of a JSON string (its quotes
     * would be escaped), and a GeoJSON geometry holds no string with a brace: the object ends at
     * the brace that balances its first one. A line that does not look so is left whole.
     */
    private function withoutGeometries(string $line): string
    {
        $offset = 0;
        while (false !== ($start = strpos($line, self::GEOMETRY_KEY, $offset))) {
            $length = \strlen($line);
            $position = $start + \strlen(self::GEOMETRY_KEY) - 1;
            $depth = 0;
            while ($position < $length) {
                $depth += '{' === $line[$position] ? 1 : -1;
                if (0 === $depth) {
                    break;
                }
                $position += 1 + strcspn($line, '{}', $position + 1);
            }
            if (0 !== $depth) {
                return $line;
            }

            // the geometry and the comma before it ("…,"geometry":{…}}") or after it ("{"geometry":{…},…")
            $end = $position + 1;
            if ($start > 0 && ',' === $line[$start - 1]) {
                --$start;
            } elseif (',' === ($line[$end] ?? '')) {
                ++$end;
            }
            $line = substr($line, 0, $start).substr($line, $end);
            $offset = $start;
        }

        return $line;
    }

    private function header(string $dump, int $number, mixed $content): DumpHeader
    {
        if (!\is_array($content)) {
            throw new InvalidDumpException($dump, $number, 'the header has no content.');
        }

        $version = $content['version'] ?? null;
        if (!\is_string($version) || 1 !== preg_match(self::SUPPORTED_VERSION, $version)) {
            throw new InvalidDumpException($dump, $number, \sprintf('the dump format version "%s" is not supported, only 0.1.x is.', \is_scalar($version) ? $version : get_debug_type($version)));
        }

        $features = $content['features'] ?? [];
        if (\is_array($features) && true === ($features['has_addresslines'] ?? false)) {
            throw new InvalidDumpException($dump, $number, 'dumps with address lines ("has_addresslines") are not supported: the address must be in each place.');
        }

        $timestamp = $content['data_timestamp'] ?? null;
        if (!\is_string($timestamp) || '' === $timestamp) {
            throw new InvalidDumpException($dump, $number, 'the header has no "data_timestamp".');
        }
        try {
            $dataTimestamp = new \DateTimeImmutable($timestamp);
        } catch (\Exception $exception) {
            throw new InvalidDumpException($dump, $number, \sprintf('the "data_timestamp" "%s" is not a date.', $timestamp), $exception);
        }

        $generator = $content['generator'] ?? '';

        return new DumpHeader(
            $version,
            \is_string($generator) ? $generator : '',
            $dataTimestamp,
            \is_array($features) && true === ($features['sorted_by_country'] ?? false),
        );
    }

    private function countryInfo(string $dump, int $number, mixed $content): CountryInfo
    {
        $countries = [];
        foreach ($this->list($dump, $number, $content) as $country) {
            $code = $country['country_code'] ?? null;
            // a code that is not ISO 3166-1 alpha-2 cannot name anything in the documents
            if (!\is_string($code) || 1 !== preg_match('/^[A-Za-z]{2}$/D', $code)) {
                continue;
            }
            $countries[] = new Country(strtoupper($code), $this->strings($country['name'] ?? null));
        }

        return new CountryInfo($countries);
    }

    /**
     * @param array<array-key, mixed> $place
     */
    private function place(string $dump, int $number, array $place): Place
    {
        $objectType = $place['object_type'] ?? null;
        $objectId = $place['object_id'] ?? null;
        $osmKey = $place['osm_key'] ?? null;
        $osmValue = $place['osm_value'] ?? null;
        $centroid = $place['centroid'] ?? null;

        // postcode areas made up by Nominatim are no OSM object
        if (null !== $objectType && (!\is_string($objectType) || '' === $objectType)) {
            throw new InvalidDumpException($dump, $number, 'the "object_type" of the place must be a string.');
        }
        if (\is_string($objectId) && 1 === preg_match('/^\d+$/D', $objectId)) {
            $objectId = (int) $objectId;
        }
        if (null !== $objectId && !\is_int($objectId)) {
            throw new InvalidDumpException($dump, $number, 'the "object_id" of the place must be a number.');
        }
        if ((null === $objectType) !== (null === $objectId)) {
            throw new InvalidDumpException($dump, $number, 'the place has an "object_type" without an "object_id" or the reverse.');
        }
        if (!\is_string($osmKey) || !\is_string($osmValue)) {
            throw new InvalidDumpException($dump, $number, 'the place has no "osm_key" and "osm_value".');
        }
        if (!\is_array($centroid) || !isset($centroid[0], $centroid[1])
            || (!\is_float($centroid[0]) && !\is_int($centroid[0])) || (!\is_float($centroid[1]) && !\is_int($centroid[1]))) {
            throw new InvalidDumpException($dump, $number, 'the place has no "centroid" [longitude, latitude].');
        }
        try {
            $coordinates = new Coordinates((float) $centroid[1], (float) $centroid[0]);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidDumpException($dump, $number, $exception->getMessage(), $exception);
        }

        $importance = $place['importance'] ?? 0.0;
        $houseNumber = $place['housenumber'] ?? null;
        $postcode = $place['postcode'] ?? null;
        $countryCode = $place['country_code'] ?? '';

        return new Place(
            $objectType,
            $objectId,
            $osmKey,
            $osmValue,
            \is_float($importance) || \is_int($importance) ? (float) $importance : 0.0,
            $this->strings($place['name'] ?? null),
            \is_string($houseNumber) ? $houseNumber : null,
            $this->strings($place['address'] ?? null),
            \is_string($postcode) ? $postcode : null,
            \is_string($countryCode) ? strtoupper($countryCode) : '',
            $coordinates,
            $this->strings($place['extra'] ?? null),
        );
    }

    /**
     * The objects of a record's content: a JSON array of objects.
     *
     * @return list<array<array-key, mixed>>
     */
    private function list(string $dump, int $number, mixed $content): array
    {
        if (!\is_array($content) || !array_is_list($content)) {
            throw new InvalidDumpException($dump, $number, 'the content of the record must be a list.');
        }
        foreach ($content as $item) {
            if (!\is_array($item)) {
                throw new InvalidDumpException($dump, $number, 'the content of the record must be a list of objects.');
            }
        }

        return $content;
    }

    /**
     * The string values of a JSON object of tags; other values are dropped.
     *
     * @return array<string, string>
     */
    private function strings(mixed $tags): array
    {
        if (!\is_array($tags)) {
            return [];
        }

        // OSM keys are never numeric, so PHP does not turn them into integers
        return array_filter($tags, \is_string(...));
    }
}
