<?php

namespace Tito10047\GeoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestApplication;
use Tito10047\GeoBundle\Tests\Fake\FakeMatrix;
use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Costing;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\Matrix;
use Tito10047\Routing\MatrixRequest;

final class RoutingMatrixCommandTest extends TestCase
{
    private TestApplication $application;
    private FakeMatrix $matrix;

    protected function setUp(): void
    {
        $this->application = new TestApplication(Configs::routing(['radius' => 150]));
        $this->matrix = $this->application->service('tito10047_geo.routing.matrix', FakeMatrix::class);
    }

    protected function tearDown(): void
    {
        $this->application->shutdown();
    }

    public function testPrintsTheMatrix(): void
    {
        $this->matrix->matrix = new Matrix([[202_335, 92_790], [150_949, null]], [[6_142, 2_978], [4_934, null]]);
        $tester = $this->application->command('tito10047-geo:routing:matrix');

        $tester->execute(['--source' => ['48.1486,17.1077', ' 48.3774 , 17.5872 '], '--target' => ['49.2231,18.7394', '48.3069,18.0864']]);

        $tester->assertCommandIsSuccessful();
        $lines = array_map(trim(...), explode("\n", $tester->getDisplay()));
        $this->assertContains('From \ to         49.2231,18.7394     48.3069,18.0864', $lines);
        $this->assertContains('48.1486,17.1077   202.3 km, 102 min   92.8 km, 50 min', $lines);
        $this->assertContains('48.3774,17.5872   150.9 km, 82 min    no route', $lines);
        $this->assertStringContainsString(Attribution::OPENSTREETMAP, $tester->getDisplay());
        $this->assertEquals([new MatrixRequest(
            [new Coordinates(48.1486, 17.1077), new Coordinates(48.3774, 17.5872)],
            [new Coordinates(49.2231, 18.7394), new Coordinates(48.3069, 18.0864)],
            Costing::Car,
            150,
        )], $this->matrix->requests);
    }

    public function testOptionsAreHonoured(): void
    {
        $this->matrix->matrix = new Matrix([[1_000]], [[60]]);
        $tester = $this->application->command('tito10047-geo:routing:matrix');

        $tester->execute(['--source' => ['48,17'], '--target' => ['-48.1,-17.1'], '--costing' => 'Bicycle', '--radius' => '0']);

        $tester->assertCommandIsSuccessful();
        $this->assertEquals([new MatrixRequest([new Coordinates(48, 17)], [new Coordinates(-48.1, -17.1)], Costing::Bicycle, 0)], $this->matrix->requests);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'no source' => [['--target' => ['48,17']], 'at least one source'];
        yield 'no target' => [['--source' => ['48,17']], 'at least one target'];
        yield 'point without longitude' => [['--source' => ['48'], '--target' => ['48,17']], 'must be "latitude,longitude"'];
        yield 'longitude out of range' => [['--source' => ['48,17'], '--target' => ['48,181']], 'longitude must be between'];
        yield 'unknown costing' => [['--source' => ['48,17'], '--target' => ['49,18'], '--costing' => 'plane'], 'The costing must be one of'];
        yield 'radius not a number' => [['--source' => ['48,17'], '--target' => ['49,18'], '--radius' => 'far'], '"--radius" must be a whole number'];
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidInput')]
    public function testInvalidInputIsReported(array $input, string $message): void
    {
        $tester = $this->application->command('tito10047-geo:routing:matrix');

        $tester->execute($input);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString($message, preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
        $this->assertSame([], $this->matrix->requests);
    }

    public function testATooLargeMatrixIsInvalid(): void
    {
        $this->matrix->exception = new InvalidArgumentException('The matrix of 51 × 50 = 2550 location pairs exceeds the maximum of 2500.');
        $tester = $this->application->command('tito10047-geo:routing:matrix');

        $tester->execute(['--source' => ['48,17'], '--target' => ['49,18']]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('exceeds the maximum of 2500', preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
    }

    public function testUnavailabilityIsReported(): void
    {
        $this->matrix->exception = new RoutingUnavailableException('Valhalla answered with HTTP 400, error 154: Path distance exceeds the max distance limit: 400000 meters');
        $tester = $this->application->command('tito10047-geo:routing:matrix');

        $tester->execute(['--source' => ['48,17'], '--target' => ['49,18']]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('error 154', preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
    }
}
