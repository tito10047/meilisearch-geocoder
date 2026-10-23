<?php

namespace Tito10047\Geocoder\Artifact;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\ImportCounts;

/**
 * The manifest.json of an artifact: what it holds and what it needs to be installed.
 */
final readonly class ArtifactManifest
{
    /** The version of the format of the artifact: an installer reads only its own. */
    public const FORMAT = 1;

    private const TIMESTAMP_FORMAT = \DateTimeInterface::ATOM;

    /**
     * @param string                            $meilisearchVersion the version of the instance that made the snapshot; only the same version loads it
     * @param string                            $settingsHash       IndexSettings::hash() of the index
     * @param int                               $schemaVersion      PdoSchema::VERSION of the tables
     * @param string                            $indexUid           the build ("geo_20261023120000")
     * @param string                            $liveUid            the index in the snapshot ("geo"): the application must search it
     * @param int                               $documents          the documents of the index
     * @param array<string, \DateTimeImmutable> $dataTimestamps     the age of the data by country
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public function __construct(
        public string $meilisearchVersion,
        public string $settingsHash,
        public int $schemaVersion,
        public string $indexUid,
        public string $liveUid,
        public \DateTimeImmutable $startedAt,
        public \DateTimeImmutable $createdAt,
        public int $documents,
        public ImportCounts $counts,
        public array $dataTimestamps,
        public ArtifactFile $snapshot,
        public ArtifactFile $database,
        public int $format = self::FORMAT,
    ) {
        if (\in_array('', [$meilisearchVersion, $indexUid, $liveUid], true)) {
            throw new InvalidArgumentException('The Meilisearch version and the index uids of an artifact must not be empty.');
        }
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $settingsHash)) {
            throw new InvalidArgumentException('The settings hash of an artifact must be a SHA-256 in hexadecimal.');
        }
        if ($schemaVersion < 1 || $format < 1 || $documents < 0) {
            throw new InvalidArgumentException('The versions of an artifact must be positive and its number of documents not negative.');
        }
        foreach ($dataTimestamps as $countryCode => $timestamp) {
            if (!\is_string($countryCode) || 1 !== preg_match('/^[A-Z]{2}$/D', $countryCode) || !$timestamp instanceof \DateTimeImmutable) {
                throw new InvalidArgumentException('The data timestamps of an artifact must be timestamps by ISO 3166-1 alpha-2 code.');
            }
        }
        if ($snapshot->name === $database->name) {
            throw new InvalidArgumentException('The snapshot and the database of an artifact must be two files.');
        }
    }

    /**
     * @throws InvalidArgumentException when the JSON is not a manifest
     */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException(\sprintf('The manifest is not valid JSON: %s', $exception->getMessage()), 0, $exception);
        }
        if (!\is_array($data)) {
            throw new InvalidArgumentException('The manifest is not a JSON object.');
        }
        $format = self::int($data, 'format');
        if (self::FORMAT !== $format) {
            throw new InvalidArgumentException(\sprintf('The artifact has the format %d; this version of the library reads the format %d.', $format, self::FORMAT));
        }

        $counts = self::array($data, 'counts');
        $timestamps = [];
        foreach (self::array($data, 'data_timestamps') as $countryCode => $timestamp) {
            if (!\is_string($timestamp)) {
                throw new InvalidArgumentException('The data timestamps of the manifest must be strings.');
            }
            $timestamps[(string) $countryCode] = self::parseTimestamp($timestamp);
        }
        $files = self::array($data, 'files');

        return new self(
            meilisearchVersion: self::string($data, 'meilisearch_version'),
            settingsHash: self::string($data, 'settings_hash'),
            schemaVersion: self::int($data, 'schema_version'),
            indexUid: self::string($data, 'index_uid'),
            liveUid: self::string($data, 'live_uid'),
            startedAt: self::parseTimestamp(self::string($data, 'started_at')),
            createdAt: self::parseTimestamp(self::string($data, 'created_at')),
            documents: self::int($data, 'documents'),
            counts: new ImportCounts(self::int($counts, 'places'), self::int($counts, 'streets'), self::int($counts, 'pois'), self::int($counts, 'house_numbers')),
            dataTimestamps: $timestamps,
            snapshot: self::file(self::array($files, 'snapshot')),
            database: self::file(self::array($files, 'database')),
            format: $format,
        );
    }

    public function toJson(): string
    {
        $timestamps = [];
        foreach ($this->dataTimestamps as $countryCode => $timestamp) {
            $timestamps[$countryCode] = $this->timestamp($timestamp);
        }

        return json_encode([
            'format' => $this->format,
            'meilisearch_version' => $this->meilisearchVersion,
            'settings_hash' => $this->settingsHash,
            'schema_version' => $this->schemaVersion,
            'index_uid' => $this->indexUid,
            'live_uid' => $this->liveUid,
            'started_at' => $this->timestamp($this->startedAt),
            'created_at' => $this->timestamp($this->createdAt),
            'documents' => $this->documents,
            'counts' => [
                'places' => $this->counts->places,
                'streets' => $this->counts->streets,
                'pois' => $this->counts->pois,
                'house_numbers' => $this->counts->houseNumbers,
            ],
            'data_timestamps' => (object) $timestamps,
            'files' => [
                'snapshot' => ['name' => $this->snapshot->name, 'size' => $this->snapshot->size, 'sha256' => $this->snapshot->sha256],
                'database' => ['name' => $this->database->name, 'size' => $this->database->size, 'sha256' => $this->database->sha256],
            ],
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @param array<array-key, mixed> $file
     */
    private static function file(array $file): ArtifactFile
    {
        return new ArtifactFile(self::string($file, 'name'), self::int($file, 'size'), self::string($file, 'sha256'));
    }

    private function timestamp(\DateTimeImmutable $timestamp): string
    {
        return $timestamp->setTimezone(new \DateTimeZone('UTC'))->format(self::TIMESTAMP_FORMAT);
    }

    private static function parseTimestamp(string $timestamp): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('!'.self::TIMESTAMP_FORMAT, $timestamp);
        if (false === $parsed) {
            throw new InvalidArgumentException(\sprintf('"%s" in the manifest is not a timestamp.', $timestamp));
        }

        return $parsed;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function array(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!\is_array($value)) {
            throw new InvalidArgumentException(\sprintf('The manifest misses the object "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('The manifest misses the string "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!\is_int($value)) {
            throw new InvalidArgumentException(\sprintf('The manifest misses the integer "%s".', $key));
        }

        return $value;
    }
}
