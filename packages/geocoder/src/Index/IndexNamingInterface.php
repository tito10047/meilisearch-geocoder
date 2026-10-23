<?php

namespace Tito10047\Geocoder\Index;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * Names the indexes: the live one searches query, and the builds swapped into it.
 *
 * An import fills a new build index while searches keep using the live one; publishing swaps
 * the build in under the live uid.
 */
interface IndexNamingInterface
{
    /**
     * The uid searches query ("geo").
     */
    public function liveUid(): string;

    /**
     * The uid of a new build started at the given time ("geo_20261023120000").
     */
    public function buildUid(\DateTimeImmutable $startedAt): string;

    /**
     * Whether the uid is one of a build of this naming (and so may be swapped in or cleaned up).
     */
    public function isBuildUid(string $uid): bool;

    /**
     * The suffix shared by the index and the tables of a build ("_20261023120000"), for
     * TableNames::withSuffix().
     *
     * @throws InvalidArgumentException when the uid is not one of a build
     */
    public function buildSuffix(string $buildUid): string;
}
