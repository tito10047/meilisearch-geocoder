<?php

namespace Tito10047\Routing\Tests\Fixtures;

use Tito10047\Routing\Coordinates;
use Tito10047\Routing\RouteRequest;

/**
 * The scenarios recorded from a local Valhalla in valhalla/ (see the README there): the request
 * each was asked with, its body and the answer.
 */
final class Fixture
{
    public const BRATISLAVA = [48.1486, 17.1077];
    public const KOSICE = [48.7164, 21.2611];
    public const SPLIT = [43.5081, 16.4402];
    public const STEPHANSPLATZ = [48.2082, 16.3738];
    public const PARIS = [48.8566, 2.3522];
    public const BRNO = [49.1951, 16.6068];

    /**
     * @return array<string, RouteRequest>
     */
    public static function requests(): array
    {
        return [
            'ba-kosice-alternatives' => new RouteRequest(self::points(self::BRATISLAVA, self::KOSICE), alternatives: 3),
            'ba-split' => new RouteRequest(self::points(self::BRATISLAVA, self::SPLIT)),
            'stephansplatz-without-radius' => new RouteRequest(self::points(self::STEPHANSPLATZ, self::BRATISLAVA), radiusMeters: 0),
            'stephansplatz-with-radius' => new RouteRequest(self::points(self::STEPHANSPLATZ, self::BRATISLAVA)),
            'outside-data' => new RouteRequest(self::points(self::PARIS, self::BRATISLAVA)),
            'multi-stop' => new RouteRequest(self::points(self::BRATISLAVA, self::STEPHANSPLATZ, self::BRNO), alternatives: 3),
        ];
    }

    public static function request(string $name): RouteRequest
    {
        return self::requests()[$name] ?? throw new \LogicException(\sprintf('There is no fixture "%s".', $name));
    }

    public static function requestBody(string $name): string
    {
        return self::read($name.'.request.json');
    }

    public static function responseBody(string $name): string
    {
        return self::read($name.'.response.json');
    }

    /**
     * @param array{float, float} ...$points
     *
     * @return list<Coordinates>
     */
    public static function points(array ...$points): array
    {
        return array_values(array_map(static fn (array $point): Coordinates => new Coordinates($point[0], $point[1]), $points));
    }

    private static function read(string $file): string
    {
        $content = file_get_contents(__DIR__.'/valhalla/'.$file);
        if (false === $content) {
            throw new \LogicException(\sprintf('The fixture "%s" cannot be read.', $file));
        }

        return rtrim($content, "\n");
    }
}
