<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Import\ImportProgressInterface;

final class RecordingProgress implements ImportProgressInterface
{
    /** @var list<array{string, ImportCounts}> */
    public array $calls = [];

    public function advance(string $dump, ImportCounts $counts): void
    {
        $this->calls[] = [$dump, $counts];
    }
}
