<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\HouseNumber\HouseNumberWriterInterface;
use Tito10047\Geocoder\Import\ConverterInterface;
use Tito10047\Geocoder\Import\ImportProgressInterface;
use Tito10047\Geocoder\Name\TranslationWriterInterface;

/**
 * Writes what the inner converter writes, then throws for the dumps it is told to fail.
 */
final readonly class FailingConverter implements ConverterInterface
{
    /**
     * @param list<string> $failingDumps
     */
    public function __construct(
        private ConverterInterface $inner,
        private array $failingDumps,
        private \Throwable $failure,
    ) {
    }

    public function convert(
        string $dump,
        DocumentWriterInterface $documents,
        HouseNumberWriterInterface $houseNumbers,
        TranslationWriterInterface $translations,
        ?ImportProgressInterface $progress = null,
    ): array {
        $summaries = $this->inner->convert($dump, $documents, $houseNumbers, $translations, $progress);
        if (\in_array($dump, $this->failingDumps, true)) {
            throw $this->failure;
        }

        return $summaries;
    }
}
