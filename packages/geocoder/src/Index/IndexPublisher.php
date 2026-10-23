<?php

namespace Tito10047\Geocoder\Index;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Puts a built index live and removes the indexes builds leave behind.
 *
 * The replaced live index is deleted right after the swap: an index costs gigabytes of disk and
 * a rollback is a new import.
 */
final readonly class IndexPublisher
{
    public function __construct(
        private IndexManagerInterface $indexes,
        private IndexNamingInterface $naming,
    ) {
    }

    /**
     * Swaps the build in under the live uid (searches never see a gap) and deletes the index it
     * replaced.
     *
     * @throws InvalidArgumentException when the uid is not one of a build
     * @throws RuntimeException         when the swap or the deletion fails
     */
    public function publish(string $buildUid): void
    {
        if (!$this->naming->isBuildUid($buildUid)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not the uid of a build of the index %s.', $buildUid, $this->naming->liveUid()));
        }

        $this->indexes->swap($buildUid, $this->naming->liveUid());
        // after a swap the build uid holds the former live index; after a first publication
        // (a rename) it does not exist any more
        if ($this->indexes->exists($buildUid)) {
            $this->indexes->delete($buildUid);
        }
    }

    /**
     * Deletes the build indexes left behind (failed or abandoned imports), except the given ones:
     * a build still running must be listed here.
     *
     * @param list<string> $keep build uids not to delete
     *
     * @return list<string> the deleted uids
     *
     * @throws RuntimeException when an index cannot be listed or deleted
     */
    public function cleanup(array $keep = []): array
    {
        $deleted = [];
        foreach ($this->indexes->indexUids() as $uid) {
            if ($this->naming->isBuildUid($uid) && !\in_array($uid, $keep, true)) {
                $this->indexes->delete($uid);
                $deleted[] = $uid;
            }
        }

        return $deleted;
    }
}
