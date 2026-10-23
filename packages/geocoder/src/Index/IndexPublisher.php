<?php

namespace Tito10047\Geocoder\Index;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Puts a built index live and removes the indexes builds leave behind.
 *
 * The replaced live index is deleted once the publication is complete: an index costs gigabytes
 * of disk and a rollback after it is a new import.
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
     * replaced: swapIn() then removeReplaced().
     *
     * Not resumable by itself: Importer::publish() records the fingerprint of the build index
     * first, so that a publication interrupted after the swap is finished by running it again.
     *
     * @throws InvalidArgumentException when the uid is not one of a build
     * @throws RuntimeException         when the swap or the deletion fails
     */
    public function publish(string $buildUid): void
    {
        $this->swapIn($buildUid);
        $this->removeReplaced($buildUid);
    }

    /**
     * Swaps the build in under the live uid. Afterwards the build uid holds the former live
     * index, or does not exist on a first publication (the build was renamed).
     *
     * @throws InvalidArgumentException when the uid is not one of a build
     * @throws RuntimeException         when the indexes cannot be swapped, or the swap cannot be confirmed
     */
    public function swapIn(string $buildUid): void
    {
        $this->assertBuildUid($buildUid);

        $this->indexes->swap($buildUid, $this->naming->liveUid());
    }

    /**
     * Undoes swapIn(): the former live index (or none) live again, the build under its uid.
     *
     * @throws InvalidArgumentException when the uid is not one of a build
     * @throws RuntimeException         when the indexes cannot be swapped back, or the swap cannot be confirmed
     */
    public function swapBack(string $buildUid): void
    {
        $this->assertBuildUid($buildUid);

        // after a rename the build uid is free: the live index is renamed back to it
        $this->indexes->swap($this->naming->liveUid(), $buildUid);
    }

    /**
     * Deletes the former live index that swapIn() left under the build uid, if any.
     *
     * @throws InvalidArgumentException when the uid is not one of a build
     * @throws RuntimeException         when the index cannot be deleted
     */
    public function removeReplaced(string $buildUid): void
    {
        $this->assertBuildUid($buildUid);

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

    /**
     * @throws InvalidArgumentException when the uid is not one of a build
     */
    private function assertBuildUid(string $buildUid): void
    {
        if (!$this->naming->isBuildUid($buildUid)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not the uid of a build of the index %s.', $buildUid, $this->naming->liveUid()));
        }
    }
}
