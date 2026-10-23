<?php

namespace Tito10047\Routing\Exception;

/**
 * Thrown when the routing is switched off by its configuration (no routing engine is configured),
 * not because a configured one is down.
 *
 * It extends RoutingUnavailableException, so a fallback catching that one still works; catch this
 * one first to tell a deliberately missing routing engine from an outage (e.g. not to alert).
 */
final class RoutingNotConfiguredException extends RoutingUnavailableException
{
}
