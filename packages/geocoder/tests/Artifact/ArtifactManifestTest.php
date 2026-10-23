<?php

namespace Tito10047\Geocoder\Tests\Artifact;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Artifact\ArtifactFile;
use Tito10047\Geocoder\Artifact\ArtifactManifest;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\ImportCounts;

final class ArtifactManifestTest extends TestCase
{
    public function testWritesAndReadsTheJson(): void
    {
        $manifest = self::manifest();

        $json = $manifest->toJson();

        $this->assertEquals($manifest, ArtifactManifest::fromJson($json));
        $this->assertSame(<<<JSON
            {
                "format": 1,
                "meilisearch_version": "1.35.1",
                "settings_hash": "{$manifest->settingsHash}",
                "schema_version": 1,
                "index_uid": "geo_20261023120000",
                "live_uid": "geo",
                "started_at": "2026-10-23T12:00:00+00:00",
                "created_at": "2026-10-23T14:00:00+00:00",
                "documents": 21,
                "counts": {
                    "places": 4,
                    "streets": 13,
                    "pois": 4,
                    "house_numbers": 134
                },
                "data_timestamps": {
                    "SK": "2026-10-17T23:02:06+00:00"
                },
                "files": {
                    "snapshot": {
                        "name": "meilisearch.snapshot",
                        "size": 10,
                        "sha256": "{$manifest->snapshot->sha256}"
                    },
                    "database": {
                        "name": "geocoder.sqlite",
                        "size": 20,
                        "sha256": "{$manifest->database->sha256}"
                    }
                }
            }

            JSON, $json);
    }

    public function testWritesNoDataTimestampsAsAnObject(): void
    {
        $manifest = self::manifest([]);

        $this->assertStringContainsString('"data_timestamps": {}', $manifest->toJson());
        $this->assertEquals($manifest, ArtifactManifest::fromJson($manifest->toJson()));
    }

    #[DataProvider('invalidJson')]
    public function testRejectsAnInvalidManifest(string $json, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        ArtifactManifest::fromJson($json);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidJson(): iterable
    {
        $valid = json_decode(self::manifest()->toJson(), true, 8, \JSON_THROW_ON_ERROR);
        \assert(\is_array($valid));
        $edit = static fn (array $changes): string => json_encode(array_replace_recursive($valid, $changes), \JSON_THROW_ON_ERROR);

        yield 'not JSON' => ['{', 'not valid JSON'];
        yield 'not an object' => ['"manifest"', 'not a JSON object'];
        yield 'another format' => [$edit(['format' => 2]), 'the format 2'];
        yield 'no version' => [$edit(['meilisearch_version' => null]), '"meilisearch_version"'];
        yield 'a count not an integer' => [$edit(['counts' => ['places' => '4']]), '"places"'];
        yield 'a timestamp' => [$edit(['created_at' => 'today']), '"today" in the manifest is not a timestamp'];
        yield 'a data timestamp not a string' => [$edit(['data_timestamps' => ['SK' => 1]]), 'must be strings'];
        yield 'a country code' => [$edit(['data_timestamps' => ['sk' => '2026-10-17T23:02:06+00:00']]), 'ISO 3166-1'];
        yield 'no files' => [$edit(['files' => null]), '"files"'];
        yield 'a file name with a path' => [$edit(['files' => ['database' => ['name' => '../geocoder.sqlite']]]), 'plain file name'];
        yield 'one file twice' => [$edit(['files' => ['database' => ['name' => 'meilisearch.snapshot']]]), 'two files'];
        yield 'the settings hash' => [$edit(['settings_hash' => 'x']), 'settings hash'];
        yield 'negative documents' => [$edit(['documents' => -1]), 'not negative'];
        yield 'an empty uid' => [$edit(['live_uid' => '']), 'must not be empty'];
    }

    /**
     * @param array<string, \DateTimeImmutable> $dataTimestamps
     */
    private static function manifest(array $dataTimestamps = ['SK' => new \DateTimeImmutable('2026-10-17T23:02:06+00:00')]): ArtifactManifest
    {
        return new ArtifactManifest(
            meilisearchVersion: '1.35.1',
            settingsHash: hash('sha256', 'settings'),
            schemaVersion: 1,
            indexUid: 'geo_20261023120000',
            liveUid: 'geo',
            startedAt: new \DateTimeImmutable('2026-10-23T12:00:00+00:00'),
            createdAt: new \DateTimeImmutable('2026-10-23T16:00:00+02:00'),
            documents: 21,
            counts: new ImportCounts(4, 13, 4, 134),
            dataTimestamps: $dataTimestamps,
            snapshot: new ArtifactFile('meilisearch.snapshot', 10, hash('sha256', 'snapshot')),
            database: new ArtifactFile('geocoder.sqlite', 20, hash('sha256', 'database')),
        );
    }
}
