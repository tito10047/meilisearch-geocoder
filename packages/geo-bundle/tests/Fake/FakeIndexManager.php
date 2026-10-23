<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\Index\IndexManagerInterface;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\IndexStatus;

/**
 * Answers the status and the index uids it is given (an index exists when it has a status); the
 * bundle uses nothing else.
 */
final class FakeIndexManager implements IndexManagerInterface
{
    public ?IndexStatus $status = null;

    public ?\Throwable $exception = null;

    /** @var list<string> */
    public array $statusCalls = [];

    /** @var list<string> */
    public array $indexUids = [];

    public function status(string $uid): IndexStatus
    {
        $this->statusCalls[] = $uid;
        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return $this->status ?? throw new \LogicException('No status given to the fake.');
    }

    public function create(string $uid, IndexSettings $settings): void
    {
        throw new \LogicException('Not faked.');
    }

    public function applySettings(string $uid, IndexSettings $settings): void
    {
        throw new \LogicException('Not faked.');
    }

    public function documentWriter(string $uid): DocumentWriterInterface
    {
        throw new \LogicException('Not faked.');
    }

    public function waitForTasks(string $uid): void
    {
        throw new \LogicException('Not faked.');
    }

    public function compact(string $uid): void
    {
        throw new \LogicException('Not faked.');
    }

    public function swap(string $uid, string $liveUid): void
    {
        throw new \LogicException('Not faked.');
    }

    public function delete(string $uid): void
    {
        throw new \LogicException('Not faked.');
    }

    public function exists(string $uid): bool
    {
        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return null !== $this->status;
    }

    public function fingerprint(string $uid): ?string
    {
        throw new \LogicException('Not faked.');
    }

    public function version(): string
    {
        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return $this->status->meilisearchVersion ?? '1.35.1';
    }

    public function indexUids(): array
    {
        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return $this->indexUids;
    }

    public function snapshot(): void
    {
        throw new \LogicException('Not faked.');
    }
}
