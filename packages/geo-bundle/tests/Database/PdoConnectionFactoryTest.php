<?php

namespace Tito10047\GeoBundle\Tests\Database;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\GeoBundle\Database\PdoConnectionFactory;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;

final class PdoConnectionFactoryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/tito10047-geo-pdo-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testConnectsThrowingTheErrors(): void
    {
        $pdo = PdoConnectionFactory::connect('sqlite::memory:');

        $this->assertSame(\PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(\PDO::ATTR_ERRMODE));
    }

    public function testReportsAnUnreachableDatabaseAsUnavailable(): void
    {
        try {
            PdoConnectionFactory::connect('nodriver-tito10047-geo:host=localhost');
            $this->fail('The connection did not fail.');
        } catch (GeocoderUnavailableException $exception) {
            $this->assertStringStartsWith('The database of the geocoder cannot be connected to: ', $exception->getMessage());
            $this->assertInstanceOf(\PDOException::class, $exception->getPrevious());
        }
    }

    public function testCreatesTheMissingDirectoriesOfAnSqliteDatabase(): void
    {
        $file = $this->directory.'/var/geo/geo.sqlite';

        $pdo = PdoConnectionFactory::connect('sqlite:'.$file);
        $pdo->exec('CREATE TABLE t (id INTEGER)');

        $this->assertFileExists($file);
        $this->assertSame(0775 & ~umask(), fileperms(\dirname($file)) & 0777);
    }

    public function testConnectsToAnSqliteDatabaseInAnExistingDirectory(): void
    {
        mkdir($this->directory);

        PdoConnectionFactory::connect('sqlite:'.$this->directory.'/geo.sqlite')->exec('CREATE TABLE t (id INTEGER)');

        $this->assertFileExists($this->directory.'/geo.sqlite');
    }

    public function testReportsADirectoryThatCannotBeCreatedAsUnavailable(): void
    {
        $this->expectException(GeocoderUnavailableException::class);
        $this->expectExceptionMessage('The directory "/dev/null/tito10047-geo" of the SQLite database of the geocoder cannot be created: ');

        PdoConnectionFactory::connect(Configs::UNREACHABLE_DSN);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sqliteDsnsWithoutDirectory(): iterable
    {
        yield 'in memory' => ['sqlite::memory:'];
        yield 'temporary' => ['sqlite:'];
        yield 'URI' => ['sqlite:file:%s/geo.sqlite?mode=memory'];
    }

    #[DataProvider('sqliteDsnsWithoutDirectory')]
    public function testCreatesNoDirectoryForAnSqliteDatabaseWithoutPath(string $dsn): void
    {
        PdoConnectionFactory::connect(\sprintf($dsn, $this->directory));

        $this->assertDirectoryDoesNotExist($this->directory);
    }
}
