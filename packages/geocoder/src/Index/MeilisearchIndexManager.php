<?php

namespace Tito10047\Geocoder\Index;

use Meilisearch\Contracts\Http;
use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Index\Meilisearch\MeilisearchApi;
use Tito10047\Geocoder\Index\Meilisearch\TaskWaiter;

/**
 * The index manager over Meilisearch (≥ 1.18: compaction and swap with rename; tuned on 1.35.1).
 */
final readonly class MeilisearchIndexManager implements IndexManagerInterface
{
    private const PRIMARY_KEY = 'id';

    /** Unfinished tasks read per request while waiting for an index. */
    private const TASK_PAGE = 1000;

    private MeilisearchApi $api;
    private TaskWaiter $waiter;

    /**
     * @param Http $http the HTTP layer of meilisearch-php: new \Meilisearch\Http\Client($url, $apiKey)
     */
    public function __construct(
        private Http $http,
        private IndexingOptions $options = new IndexingOptions(),
    ) {
        $this->api = new MeilisearchApi($http);
        $this->waiter = new TaskWaiter($this->api, $options);
    }

    public function create(string $uid, IndexSettings $settings): void
    {
        MeilisearchApi::assertIndexUid($uid);

        // both enqueued at once: Meilisearch runs the tasks of an index in order, so the settings
        // are in place before any document whatever happens next
        $creation = $this->api->createIndex($uid, self::PRIMARY_KEY);
        $update = $this->api->updateSettings($uid, $settings->toArray());
        $this->waiter->wait([$creation->uid, $update->uid]);
    }

    public function applySettings(string $uid, IndexSettings $settings): void
    {
        MeilisearchApi::assertIndexUid($uid);

        $this->waiter->waitFor($this->api->updateSettings($uid, $settings->toArray()));
    }

    public function documentWriter(string $uid): DocumentWriterInterface
    {
        return new MeilisearchDocumentWriter($this->http, $uid, $this->options);
    }

    public function waitForTasks(string $uid): void
    {
        MeilisearchApi::assertIndexUid($uid);

        // new tasks may be enqueued meanwhile: wait until a check finds none
        while ([] !== $uids = $this->api->unfinishedTaskUids($uid, self::TASK_PAGE)) {
            $this->waiter->wait($uids);
        }
    }

    public function compact(string $uid): void
    {
        MeilisearchApi::assertIndexUid($uid);

        $this->waiter->waitFor($this->api->compact($uid));
    }

    public function swap(string $uid, string $liveUid): void
    {
        MeilisearchApi::assertIndexUid($uid);
        MeilisearchApi::assertIndexUid($liveUid);
        if ($uid === $liveUid) {
            throw new InvalidArgumentException(\sprintf('Cannot swap the index %s with itself.', $uid));
        }

        // a plain swap needs both indexes; the very first publication renames the build instead
        $this->waiter->waitFor($this->api->swap($uid, $liveUid, !$this->api->indexExists($liveUid)));
    }

    public function delete(string $uid): void
    {
        MeilisearchApi::assertIndexUid($uid);

        $this->waiter->waitFor($this->api->deleteIndex($uid));
    }

    public function exists(string $uid): bool
    {
        MeilisearchApi::assertIndexUid($uid);

        return $this->api->indexExists($uid);
    }

    public function indexUids(): array
    {
        return $this->api->indexUids();
    }

    public function status(string $uid): IndexStatus
    {
        MeilisearchApi::assertIndexUid($uid);

        $stats = $this->api->indexStats($uid);
        try {
            $settingsHash = IndexSettings::fromArray($this->api->settings($uid))->hash();
        } catch (InvalidArgumentException) {
            $settingsHash = null;
        }

        return new IndexStatus(
            uid: $uid,
            documents: $stats['numberOfDocuments'],
            rawDocumentDbSize: $stats['rawDocumentDbSize'],
            databaseSize: $this->api->databaseSize(),
            indexing: $stats['isIndexing'],
            settingsHash: $settingsHash,
            meilisearchVersion: $this->api->version(),
        );
    }

    public function snapshot(): void
    {
        $this->waiter->waitFor($this->api->createSnapshot());
    }
}
