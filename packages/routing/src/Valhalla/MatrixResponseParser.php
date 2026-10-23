<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\Matrix;
use Tito10047\Routing\MatrixRequest;

/**
 * Reads the concise answer of Valhalla's `/sources_to_targets`:
 * `{"sources_to_targets": {"durations": [[…]], "distances": [[…]]}, "units": "kilometers"}`,
 * one row per source, null for a pair without a route. Distances are converted to whole metres,
 * durations rounded to whole seconds.
 */
final class MatrixResponseParser implements MatrixResponseParserInterface
{
    private const METERS_PER_UNIT = [
        'kilometers' => 1000.0,
        'miles' => 1609.344,
    ];

    public function parse(string $body, MatrixRequest $request): Matrix
    {
        try {
            $data = json_decode($body, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RoutingUnavailableException(\sprintf('Valhalla answered with invalid JSON: %s', $e->getMessage()), 0, $e);
        }

        try {
            if (!\is_array($data) || !\is_array($data['sources_to_targets'] ?? null)) {
                throw new InvalidArgumentException('An object with "sources_to_targets" was expected.');
            }
            $units = $data['units'] ?? 'kilometers';
            $metersPerUnit = \is_string($units) ? self::METERS_PER_UNIT[$units] ?? null : null;
            if (null === $metersPerUnit) {
                throw new InvalidArgumentException(\sprintf('The units %s are not supported.', json_encode($units)));
            }
            $sources = \count($request->sources);
            $targets = \count($request->targets);
            $matrix = $data['sources_to_targets'];

            return new Matrix(
                $this->rows($matrix['distances'] ?? null, 'distances', $sources, $targets, $metersPerUnit),
                $this->rows($matrix['durations'] ?? null, 'durations', $sources, $targets, 1.0),
            );
        } catch (InvalidArgumentException $e) {
            throw new RoutingUnavailableException(\sprintf('Valhalla answered with an unexpected matrix: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * @return list<list<int|null>>
     */
    private function rows(mixed $rows, string $path, int $sources, int $targets, float $factor): array
    {
        if (!\is_array($rows) || !array_is_list($rows) || \count($rows) !== $sources) {
            throw new InvalidArgumentException(\sprintf('A list of %d row(s) was expected at sources_to_targets.%s.', $sources, $path));
        }

        $values = [];
        foreach ($rows as $source => $row) {
            if (!\is_array($row) || !array_is_list($row) || \count($row) !== $targets) {
                throw new InvalidArgumentException(\sprintf('A list of %d value(s) was expected at sources_to_targets.%s[%d].', $targets, $path, $source));
            }
            $values[] = array_map(static function (mixed $value) use ($path, $source, $factor): ?int {
                if (null === $value) {
                    return null;
                }
                if ((!\is_int($value) && !\is_float($value)) || $value < 0) {
                    throw new InvalidArgumentException(\sprintf('A number of 0 or more or null was expected at sources_to_targets.%s[%d], %s given.', $path, $source, get_debug_type($value)));
                }

                return (int) round($value * $factor);
            }, $row);
        }

        return $values;
    }
}
