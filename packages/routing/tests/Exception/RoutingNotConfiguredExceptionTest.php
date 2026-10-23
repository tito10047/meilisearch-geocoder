<?php

namespace Tito10047\Routing\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Exception\RoutingNotConfiguredException;
use Tito10047\Routing\Exception\RoutingUnavailableException;

final class RoutingNotConfiguredExceptionTest extends TestCase
{
    public function testIsCaughtAsAnUnavailableRouting(): void
    {
        $caught = null;
        try {
            throw new RoutingNotConfiguredException('Valhalla is not configured.');
        } catch (RoutingUnavailableException $e) {
            // the fallbacks written for an outage keep working
            $caught = $e;
        }

        $this->assertInstanceOf(RoutingNotConfiguredException::class, $caught);
        $this->assertSame('Valhalla is not configured.', $caught->getMessage());
    }
}
