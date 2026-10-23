<?php

namespace Tito10047\Geocoder\Index\Meilisearch;

use Meilisearch\Contracts\Http;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Endpoints\Snapshots;
use Meilisearch\Endpoints\Stats;
use Meilisearch\Endpoints\Tasks;
use Meilisearch\Endpoints\Version;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Exceptions\ExceptionInterface as MeilisearchExceptionInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Index\Exception\MeilisearchException;
use Tito10047\Geocoder\Index\Task;

/**
 * The Meilisearch endpoints the index needs, typed, behind the client of meilisearch-php.
 *
 * Every failure of the client (HTTP, API error, malformed answer) becomes a MeilisearchException
 * here, so no vendor exception leaks. The client has no method for the compaction, its swap
 * cannot rename and it wraps the settings it reads in objects: those go through its raw HTTP layer.
 *
 * @internal
 */
final readonly class MeilisearchApi
{
    public function __construct(private Http $http)
    {
    }

    /**
     * @throws InvalidArgumentException when the uid is not a valid Meilisearch index uid
     */
    public static function assertIndexUid(string $uid): void
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,400}$/D', $uid)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a valid index uid: use letters, digits, "-" and "_", at most 400 characters.', $uid));
        }
    }

    public function createIndex(string $uid, string $primaryKey): Task
    {
        return $this->task(fn (): mixed => (new Indexes($this->http))->create($uid, ['primaryKey' => $primaryKey]), 'create the index '.$uid);
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function updateSettings(string $uid, array $settings): Task
    {
        return $this->task(fn (): mixed => (new Indexes($this->http, $uid))->updateSettings($settings), 'update the settings of the index '.$uid);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function settings(string $uid): array
    {
        // raw: the client wraps parts of the settings in objects
        return $this->array(fn (): mixed => $this->http->get('/indexes/'.$uid.'/settings'), 'read the settings of the index '.$uid);
    }

    public function addDocumentsNdjson(string $uid, string $ndjson, string $primaryKey): Task
    {
        return $this->task(fn (): mixed => (new Indexes($this->http, $uid))->addDocumentsNdjson($ndjson, $primaryKey), 'add documents to the index '.$uid);
    }

    public function deleteIndex(string $uid): Task
    {
        return $this->task(fn (): mixed => (new Indexes($this->http, $uid))->delete(), 'delete the index '.$uid);
    }

    public function compact(string $uid): Task
    {
        return $this->task(fn (): mixed => $this->http->post('/indexes/'.$uid.'/compact'), 'compact the index '.$uid);
    }

    /**
     * @param bool $rename rename $uid to $liveUid, which must not exist (Meilisearch ≥ 1.18)
     */
    public function swap(string $uid, string $liveUid, bool $rename): Task
    {
        $swap = ['indexes' => [$uid, $liveUid]];
        if ($rename) {
            $swap['rename'] = true;
        }

        return $this->task(fn (): mixed => $this->http->post('/swap-indexes', [$swap]), \sprintf('swap the indexes %s and %s', $uid, $liveUid));
    }

    public function createSnapshot(): Task
    {
        return $this->task(fn (): mixed => (new Snapshots($this->http))->create(), 'create a snapshot');
    }

    /**
     * The tasks of the given uids, in any order.
     *
     * @param non-empty-list<int> $uids
     *
     * @return list<Task>
     */
    public function tasks(array $uids): array
    {
        return $this->taskList(['uids' => $uids, 'limit' => \count($uids)], 'read the tasks '.implode(', ', $uids));
    }

    /**
     * The uids of the enqueued and processing tasks of an index, at most $limit of them.
     *
     * @return list<int>
     */
    public function unfinishedTaskUids(string $uid, int $limit): array
    {
        $tasks = $this->taskList(['indexUids' => [$uid], 'statuses' => ['enqueued', 'processing'], 'limit' => $limit], 'read the tasks of the index '.$uid);

        return array_map(static fn (Task $task): int => $task->uid, $tasks);
    }

    public function indexExists(string $uid): bool
    {
        return null !== $this->indexInfo($uid);
    }

    /**
     * When the index was created, to the nanosecond ("2026-10-23T14:27:21.027114682Z"): it moves
     * with the index on a swap or a rename, so it tells the indexes apart.
     *
     * @return string|null null when the index does not exist
     */
    public function indexCreatedAt(string $uid): ?string
    {
        $info = $this->indexInfo($uid);
        if (null === $info) {
            return null;
        }
        $createdAt = $info['createdAt'] ?? null;
        if (!\is_string($createdAt) || '' === $createdAt) {
            throw new MeilisearchException(\sprintf('Meilisearch returned the index %s without its creation time.', $uid));
        }

        return $createdAt;
    }

    /**
     * @return array<array-key, mixed>|null null when the index does not exist
     */
    private function indexInfo(string $uid): ?array
    {
        try {
            $info = (new Indexes($this->http, $uid))->fetchRawInfo();
        } catch (ApiException $e) {
            if ('index_not_found' === $e->errorCode) {
                return null;
            }

            throw new MeilisearchException(\sprintf('Cannot read the index %s from Meilisearch: %s', $uid, $e->getMessage()), 0, $e);
        } catch (MeilisearchExceptionInterface|ClientExceptionInterface|\JsonException|\TypeError $e) {
            throw new MeilisearchException(\sprintf('Cannot read the index %s from Meilisearch: %s', $uid, $e->getMessage()), 0, $e);
        }
        if (null === $info) {
            throw new MeilisearchException(\sprintf('Meilisearch returned an empty answer when trying to read the index %s.', $uid));
        }

        return $info;
    }

    /**
     * @return list<string>
     */
    public function indexUids(): array
    {
        $uids = [];
        $limit = 100;
        for ($offset = 0;; $offset += $limit) {
            $page = $this->array(fn (): mixed => (new Indexes($this->http))->allRaw(['offset' => $offset, 'limit' => $limit]), 'list the indexes');
            $results = $page['results'] ?? null;
            $total = $page['total'] ?? null;
            if (!\is_array($results) || !\is_int($total)) {
                throw new MeilisearchException('Meilisearch returned a malformed list of indexes.');
            }
            foreach ($results as $index) {
                if (!\is_array($index) || !\is_string($index['uid'] ?? null)) {
                    throw new MeilisearchException('Meilisearch returned a malformed list of indexes.');
                }
                $uids[] = $index['uid'];
            }
            if ([] === $results || $offset + $limit >= $total) {
                return $uids;
            }
        }
    }

    /**
     * @return array{numberOfDocuments: int, rawDocumentDbSize: int, isIndexing: bool}
     */
    public function indexStats(string $uid): array
    {
        $stats = $this->array(fn (): mixed => (new Indexes($this->http, $uid))->stats(), 'read the statistics of the index '.$uid);
        $documents = $stats['numberOfDocuments'] ?? null;
        $size = $stats['rawDocumentDbSize'] ?? null;
        $indexing = $stats['isIndexing'] ?? null;
        if (!\is_int($documents) || !\is_int($size) || !\is_bool($indexing)) {
            throw new MeilisearchException(\sprintf('Meilisearch returned malformed statistics of the index %s.', $uid));
        }

        return ['numberOfDocuments' => $documents, 'rawDocumentDbSize' => $size, 'isIndexing' => $indexing];
    }

    public function databaseSize(): int
    {
        $size = $this->array(fn (): mixed => (new Stats($this->http))->show(), 'read the statistics')['databaseSize'] ?? null;
        if (!\is_int($size)) {
            throw new MeilisearchException('Meilisearch returned malformed statistics.');
        }

        return $size;
    }

    public function version(): string
    {
        $version = $this->array(fn (): mixed => (new Version($this->http))->show(), 'read the version')['pkgVersion'] ?? null;
        if (!\is_string($version)) {
            throw new MeilisearchException('Meilisearch returned a malformed version.');
        }

        return $version;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return list<Task>
     */
    private function taskList(array $query, string $action): array
    {
        $results = $this->array(fn (): mixed => (new Tasks($this->http))->all($query), $action)['results'] ?? null;
        if (!\is_array($results)) {
            throw new MeilisearchException(\sprintf('Meilisearch returned a malformed list of tasks when trying to %s.', $action));
        }

        $tasks = [];
        foreach ($results as $task) {
            $tasks[] = $this->toTask($task, $action);
        }

        return $tasks;
    }

    /**
     * @param \Closure(): mixed $call
     */
    private function task(\Closure $call, string $action): Task
    {
        return $this->toTask($this->call($call, $action), $action);
    }

    /**
     * @param \Closure(): mixed $call
     *
     * @return array<array-key, mixed>
     */
    private function array(\Closure $call, string $action): array
    {
        $result = $this->call($call, $action);
        if (!\is_array($result)) {
            throw new MeilisearchException(\sprintf('Meilisearch returned an unexpected answer when trying to %s.', $action));
        }

        return $result;
    }

    private function toTask(mixed $task, string $action): Task
    {
        try {
            if (!\is_array($task)) {
                throw new InvalidArgumentException('The task returned by Meilisearch is not an object.');
            }

            return Task::fromArray($task);
        } catch (InvalidArgumentException $e) {
            throw new MeilisearchException(\sprintf('Meilisearch returned a malformed task when trying to %s.', $action), 0, $e);
        }
    }

    /**
     * @param \Closure(): mixed $call
     */
    private function call(\Closure $call, string $action): mixed
    {
        // the endpoints declare an array return type: an empty answer is a TypeError
        try {
            return $call();
        } catch (MeilisearchExceptionInterface|ClientExceptionInterface|\JsonException|\TypeError $e) {
            throw new MeilisearchException(\sprintf('Cannot %s in Meilisearch: %s', $action, $e->getMessage()), 0, $e);
        }
    }
}
