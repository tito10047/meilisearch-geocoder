<?php

namespace Tito10047\Geocoder\Import\Workspace;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * The scratch storage of one conversion: what has to be seen whole before it can be written
 * (places of the same name, segments of the same street) and what was already seen.
 *
 * A dump holds millions of objects: implementations keep them out of the PHP memory.
 */
interface ConversionWorkspaceInterface
{
    /**
     * Whether the key is seen for the first time; it is remembered.
     *
     * @throws RuntimeException when the storage fails
     */
    public function firstOccurrence(string $key): bool;

    /**
     * Keeps a place until its group is complete.
     *
     * @param string $group the places of a group are deduplicated together
     *
     * @throws RuntimeException when the storage fails
     */
    public function addPlace(string $group, Document $place): void;

    /**
     * The places by group, each group in the order the places were added.
     *
     * @return iterable<int, list<Document>>
     *
     * @throws RuntimeException when the storage fails
     */
    public function placeGroups(): iterable;

    /**
     * Keeps the street of a named way: it replaces a street of the same id unless that one has
     * more than one name (its translations are worth more than the position of a later segment).
     *
     * @throws RuntimeException when the storage fails
     */
    public function addStreetOfWay(Document $street): void;

    /**
     * Keeps a street known from an address, unless a street of the same id is already kept.
     *
     * @throws RuntimeException when the storage fails
     */
    public function addStreetOfAddress(Document $street): void;

    /**
     * The streets, in the order they were first added.
     *
     * @return iterable<int, Document>
     *
     * @throws RuntimeException when the storage fails
     */
    public function streets(): iterable;

    /**
     * Releases the storage; the workspace cannot be used afterwards.
     */
    public function close(): void;
}
