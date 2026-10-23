<?php

namespace Tito10047\Geocoder\Import;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;

/**
 * Builds the index and the tables of the geocoder from dumps, and makes them live.
 *
 * An import never touches what searches use: it fills a new index and new tables (a build),
 * which publish() puts live at once.
 */
interface ImporterInterface
{
    /**
     * Converts the dumps into a new index and new tables; nothing live changes.
     *
     * On failure, what was built is removed before the exception is thrown.
     *
     * @param iterable<string>             $dumps    paths or stream URLs ("php://stdin"); ".zst" is decompressed
     * @param ImportProgressInterface|null $progress follows the conversion of each dump
     *
     * @throws InvalidArgumentException when there is no dump, a dump cannot be opened or is not a dump
     * @throws RuntimeException         when the build fails (the search engine, the database, reading a dump)
     */
    public function import(iterable $dumps, ?ImportProgressInterface $progress = null): Build;

    /**
     * Makes a build live: its index swapped in under the live uid, its tables renamed to the
     * live names; the index and the tables it replaces are dropped, and so are the builds started
     * before it (left by failed or abandoned imports). Builds started after it are kept: their
     * imports may still run.
     *
     * @return Build the same build, in the live tables
     *
     * @throws InvalidArgumentException when the index uid is not one of a build
     * @throws BuildNotFoundException   when the index or the tables of the build do not exist (any more)
     * @throws RuntimeException         when the build is of another schema version, or the index or the tables cannot be published
     */
    public function publish(Build $build): Build;

    /**
     * A build not published yet, e.g. one imported earlier without publishing it.
     *
     * @param string $indexUid "geo_20261023120000"
     *
     * @throws InvalidArgumentException when the uid is not one of a build
     * @throws BuildNotFoundException   when neither its index nor the live index (after a partial publication) holds it, or its tables do not exist
     * @throws RuntimeException         when the search engine or the database cannot be asked
     */
    public function build(string $indexUid): Build;

    /**
     * The live build, read from the live tables.
     *
     * @throws BuildNotFoundException when nothing was published yet
     * @throws RuntimeException       when the database cannot be asked
     */
    public function live(): Build;

    /**
     * Removes the indexes and the tables of builds: those of failed or abandoned imports. A build
     * of an import still running must be kept.
     *
     * @param list<string> $keep the index uids of the builds to keep
     *
     * @return list<string> the index uids of the removed builds
     *
     * @throws RuntimeException when an index or a table cannot be listed or removed
     */
    public function cleanup(array $keep = []): array;
}
