<?php

namespace Tito10047\Geocoder\Import;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * What an import built: a new index and new tables, and the dumps they were made of.
 *
 * Nothing of it is live until it is published. The same is stored in the metadata table of the
 * build, from which it can be read again (e.g. to publish later, or once it is live).
 */
final readonly class Build
{
    /**
     * @param string             $indexUid      the index of the build ("geo_20261023120000"); after its publication its documents are in the live index
     * @param TableNames         $tables        the tables holding the build (those of the build, or the live ones once published)
     * @param \DateTimeImmutable $startedAt     when the import started; it names the build
     * @param string             $settingsHash  IndexSettings::hash() of the settings of the index
     * @param int                $schemaVersion PdoSchema::VERSION of the tables
     * @param list<DumpSummary>  $dumps         the dumps, in the order they were imported
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public function __construct(
        public string $indexUid,
        public TableNames $tables,
        public \DateTimeImmutable $startedAt,
        public string $settingsHash,
        public int $schemaVersion,
        public array $dumps,
    ) {
        if ('' === $indexUid) {
            throw new InvalidArgumentException('The index uid of a build must not be empty.');
        }
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $settingsHash)) {
            throw new InvalidArgumentException(\sprintf('The settings hash of a build must be a SHA-256 in hexadecimal, "%s" given.', $settingsHash));
        }
        if ($schemaVersion < 1) {
            throw new InvalidArgumentException(\sprintf('The schema version of a build must be positive, %d given.', $schemaVersion));
        }
        if ([] === $dumps || !array_is_list($dumps)) {
            throw new InvalidArgumentException('A build is made of a non-empty list of dumps.');
        }
        foreach ($dumps as $dump) {
            if (!$dump instanceof DumpSummary) {
                throw new InvalidArgumentException(\sprintf('The dumps of a build must be %s, %s given.', DumpSummary::class, get_debug_type($dump)));
            }
        }
    }

    /**
     * The same build in other tables: after its publication, or copied into another database.
     */
    public function withTables(TableNames $tables): self
    {
        return new self($this->indexUid, $tables, $this->startedAt, $this->settingsHash, $this->schemaVersion, $this->dumps);
    }

    /**
     * What all the dumps produced.
     */
    public function counts(): ImportCounts
    {
        $counts = new ImportCounts();
        foreach ($this->dumps as $dump) {
            $counts = $counts->add($dump->counts);
        }

        return $counts;
    }

    /**
     * The age of the oldest data of the build: the data timestamp of its oldest dump.
     */
    public function oldestDataTimestamp(): \DateTimeImmutable
    {
        $oldest = $this->dumps[0]->dataTimestamp;
        foreach ($this->dumps as $dump) {
            if ($dump->dataTimestamp < $oldest) {
                $oldest = $dump->dataTimestamp;
            }
        }

        return $oldest;
    }

    /**
     * The age of the data of each country: the data timestamp of its dump (the oldest one when
     * several dumps hold the country).
     *
     * @return array<string, \DateTimeImmutable> by ISO 3166-1 alpha-2 code, sorted
     */
    public function dataTimestamps(): array
    {
        $timestamps = [];
        foreach ($this->dumps as $dump) {
            foreach ($dump->countryCodes as $countryCode) {
                if (!isset($timestamps[$countryCode]) || $dump->dataTimestamp < $timestamps[$countryCode]) {
                    $timestamps[$countryCode] = $dump->dataTimestamp;
                }
            }
        }
        ksort($timestamps);

        return $timestamps;
    }
}
