<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\CountrySegment;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\Geometry\PolylineDecoder;
use Tito10047\Routing\Geometry\PolylineDecoderInterface;
use Tito10047\Routing\Route;

/**
 * Reads Valhalla's `trip` and `alternates[].trip` into routes.
 *
 * The legs (one between each two points of the request) are joined: their shapes into one line,
 * their countries into one sequence (the country of a stop is not listed twice), their tolls into
 * one sum per country.
 */
final readonly class ResponseParser implements ResponseParserInterface
{
    private const METERS_PER_UNIT = [
        'kilometers' => 1000.0,
        'miles' => 1609.344,
    ];

    public function __construct(
        private PolylineDecoderInterface $decoder = new PolylineDecoder(6),
        private CountryLocatorInterface $countryLocator = new AdminCrossingCountryLocator(),
        private TollLocatorInterface $tollLocator = new ManeuverTollLocator(),
    ) {
    }

    public function parse(string $body): array
    {
        try {
            $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RoutingUnavailableException(\sprintf('Valhalla answered with invalid JSON: %s', $e->getMessage()), 0, $e);
        }

        try {
            $data = $this->object($data, 'the answer');
            $routes = [$this->route($this->object($data['trip'] ?? null, 'trip'), 'trip')];
            foreach ($this->list($data['alternates'] ?? [], 'alternates') as $index => $alternate) {
                $path = \sprintf('alternates[%d].trip', $index);
                $routes[] = $this->route($this->object($this->object($alternate, "alternates[$index]")['trip'] ?? null, $path), $path);
            }
        } catch (InvalidArgumentException $e) {
            throw new RoutingUnavailableException(\sprintf('Valhalla answered with an unexpected route: %s', $e->getMessage()), 0, $e);
        }

        return $routes;
    }

    /**
     * @param array<mixed> $trip
     */
    private function route(array $trip, string $path): Route
    {
        $units = $this->string($trip['units'] ?? 'kilometers', "$path.units");
        $metersPerUnit = self::METERS_PER_UNIT[$units] ?? throw new InvalidArgumentException(\sprintf('The units "%s" at %s.units are not supported.', $units, $path));
        $summary = $this->object($trip['summary'] ?? null, "$path.summary");
        $legs = $this->list($trip['legs'] ?? null, "$path.legs");
        if ([] === $legs) {
            throw new InvalidArgumentException(\sprintf('The trip at %s has no legs.', $path));
        }

        $geometry = [];
        /** @var list<array{string, float}> $countries */
        $countries = [];
        /** @var array<string, float> $tolled */
        $tolled = [];
        foreach ($legs as $index => $legData) {
            $leg = $this->leg($this->object($legData, "$path.legs[$index]"), "$path.legs[$index]", $metersPerUnit);

            $stretches = $this->countryLocator->locate($leg);
            foreach ($stretches as $stretch) {
                $last = array_key_last($countries);
                if (null !== $last && $countries[$last][0] === $stretch->country) {
                    $countries[$last][1] += $stretch->lengthMeters();
                } else {
                    $countries[] = [$stretch->country, $stretch->lengthMeters()];
                }
            }
            foreach ($this->tollLocator->locate($leg, $stretches) as $stretch) {
                $tolled[$stretch->country] = ($tolled[$stretch->country] ?? 0.0) + $stretch->lengthMeters();
            }

            $shape = $leg->shape;
            // a leg starts where the previous one ended
            $previous = $geometry[\count($geometry) - 1] ?? null;
            if (null !== $previous && $previous->latitude === $shape[0]->latitude && $previous->longitude === $shape[0]->longitude) {
                array_shift($shape);
            }
            array_push($geometry, ...$shape);
        }

        return new Route(
            (int) round($this->number($summary['length'] ?? null, "$path.summary.length") * $metersPerUnit),
            (int) round($this->number($summary['time'] ?? null, "$path.summary.time")),
            $geometry,
            array_map(static fn (array $country): CountrySegment => new CountrySegment($country[0], (int) round($country[1])), $countries),
            array_map(static fn (string $country, float $meters): CountrySegment => new CountrySegment($country, (int) round($meters)), array_keys($tolled), $tolled),
        );
    }

    /**
     * @param array<mixed> $leg
     */
    private function leg(array $leg, string $path, float $metersPerUnit): Leg
    {
        $summary = $this->object($leg['summary'] ?? null, "$path.summary");

        $countries = [];
        foreach ($this->list($summary['admins'] ?? [], "$path.summary.admins") as $index => $admin) {
            $countries[] = $this->string($this->object($admin, "$path.summary.admins[$index]")['country_code'] ?? '', "$path.summary.admins[$index].country_code");
        }

        $crossings = [];
        foreach ($this->list($summary['admin_crossings'] ?? [], "$path.summary.admin_crossings") as $index => $crossing) {
            $at = "$path.summary.admin_crossings[$index]";
            $crossing = $this->object($crossing, $at);
            $crossings[] = new AdminCrossing(
                $this->int($crossing['from_admin_index'] ?? null, "$at.from_admin_index"),
                $this->int($crossing['to_admin_index'] ?? null, "$at.to_admin_index"),
                $this->span($crossing, $at),
            );
        }

        $tolled = [];
        foreach ($this->list($leg['maneuvers'] ?? [], "$path.maneuvers") as $index => $maneuver) {
            $maneuver = $this->object($maneuver, "$path.maneuvers[$index]");
            // Valhalla leaves the key out when the road is not tolled
            if (true === ($maneuver['toll'] ?? false)) {
                $tolled[] = $this->span($maneuver, "$path.maneuvers[$index]");
            }
        }

        return new Leg(
            $this->decoder->decode($this->string($leg['shape'] ?? null, "$path.shape")),
            $this->number($summary['length'] ?? null, "$path.summary.length") * $metersPerUnit,
            $countries,
            $crossings,
            $tolled,
        );
    }

    /**
     * @param array<mixed> $object
     */
    private function span(array $object, string $path): ShapeSpan
    {
        return new ShapeSpan(
            $this->int($object['begin_shape_index'] ?? null, "$path.begin_shape_index"),
            $this->int($object['end_shape_index'] ?? null, "$path.end_shape_index"),
        );
    }

    /**
     * @return array<mixed>
     */
    private function object(mixed $value, string $path): array
    {
        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw new InvalidArgumentException(\sprintf('An object was expected at %s, %s given.', $path, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $value, string $path): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException(\sprintf('A list was expected at %s, %s given.', $path, get_debug_type($value)));
        }

        return $value;
    }

    private function string(mixed $value, string $path): string
    {
        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('A string was expected at %s, %s given.', $path, get_debug_type($value)));
        }

        return $value;
    }

    private function int(mixed $value, string $path): int
    {
        if (!\is_int($value)) {
            throw new InvalidArgumentException(\sprintf('An integer was expected at %s, %s given.', $path, get_debug_type($value)));
        }

        return $value;
    }

    private function number(mixed $value, string $path): float
    {
        if (!\is_int($value) && !\is_float($value)) {
            throw new InvalidArgumentException(\sprintf('A number was expected at %s, %s given.', $path, get_debug_type($value)));
        }

        return (float) $value;
    }
}
