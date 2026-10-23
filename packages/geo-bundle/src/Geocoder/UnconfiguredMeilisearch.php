<?php

namespace Tito10047\GeoBundle\Geocoder;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\Exception\GeocoderNotConfiguredException;
use Tito10047\Geocoder\Index\IndexManagerInterface;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\IndexStatus;
use Tito10047\Geocoder\Search\AddressLookupInterface;
use Tito10047\Geocoder\Search\Backend\BackendQuery;
use Tito10047\Geocoder\Search\Backend\DocumentSourceInterface;
use Tito10047\Geocoder\Search\Backend\SearchBackendInterface;
use Tito10047\Geocoder\Search\GeocoderInterface;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;

/**
 * Stands for the geocoder and for Meilisearch while "geo.geocoder.meilisearch.url" resolves to an
 * empty value (an empty environment variable in an environment without a search engine).
 *
 * The services depending on the geocoder are still created; every search, lookup and operation on
 * the indexes fails with GeocoderNotConfiguredException: a GeocoderUnavailableException, so that
 * applications fall back and the commands fail as when Meilisearch is unreachable, which can still
 * be told apart. The geocoder and the address lookup
 * are replaced too, not only the search engine: they would otherwise answer some requests from the
 * database alone (a text too short to search, a reference without a house number) and hide the
 * missing configuration.
 */
final readonly class UnconfiguredMeilisearch implements GeocoderInterface, AddressLookupInterface, SearchBackendInterface, DocumentSourceInterface, IndexManagerInterface
{
    public const MESSAGE = 'Meilisearch is not configured: geo.geocoder.meilisearch.url is empty.';

    public function suggest(string $text, ?SearchOptions $options = null): array
    {
        throw $this->exception();
    }

    public function geocode(string $text, ?SearchOptions $options = null): ?Suggestion
    {
        throw $this->exception();
    }

    public function findByReference(string $reference, ?SearchOptions $options = null): ?Suggestion
    {
        throw $this->exception();
    }

    public function search(BackendQuery $query): array
    {
        throw $this->exception();
    }

    public function document(string $id): ?Document
    {
        throw $this->exception();
    }

    public function create(string $uid, IndexSettings $settings): void
    {
        throw $this->exception();
    }

    public function applySettings(string $uid, IndexSettings $settings): void
    {
        throw $this->exception();
    }

    public function documentWriter(string $uid): DocumentWriterInterface
    {
        // a writer accepting documents it cannot send would fail only when closed, after a whole dump
        throw $this->exception();
    }

    public function waitForTasks(string $uid): void
    {
        throw $this->exception();
    }

    public function compact(string $uid): void
    {
        throw $this->exception();
    }

    public function swap(string $uid, string $liveUid): void
    {
        throw $this->exception();
    }

    public function delete(string $uid): void
    {
        throw $this->exception();
    }

    public function exists(string $uid): bool
    {
        throw $this->exception();
    }

    public function fingerprint(string $uid): ?string
    {
        throw $this->exception();
    }

    public function version(): string
    {
        throw $this->exception();
    }

    public function indexUids(): array
    {
        throw $this->exception();
    }

    public function status(string $uid): IndexStatus
    {
        throw $this->exception();
    }

    public function snapshot(): void
    {
        throw $this->exception();
    }

    private function exception(): GeocoderNotConfiguredException
    {
        return new GeocoderNotConfiguredException(self::MESSAGE);
    }
}
