<?php

namespace Tito10047\GeoBundle\Command;

use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Costing;
use Tito10047\Routing\Exception\InvalidArgumentException;

/**
 * Reads the arguments and options shared by the routing commands.
 *
 * @internal
 */
final class RoutingInput
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidArgumentException when the point is not "latitude,longitude"
     */
    public static function point(string $point): Coordinates
    {
        if (1 !== preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/D', $point, $matches)) {
            throw new InvalidArgumentException(\sprintf('A point must be "latitude,longitude" in degrees, e.g. "48.1486,17.1077", "%s" given.', $point));
        }

        return new Coordinates((float) $matches[1], (float) $matches[2]);
    }

    /**
     * @throws InvalidArgumentException when the value is not a whole number
     */
    public static function integer(string $option, mixed $value): int
    {
        if (!\is_string($value) || 1 !== preg_match('/^-?\d+$/D', $value)) {
            throw new InvalidArgumentException(\sprintf('The option "--%s" must be a whole number, "%s" given.', $option, \is_string($value) ? $value : get_debug_type($value)));
        }

        return (int) $value;
    }

    /**
     * @throws InvalidArgumentException when the value is not a costing
     */
    public static function costing(mixed $value): Costing
    {
        $costing = \is_string($value) ? Costing::tryFrom(strtolower($value)) : null;

        return $costing ?? throw new InvalidArgumentException(\sprintf('The costing must be one of %s, "%s" given.', self::costings(), \is_string($value) ? $value : get_debug_type($value)));
    }

    /**
     * The values of the costings, for the help of an option.
     */
    public static function costings(): string
    {
        return implode(', ', array_map(static fn (Costing $costing): string => $costing->value, Costing::cases()));
    }

    public static function kilometres(int $meters): string
    {
        return \sprintf('%.1f km', $meters / 1000);
    }
}
