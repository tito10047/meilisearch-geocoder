<?php

namespace Tito10047\GeoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestApplication;
use Tito10047\GeoBundle\Tests\Fake\FakeRouter;
use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Costing;
use Tito10047\Routing\CountrySegment;
use Tito10047\Routing\Exception\RouteNotFoundException;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\Route;
use Tito10047\Routing\RouteRequest;

final class RoutingRouteCommandTest extends TestCase
{
    private TestApplication $application;
    private FakeRouter $router;

    protected function setUp(): void
    {
        $this->application = new TestApplication(Configs::routing(['radius' => 150]));
        $this->router = $this->application->service('geo.routing.router', FakeRouter::class);
    }

    protected function tearDown(): void
    {
        $this->application->shutdown();
    }

    public function testPrintsTheRoutes(): void
    {
        $this->router->routes = [
            new Route(780_400, 27_000, [], [new CountrySegment('SK', 60_000), new CountrySegment('HU', 400_000), new CountrySegment('HR', 320_400)], [new CountrySegment('HU', 163_000), new CountrySegment('HR', 300_000)]),
            new Route(800_000, 30_600, [], [], []),
        ];
        $tester = $this->application->command('geo:route');

        $tester->execute(['points' => ['48.1486,17.1077', ' 43.5081 , 16.4402 '], '--alternatives' => '2']);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Route 1', $display);
        $this->assertStringContainsString('780.4 km', $display);
        $this->assertStringContainsString('7:30 h', $display);
        $this->assertStringContainsString('SK 60.0 km → HU 400.0 km → HR 320.4 km', $display);
        $this->assertStringContainsString('HU 163.0 km, HR 300.0 km', $display);
        $this->assertStringContainsString('Route 2', $display);
        $this->assertStringContainsString('unknown (no borders in the routing data)', $display);
        $this->assertStringContainsString('none', $display);
        $this->assertStringContainsString(Attribution::OPENSTREETMAP, $display);
        $this->assertEquals([new RouteRequest([new Coordinates(48.1486, 17.1077), new Coordinates(43.5081, 16.4402)], 2, Costing::Car, 150)], $this->router->requests);
    }

    public function testOptionsAreHonoured(): void
    {
        $this->router->routes = [new Route(1000, 60, [], [], [])];
        $tester = $this->application->command('geo:route');

        $tester->execute(['points' => ['48,17', '48.1,17.1', '-48.2,-17.2'], '--costing' => 'Truck', '--radius' => '0']);

        $tester->assertCommandIsSuccessful();
        $this->assertEquals([new RouteRequest([new Coordinates(48, 17), new Coordinates(48.1, 17.1), new Coordinates(-48.2, -17.2)], 1, Costing::Truck, 0)], $this->router->requests);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidInput(): iterable
    {
        yield 'one point' => [['points' => ['48,17']], 'at least two points'];
        yield 'point without longitude' => [['points' => ['48', '48,17']], 'must be "latitude,longitude"'];
        yield 'latitude out of range' => [['points' => ['91,17', '48,17']], 'latitude must be between'];
        yield 'unknown costing' => [['points' => ['48,17', '49,18'], '--costing' => 'plane'], 'The costing must be one of'];
        yield 'alternatives not a number' => [['points' => ['48,17', '49,18'], '--alternatives' => 'many'], '"--alternatives" must be a whole number'];
        yield 'zero alternatives' => [['points' => ['48,17', '49,18'], '--alternatives' => '0'], 'at least 1'];
        yield 'negative radius' => [['points' => ['48,17', '49,18'], '--radius' => '-5'], 'must not be negative'];
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidInput')]
    public function testInvalidInputIsReported(array $input, string $message): void
    {
        $tester = $this->application->command('geo:route');

        $tester->execute($input);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString($message, preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
        $this->assertSame([], $this->router->requests);
    }

    /**
     * @return iterable<string, array{\Throwable}>
     */
    public static function failures(): iterable
    {
        yield 'no route' => [new RouteNotFoundException('No path could be found for input.')];
        yield 'unavailable' => [new RoutingUnavailableException('Valhalla is unreachable.')];
    }

    #[DataProvider('failures')]
    public function testFailureIsReported(\Throwable $exception): void
    {
        $this->router->exception = $exception;
        $tester = $this->application->command('geo:route');

        $tester->execute(['points' => ['48,17', '49,18']]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString($exception->getMessage(), preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
    }
}
