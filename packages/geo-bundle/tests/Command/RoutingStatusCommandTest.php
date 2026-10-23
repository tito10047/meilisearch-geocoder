<?php

namespace Tito10047\GeoBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Routing\RoutingStatus;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestApplication;
use Tito10047\GeoBundle\Tests\Fake\FakeRoutingStatusProvider;
use Tito10047\Routing\Exception\RoutingUnavailableException;

final class RoutingStatusCommandTest extends TestCase
{
    private TestApplication $application;
    private FakeRoutingStatusProvider $statusProvider;

    protected function setUp(): void
    {
        $this->application = new TestApplication(Configs::routing());
        $this->statusProvider = $this->application->service('geo.routing.status_provider', FakeRoutingStatusProvider::class);
    }

    protected function tearDown(): void
    {
        $this->application->shutdown();
    }

    public function testPrintsTheStatus(): void
    {
        $this->statusProvider->status = new RoutingStatus('3.5.1', new \DateTimeImmutable('2026-10-22 12:00:00 UTC'), ['status', 'route']);
        $tester = $this->application->command('geo:route:status');

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        $this->assertStringContainsString('3.5.1', $display);
        $this->assertStringContainsString('2026-10-22 12:00:00', $display);
        $this->assertStringContainsString('status, route', $display);
        $this->assertStringContainsString('Valhalla is reachable.', $display);
        $this->assertStringContainsString(Attribution::OPENSTREETMAP, $display);
    }

    public function testUnknownDetailsArePrintedAsSuch(): void
    {
        $this->statusProvider->status = new RoutingStatus('3.5.1');
        $tester = $this->application->command('geo:route:status');

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame(2, substr_count($tester->getDisplay(), 'unknown'));
    }

    public function testUnreachableValhallaFails(): void
    {
        $this->statusProvider->exception = new RoutingUnavailableException('Valhalla is unreachable.');
        $tester = $this->application->command('geo:route:status');

        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Valhalla is unreachable.', $tester->getDisplay());
    }

    public function testNotConfiguredValhallaFails(): void
    {
        $application = new TestApplication(Configs::routing(['url' => Configs::EMPTY_ENV]), []);
        try {
            $tester = $application->command('geo:route:status');

            $tester->execute([]);

            $this->assertSame(Command::FAILURE, $tester->getStatusCode());
            $this->assertStringContainsString('Valhalla is not configured: geo.routing.url is empty.', $tester->getDisplay());
        } finally {
            $application->shutdown();
        }
    }
}
