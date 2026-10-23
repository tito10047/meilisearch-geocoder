<?php

namespace Tito10047\Geocoder\Tests\Storage\Pdo\Dialect;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Storage\Pdo\Dialect\MysqlDialect;
use Tito10047\Geocoder\Storage\TableNames;

final class MysqlDialectTest extends TestCase
{
    public function testGivesTheTablesABinaryCollation(): void
    {
        $this->assertSame(' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin', (new MysqlDialect())->tableOptions());
    }

    public function testListsTheTablesOfTheCurrentDatabase(): void
    {
        $this->assertStringContainsString('table_schema = DATABASE()', (new MysqlDialect())->listTablesQuery());
    }

    public function testPublishesTheFirstBuildByOneRename(): void
    {
        $dialect = new MysqlDialect();

        $this->assertFalse($dialect->hasTransactionalDdl());
        $this->assertSame(
            ['RENAME TABLE geo_house_b TO geo_house, geo_name_b TO geo_name, geo_meta_b TO geo_meta'],
            $dialect->publishStatements((new TableNames())->withSuffix('_b'), new TableNames(), []),
        );
    }

    public function testReplacesTheLiveTablesInOneAtomicRenameAndDropsThemAfterwards(): void
    {
        $statements = (new MysqlDialect())->publishStatements((new TableNames())->withSuffix('_b'), new TableNames(), ['geo_house', 'GEO_NAME', 'geo_meta']);

        $this->assertSame([
            'DROP TABLE IF EXISTS geo_house_old, geo_name_old, geo_meta_old',
            'RENAME TABLE geo_house TO geo_house_old, geo_house_b TO geo_house, geo_name TO geo_name_old, geo_name_b TO geo_name, geo_meta TO geo_meta_old, geo_meta_b TO geo_meta',
            'DROP TABLE geo_house_old, geo_name_old, geo_meta_old',
        ], $statements);
    }

    public function testRenamesAwayOnlyTheLiveTablesThatExist(): void
    {
        $statements = (new MysqlDialect())->publishStatements(new TableNames('h_b', 'n_b', 'm_b'), new TableNames('h', 'n', 'm'), ['n']);

        $this->assertSame([
            'DROP TABLE IF EXISTS n_old',
            'RENAME TABLE h_b TO h, n TO n_old, n_b TO n, m_b TO m',
            'DROP TABLE n_old',
        ], $statements);
    }

    public function testStreamsWithAnUnbufferedQuery(): void
    {
        $this->assertSame([MysqlDialect::ATTR_USE_BUFFERED_QUERY => false], (new MysqlDialect())->streamingOptions());
        if (\defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')) {
            $this->assertSame(MysqlDialect::ATTR_USE_BUFFERED_QUERY, \constant('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY'));
        }
    }
}
