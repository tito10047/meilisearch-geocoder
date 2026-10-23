<?php

namespace Tito10047\Geocoder\Tests\Search\Backend;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Backend\SearchHit;
use Tito10047\Geocoder\Tests\Fake\Documents;

final class SearchHitTest extends TestCase
{
    public function testKeepsTheValues(): void
    {
        $document = Documents::create('R1', 'Košice');

        $this->assertSame($document, (new SearchHit($document, 2))->document);
        $this->assertSame(2, (new SearchHit($document, 2))->matchingWords);
        $this->assertNull((new SearchHit($document))->matchingWords);
    }

    public function testRejectsNegativeMatchingWords(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SearchHit(Documents::create('R1', 'Košice'), -1);
    }
}
