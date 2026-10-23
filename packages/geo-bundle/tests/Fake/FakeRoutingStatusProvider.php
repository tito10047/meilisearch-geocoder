<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Tito10047\GeoBundle\Routing\RoutingStatus;
use Tito10047\GeoBundle\Routing\RoutingStatusProviderInterface;

/**
 * Answers the status it is given.
 */
final class FakeRoutingStatusProvider implements RoutingStatusProviderInterface
{
    public ?RoutingStatus $status = null;

    public ?\Throwable $exception = null;

    public function status(): RoutingStatus
    {
        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return $this->status ?? throw new \LogicException('No status given to the fake.');
    }
}
