<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\Exception\RouteNotFoundException;
use Tito10047\Routing\Exception\RoutingUnavailableException;

/**
 * Translates Valhalla's error codes.
 *
 * Only the codes saying that the points were understood and snapped to the roads, but no road
 * connects them, mean that the route does not exist (another engine would not find one either):
 * 170 "Locations are in unconnected regions", 441 "Location is unreachable" and 442 "No path
 * could be found for input" (Stephansplatz in Vienna without a radius).
 *
 * Everything else means that this Valhalla cannot answer, and a fallback engine may:
 * 171 "No suitable edges near location" (a point outside the data of this instance), 154 "Path
 * distance exceeds the max distance limit" (a limit of this instance), the 1xx errors of a
 * request it does not accept, 5xx, and answers that are not Valhalla's JSON (a proxy's page).
 *
 * The matrix (`/sources_to_targets`) is different: it answers 170 when a point lies outside the
 * data (where `/route` answers 171) and gives null for the pairs without a route, so
 * {@see self::forMatrix()} translates every error to "unavailable".
 *
 * @see https://valhalla.github.io/valhalla/api/turn-by-turn/api-reference/#http-status-codes-and-conditions
 */
final readonly class ErrorTranslator implements ErrorTranslatorInterface
{
    public const ROUTE_NOT_FOUND_CODES = [170, 441, 442];

    /**
     * @param list<int> $notFoundCodes the error codes meaning that no route exists
     */
    public function __construct(
        private array $notFoundCodes = self::ROUTE_NOT_FOUND_CODES,
    ) {
    }

    /**
     * The translator of the errors of `/sources_to_targets`: none of them means that no route exists.
     */
    public static function forMatrix(): self
    {
        return new self([]);
    }

    public function translate(int $statusCode, string $body): RouteNotFoundException|RoutingUnavailableException
    {
        try {
            $error = json_decode($body, true, 4, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $error = null;
        }

        if (!\is_array($error) || !\is_int($error['error_code'] ?? null)) {
            return new RoutingUnavailableException(\sprintf('Valhalla answered with HTTP %d without an error code: %s', $statusCode, $this->excerpt($body)));
        }

        $code = $error['error_code'];
        $message = \is_string($error['error'] ?? null) ? $error['error'] : 'no message';
        $description = \sprintf('Valhalla answered with HTTP %d, error %d: %s', $statusCode, $code, $message);

        if (\in_array($code, $this->notFoundCodes, true)) {
            return new RouteNotFoundException($description, $code);
        }

        return new RoutingUnavailableException($description, $code);
    }

    private function excerpt(string $body): string
    {
        $body = trim(preg_replace('/\s+/', ' ', $body) ?? '');
        if ('' === $body) {
            return '(empty body)';
        }

        // cut at a character, not in the middle of a UTF-8 sequence, unless the body is not UTF-8
        $excerpt = 1 === preg_match('/^.{1,200}/su', $body, $match) ? $match[0] : substr($body, 0, 200);

        return \strlen($excerpt) < \strlen($body) ? $excerpt.'…' : $excerpt;
    }
}
