<?php

namespace Tito10047\Geocoder\Tests\Index\Fixtures;

use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\Index\IndexManagerInterface;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\IndexStatus;
use Tito10047\Geocoder\Tests\Fake\InMemoryDocumentWriter;

/**
 * Indexes as named labels: enough to check what is swapped and deleted.
 *
 * The documents, the settings hash and the writer of an index follow its label (its content)
 * through the swaps. snapshot() writes the labels into a file of the snapshot directory, when
 * one is given.
 */
final class InMemoryIndexManager implements IndexManagerInterface
{
    /** @var list<string> */
    public array $log = [];
    /** @var array<string, InMemoryDocumentWriter> by label */
    public array $writers = [];
    /** @var array<string, string> by label */
    public array $settingsHashes = [];
    public ?string $snapshotDirectory = null;
    /** @var string|null the operation ("create", "compact", …) that throws */
    public ?string $failOn = null;

    /**
     * @param array<string, string> $indexes index uid => label of its content
     */
    public function __construct(public array $indexes = [])
    {
    }

    public function create(string $uid, IndexSettings $settings): void
    {
        $this->log[] = 'create '.$uid;
        $this->fail('create');
        $this->indexes[$uid] = $uid;
        $this->settingsHashes[$uid] = $settings->hash();
    }

    public function applySettings(string $uid, IndexSettings $settings): void
    {
        $this->log[] = 'settings '.$uid;
    }

    public function documentWriter(string $uid): DocumentWriterInterface
    {
        $this->log[] = 'writer '.$uid;

        return $this->writers[$this->get($uid)] = new InMemoryDocumentWriter();
    }

    public function waitForTasks(string $uid): void
    {
    }

    public function compact(string $uid): void
    {
        $this->log[] = 'compact '.$uid;
        $this->fail('compact');
    }

    public function swap(string $uid, string $liveUid): void
    {
        $this->log[] = \sprintf('swap %s %s', $uid, $liveUid);
        $this->fail('swap');
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
        $label = $this->get($uid);

        return new IndexStatus($uid, \count(isset($this->writers[$label]) ? $this->writers[$label]->documents : []), 0, 0, false, $this->settingsHashes[$label] ?? null, '1.35.1');
    }

    public function snapshot(): void
    {
        $this->log[] = 'snapshot';
        $this->fail('snapshot');
        if (null !== $this->snapshotDirectory) {
            file_put_contents($this->snapshotDirectory.'/data.ms.snapshot', json_encode($this->indexes, \JSON_THROW_ON_ERROR));
        }
    }

    private function fail(string $operation): void
    {
        if ($operation === $this->failOn) {
            throw new RuntimeException(\sprintf('The %s failed.', $operation));
        }
    }

    private function get(string $uid): string
    {
        return $this->indexes[$uid] ?? throw new RuntimeException(\sprintf('Index %s not found.', $uid));
    }
}
