<?php

namespace Tito10047\GeoBundle\Tests\Database;

use PHPUnit\Framework\TestCase;
use Tito10047\GeoBundle\Database\PdoConnectionFactory;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;

final class PdoConnectionFactoryTest extends TestCase
{
    public function testConnectsThrowingTheErrors(): void
    {
        $pdo = PdoConnectionFactory::connect('sqlite::memory:');

        $this->assertSame(\PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(\PDO::ATTR_ERRMODE));
    }

    public function testReportsAnUnreachableDatabaseAsUnavailable(): void
    {
        try {
            PdoConnectionFactory::connect(Configs::UNREACHABLE_DSN);
            $this->fail('The connection did not fail.');
        } catch (GeocoderUnavailableException $exception) {
            $this->assertStringStartsWith('The database of the geocoder cannot be connected to: ', $exception->getMessage());
            $this->assertInstanceOf(\PDOException::class, $exception->getPrevious());
        }
    }
}
