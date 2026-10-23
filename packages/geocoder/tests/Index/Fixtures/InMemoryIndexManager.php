<?php

namespace Tito10047\Geocoder\Tests\Index\Fixtures;

use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\Index\IndexManagerInterface;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\IndexStatus;

/**
 * Indexes as named labels: enough to check what is swapped and deleted.
 */
final class InMemoryIndexManager implements IndexManagerInterface
{
    /** @var list<string> */
    public array $log = [];

    /**
     * @param array<string, string> $indexes index uid => label of its content
     */
    public function __construct(public array $indexes = [])
    {
    }

    public function create(string $uid, IndexSettings $settings): void
    {
        $this->log[] = 'create '.$uid;
        $this->indexes[$uid] = $uid;
    }

    public function applySettings(string $uid, IndexSettings $settings): void
    {
        $this->log[] = 'settings '.$uid;
    }

    public function documentWriter(string $uid): DocumentWriterInterface
    {
        throw new \LogicException('Not used.');
    }

    public function waitForTasks(string $uid): void
    {
    }

    public function compact(string $uid): void
    {
        $this->log[] = 'compact '.$uid;
    }

    public function swap(string $uid, string $liveUid): void
    {
        $this->log[] = \sprintf('swap %s %s', $uid, $liveUid);
        $content = $this->get($uid);
        if (isset($this->indexes[$liveUid])) {
            $this->indexes[$uid] = $this->indexes[$liveUid];
        } else {
            unset($this->indexes[$uid]);
        }
        $this->indexes[$liveUid] = $content;
    }

    public function delete(string $uid): void
    {
        $this->log[] = 'delete '.$uid;
        $this->get($uid);
        unset($this->indexes[$uid]);
    }

    public function exists(string $uid): bool
    {
        return isset($this->indexes[$uid]);
    }

    public function indexUids(): array
    {
        return array_keys($this->indexes);
    }

    public function status(string $uid): IndexStatus
    {
        $this->get($uid);

        return new IndexStatus($uid, 0, 0, 0, false, null, '1.35.1');
    }

    public function snapshot(): void
    {
        $this->log[] = 'snapshot';
    }

    private function get(string $uid): string
    {
        return $this->indexes[$uid] ?? throw new RuntimeException(\sprintf('Index %s not found.', $uid));
    }
}
