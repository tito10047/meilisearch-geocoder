<?php

namespace Tito10047\Geocoder\Tests\Storage\Pdo\Dialect;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Storage\Pdo\Dialect\TablePairs;
use Tito10047\Geocoder\Storage\TableNames;

final class TablePairsTest extends TestCase
{
    public function testPairsTheTablesByRole(): void
    {
        $this->assertSame(
            [['a', 'x'], ['b', 'y'], ['c', 'z']],
            TablePairs::of(new TableNames('a', 'b', 'c'), new TableNames('x', 'y', 'z')),
        );
    }

    public function testFindsATableWhateverTheCase(): void
    {
        $this->assertTrue(TablePairs::contains(['geo_house'], 'GEO_House'));
        $this->assertFalse(TablePairs::contains(['geo_house_1'], 'geo_house'));
    }
}
