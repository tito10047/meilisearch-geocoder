<?php

namespace Tito10047\Routing\Exception;

/**
 * Thrown when the routing engine cannot answer now: it is unreachable, too slow, failing,
 * answers something unexpected, or a point lies outside the area of its data.
 *
 * Applications with a fallback router switch to it when they catch this exception.
 */
class RoutingUnavailableException extends RuntimeException
{
}
