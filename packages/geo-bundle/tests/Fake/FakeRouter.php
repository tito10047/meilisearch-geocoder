<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Tito10047\Routing\Route;
use Tito10047\Routing\RouteRequest;
use Tito10047\Routing\RouterInterface;

/**
 * Answers the routes it is given and records the requests.
 */
final class FakeRouter implements RouterInterface
{
    /** @var list<Route> */
    public array $routes = [];

    public ?\Throwable $exception = null;

    /** @var list<RouteRequest> */
    public array $requests = [];

    public function route(RouteRequest $request): array
    {
        $this->requests[] = $request;
        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }
        if ([] === $this->routes) {
            throw new \LogicException('No route given to the fake.');
        }

        return $this->routes;
    }
}
