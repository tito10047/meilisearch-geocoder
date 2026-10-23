<?php

namespace Tito10047\Routing\Tests\Fixtures;

use Tito10047\Routing\Coordinates;
use Tito10047\Routing\MatrixRequest;
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
    public const TRNAVA = [48.3774, 17.5872];
    public const NITRA = [48.3069, 18.0864];
    public const ZILINA = [49.2231, 18.7394];
    public const BANSKA_BYSTRICA = [48.7363, 19.1462];
    public const KRAKOW = [50.0614, 19.9366];

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

    /**
     * The matrices, recorded from the tiles of Slovakia only unless the name says otherwise.
     *
     * @return array<string, MatrixRequest>
     */
    public static function matrixRequests(): array
    {
        return [
            'matrix-sk-2x3' => new MatrixRequest(self::points(self::BRATISLAVA, self::TRNAVA), self::points(self::ZILINA, self::BANSKA_BYSTRICA, self::NITRA)),
            'matrix-unreachable' => new MatrixRequest(self::points(self::BRATISLAVA), self::points(self::TRNAVA, self::STEPHANSPLATZ, self::KOSICE)),
            'matrix-too-far' => new MatrixRequest(self::points(self::BRATISLAVA), self::points(self::PARIS)),
            'matrix-outside-data' => new MatrixRequest(self::points(self::KRAKOW, self::BRATISLAVA), self::points(self::ZILINA)),
            'matrix-cross-border-all' => new MatrixRequest(self::points(self::BRATISLAVA, self::BRNO), self::points(self::STEPHANSPLATZ, self::BRNO), avoidTolls: true),
        ];
    }

    public static function matrixRequest(string $name): MatrixRequest
    {
        return self::matrixRequests()[$name] ?? throw new \LogicException(\sprintf('There is no matrix fixture "%s".', $name));
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
