<?php

namespace Tito10047\Geocoder\Name;

use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Receives the translated names of towns, regions and countries found by an import.
 *
 * The same translation may be written many times (every street of a town carries it):
 * implementations store it once. They may buffer: the translations are complete only once
 * close() returned.
 */
interface TranslationWriterInterface
{
    /**
     * @throws RuntimeException when the translation cannot be written
     */
    public function write(Translation $translation): void;

    /**
     * Writes the buffered translations and releases the resources; nothing may be written afterwards.
     *
     * @throws RuntimeException when the translations cannot be written
     */
    public function close(): void;
}
