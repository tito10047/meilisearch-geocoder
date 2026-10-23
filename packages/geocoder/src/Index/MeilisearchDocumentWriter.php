<?php

namespace Tito10047\Geocoder\Index;

use Meilisearch\Contracts\Http;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\Index\Exception\WriterClosedException;
use Tito10047\Geocoder\Index\Meilisearch\MeilisearchApi;
use Tito10047\Geocoder\Index\Meilisearch\TaskWaiter;

/**
 * Sends documents to a Meilisearch index in NDJSON batches without waiting for each to be indexed.
 *
 * At most IndexingOptions::$maxPendingTasks batches are unfinished at a time: before sending
 * another one, the writer waits for the oldest. The PHP memory is one batch whatever the number
 * of documents, and Meilisearch never merges more than maxPendingTasks batches into one
 * indexing run, which bounds its memory too. A failed batch fails the next write() or close().
 */
final class MeilisearchDocumentWriter implements DocumentWriterInterface
{
    private const PRIMARY_KEY = 'id';

    private readonly MeilisearchApi $api;
    private readonly TaskWaiter $waiter;
    private string $buffer = '';
    private int $buffered = 0;
    /** @var list<int> unfinished document tasks, oldest first */
    private array $pending = [];
    private bool $closed = false;

    /**
     * @param Http $http the HTTP layer of meilisearch-php: new \Meilisearch\Http\Client($url, $apiKey)
     *
     * @throws InvalidArgumentException when the uid is not a valid index uid
     */
    public function __construct(
        Http $http,
        private readonly string $uid,
        private readonly IndexingOptions $options = new IndexingOptions(),
    ) {
        MeilisearchApi::assertIndexUid($uid);
        $this->api = new MeilisearchApi($http);
        $this->waiter = new TaskWaiter($this->api, $options);
    }

    public function write(Document $document): void
    {
        if ($this->closed) {
            throw new WriterClosedException(\sprintf('The writer of the index %s is closed.', $this->uid));
        }

        try {
            $this->buffer .= json_encode($document->toArray(), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR)."\n";
        } catch (\JsonException $e) {
            throw new RuntimeException(\sprintf('The document "%s" cannot be encoded as JSON: %s', $document->id, $e->getMessage()), 0, $e);
        }

        if (++$this->buffered >= $this->options->batchSize) {
            $this->flush();
        }
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;

        $this->flush();
        $this->waiter->wait($this->pending);
        $this->pending = [];
    }

    private function flush(): void
    {
        if (0 === $this->buffered) {
            return;
        }

        $this->pending = $this->waiter->waitUntilAtMost($this->pending, $this->options->maxPendingTasks - 1);

        $task = $this->api->addDocumentsNdjson($this->uid, $this->buffer, self::PRIMARY_KEY);
        $this->buffer = '';
        $this->buffered = 0;
        $this->pending[] = $task->uid;
    }
}
