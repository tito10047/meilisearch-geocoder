<?php

namespace Tito10047\Geocoder\Storage;

use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\HouseNumber\HouseNumberWriterInterface;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Name\TranslationWriterInterface;

/**
 * The tables of the builds in a database: created empty, filled, completed with the metadata of
 * the build, published under the live names, dropped.
 */
interface BuildStorageInterface
{
    /**
     * The version of the tables this storage creates, stored with each build.
     */
    public function schemaVersion(): int;

    /**
     * Creates the empty tables of a build, without their secondary indexes (loading is faster
     * without them).
     *
     * @throws RuntimeException when one of the tables exists already or cannot be created
     */
    public function create(TableNames $tables): void;

    /**
     * A writer of the house numbers into the tables; they are written once it is closed.
     */
    public function houseNumberWriter(TableNames $tables): HouseNumberWriterInterface;

    /**
     * A writer of the translations into the tables; they are written once it is closed.
     */
    public function translationWriter(TableNames $tables): TranslationWriterInterface;

    /**
     * Completes the loaded tables of the build ($build->tables): creates their secondary indexes
     * and writes the metadata of the build.
     *
     * @throws RuntimeException when the indexes or the metadata cannot be written
     */
    public function complete(Build $build): void;

    /**
     * The build whose metadata the tables hold; its tables are the given ones.
     *
     * @throws BuildNotFoundException when the tables or their metadata do not exist
     * @throws RuntimeException       when the metadata cannot be read or is invalid
     */
    public function read(TableNames $tables): Build;

    /**
     * Whether at least one of the tables exists.
     *
     * @throws RuntimeException when the tables cannot be listed
     */
    public function exists(TableNames $tables): bool;

    /**
     * Records in the metadata of the tables that the publication of their build started, with
     * the fingerprint of its index (IndexManagerInterface::fingerprint()) taken before the swap:
     * it tells a later run which index is the build's once it was swapped or renamed. It goes
     * live with the tables.
     *
     * @throws BuildNotFoundException when the tables hold no build
     * @throws RuntimeException       when it cannot be written
     */
    public function recordPublication(TableNames $tables, string $indexFingerprint): void;

    /**
     * The fingerprint of the index recorded by recordPublication(), if any.
     *
     * @throws BuildNotFoundException when the tables hold no build
     * @throws RuntimeException       when it cannot be read
     */
    public function recordedPublication(TableNames $tables): ?string;

    /**
     * Renames the tables of a build to the live names, at once, and drops the live tables they
     * replace (there may be none on a first publication).
     *
     * @throws BuildNotFoundException when a table of the build does not exist
     * @throws RuntimeException       when the tables cannot be renamed or dropped
     */
    public function publish(TableNames $build, TableNames $live): void;

    /**
     * Drops the tables that exist, with their indexes.
     *
     * @throws RuntimeException when a table cannot be dropped
     */
    public function drop(TableNames $tables): void;

    /**
     * The suffixes of the existing tables named after the given ones ("_20261023120000" for
     * "geo_house_20261023120000"), the empty suffix of the tables themselves excepted.
     *
     * @return list<string>
     *
     * @throws RuntimeException when the tables cannot be listed
     */
    public function suffixes(TableNames $tables): array;

    /**
     * Rolls back what a failed load left uncommitted (a writer stops in the middle of its
     * transaction when it fails), so that the tables can be dropped.
     */
    public function rollBack(): void;
}
