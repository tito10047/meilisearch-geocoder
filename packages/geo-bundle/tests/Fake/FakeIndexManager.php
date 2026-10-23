<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\Index\IndexManagerInterface;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\IndexStatus;

/**
 * Answers the status it is given; the commands of the bundle use nothing else.
 */
final class FakeIndexManager implements IndexManagerInterface
{
    public ?IndexStatus $status = null;

    public ?\Throwable $exception = null;

    /** @var list<string> */
    public array $statusCalls = [];

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
        throw new \LogicException('Not faked.');
    }

    public function indexUids(): array
    {
        throw new \LogicException('Not faked.');
    }

    public function snapshot(): void
    {
        throw new \LogicException('Not faked.');
    }
}
