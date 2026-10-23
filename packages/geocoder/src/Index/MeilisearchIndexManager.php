<?php

namespace Tito10047\Geocoder\Index;

use Meilisearch\Contracts\Http;
use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Index\Exception\MeilisearchException;
use Tito10047\Geocoder\Index\Exception\TaskNotFoundException;
use Tito10047\Geocoder\Index\Meilisearch\MeilisearchApi;
use Tito10047\Geocoder\Index\Meilisearch\TaskWaiter;

/**
 * The index manager over Meilisearch (≥ 1.18: compaction and swap with rename; tuned on 1.35.1).
 *
 * Works with an API key restricted to the indexes of the geocoder ("indexes": ["geo*"]), which
 * Meilisearch does not show the tasks of no index: a swap is then confirmed from the creation
 * times of the indexes. Only snapshot() needs a key allowed on every index.
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

        $fingerprint = $this->api->indexCreatedAt($uid) ?? throw new MeilisearchException(\sprintf('Cannot swap the index %s in: it does not exist.', $uid));
        $liveFingerprint = $this->api->indexCreatedAt($liveUid);
        // a plain swap needs both indexes; the very first publication renames the build instead
        $rename = null === $liveFingerprint;
        $task = $this->api->swap($uid, $liveUid, $rename);

        try {
            $this->waiter->waitFor($task);
        } catch (TaskNotFoundException) {
            // a key restricted to some indexes never sees a swap, a task of no index: the indexes
            // tell its effect, their creation times having moved with them
            $this->waiter->waitForEffect(
                fn (): bool => $this->api->indexCreatedAt($liveUid) === $fingerprint && $this->api->indexCreatedAt($uid) === $liveFingerprint,
                $task,
                $rename ? \sprintf('the index %s renamed to %s', $uid, $liveUid) : \sprintf('the indexes %s and %s swapped', $uid, $liveUid),
            );
        }
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

    public function fingerprint(string $uid): ?string
    {
        MeilisearchApi::assertIndexUid($uid);

        return $this->api->indexCreatedAt($uid);
    }

    public function version(): string
    {
        return $this->api->version();
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
        $task = $this->api->createSnapshot();
        try {
            $this->waiter->waitFor($task);
        } catch (TaskNotFoundException $e) {
            // unlike a swap, nothing in the indexes tells when the snapshot is complete
            throw new MeilisearchException(\sprintf('Meilisearch created the snapshot %s, but this API key may not follow it: a snapshot holds every index, create it with a key allowed on every index ("indexes": ["*"]).', $task->describe()), 0, $e);
        }
    }
}
