<?php

namespace Tito10047\Geocoder\Tests\Storage\Pdo\Dialect;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Storage\Pdo\Dialect\PostgresqlDialect;
use Tito10047\Geocoder\Storage\TableNames;

final class PostgresqlDialectTest extends TestCase
{
    public function testKeepsTheDefaultTableOptions(): void
    {
        $this->assertSame('', (new PostgresqlDialect())->tableOptions());
    }

    public function testListsTheTablesOfTheCurrentSchema(): void
    {
        $this->assertStringContainsString('table_schema = current_schema()', (new PostgresqlDialect())->listTablesQuery());
    }

    public function testPublishesTheFirstBuildByRenamesInATransaction(): void
    {
        $dialect = new PostgresqlDialect();

        $this->assertTrue($dialect->hasTransactionalDdl());
        $this->assertSame([
            'ALTER TABLE geo_house_b RENAME TO geo_house',
            'ALTER TABLE geo_name_b RENAME TO geo_name',
            'ALTER TABLE geo_meta_b RENAME TO geo_meta',
        ], $dialect->publishStatements((new TableNames())->withSuffix('_b'), new TableNames(), []));
    }

    public function testDropsTheLiveTablesBeforeTheRenames(): void
    {
        $this->assertSame([
            'DROP TABLE geo_house',
            'DROP TABLE geo_meta',
            'ALTER TABLE geo_house_b RENAME TO geo_house',
            'ALTER TABLE geo_name_b RENAME TO geo_name',
            'ALTER TABLE geo_meta_b RENAME TO geo_meta',
        ], (new PostgresqlDialect())->publishStatements((new TableNames())->withSuffix('_b'), new TableNames(), ['geo_house', 'geo_meta']));
    }

    public function testStreamsThroughAServerSideCursor(): void
    {
        $this->assertSame([\PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL], (new PostgresqlDialect())->streamingOptions());
    }
}
