<?php

namespace Tito10047\GeoBundle\Routing;

/**
 * What the routing engine says about itself.
 */
final readonly class RoutingStatus
{
    /**
     * @param string                  $version          the version of the engine, e.g. "3.5.1"
     * @param \DateTimeImmutable|null $tilesetModified  when its routing data was built; null when not reported
     * @param list<string>            $availableActions the services it offers ("route", "matrix", …)
     */
    public function __construct(
        public string $version,
        public ?\DateTimeImmutable $tilesetModified = null,
        public array $availableActions = [],
    ) {
    }
}
