<?php

namespace Tito10047\Geocoder\Tests\Storage\Pdo;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Storage\Pdo\PdoErrorMode;

final class PdoErrorModeTest extends TestCase
{
    public function testAcceptsAConnectionThatThrows(): void
    {
        PdoErrorMode::assertExceptions(new \PDO('sqlite::memory:'));

        $this->expectNotToPerformAssertions();
    }

    public function testRejectsASilentConnection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDO::ERRMODE_EXCEPTION');

        PdoErrorMode::assertExceptions(new \PDO('sqlite::memory:', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT]));
    }
}
