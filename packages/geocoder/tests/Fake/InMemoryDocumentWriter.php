<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentWriterInterface;

final class InMemoryDocumentWriter implements DocumentWriterInterface
{
    /** @var list<Document> */
    public array $documents = [];
    public bool $closed = false;

    public function write(Document $document): void
    {
        $this->documents[] = $document;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
