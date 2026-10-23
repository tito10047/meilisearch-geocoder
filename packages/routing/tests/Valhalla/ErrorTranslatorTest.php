<?php

namespace Tito10047\Routing\Tests\Valhalla;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Exception\RouteNotFoundException;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\Tests\Fixtures\Fixture;
use Tito10047\Routing\Valhalla\ErrorTranslator;

final class ErrorTranslatorTest extends TestCase
{
    public function testARecordedPointWithoutAPathIsARouteNotFound(): void
    {
        $exception = (new ErrorTranslator())->translate(400, Fixture::responseBody('stephansplatz-without-radius'));

        $this->assertInstanceOf(RouteNotFoundException::class, $exception);
        $this->assertSame(442, $exception->getCode());
        $this->assertSame('Valhalla answered with HTTP 400, error 442: No path could be found for input', $exception->getMessage());
    }

    public function testARecordedPointOutsideTheDataIsUnavailable(): void
    {
        $exception = (new ErrorTranslator())->translate(400, Fixture::responseBody('outside-data'));

        $this->assertInstanceOf(RoutingUnavailableException::class, $exception);
        $this->assertSame(171, $exception->getCode());
        $this->assertSame('Valhalla answered with HTTP 400, error 171: No suitable edges near location', $exception->getMessage());
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideErrors')]
    public function testTranslatesTheErrorCode(int $statusCode, string $body, string $class, int $code): void
    {
        $exception = (new ErrorTranslator())->translate($statusCode, $body);

        $this->assertInstanceOf($class, $exception);
        $this->assertSame($code, $exception->getCode());
    }

    /**
     * @return iterable<string, array{int, string, class-string, int}>
     */
    public static function provideErrors(): iterable
    {
        yield 'unconnected regions' => [400, self::error(170, 'Locations are in unconnected regions. Go check/edit the map at osm.org'), RouteNotFoundException::class, 170];
        yield 'unreachable location' => [400, self::error(441, 'Location is unreachable'), RouteNotFoundException::class, 441];
        yield 'no path' => [400, self::error(442, 'No path could be found for input'), RouteNotFoundException::class, 442];
        yield 'outside the data' => [400, self::error(171, 'No suitable edges near location'), RoutingUnavailableException::class, 171];
        yield 'too far' => [400, self::error(154, 'Path distance exceeds the max distance limit'), RoutingUnavailableException::class, 154];
        yield 'invalid request' => [400, self::error(100, 'Failed to parse json request'), RoutingUnavailableException::class, 100];
        yield 'server error' => [500, self::error(499, 'Unknown'), RoutingUnavailableException::class, 499];
        yield 'proxy page' => [502, '<html><body>Bad Gateway</body></html>', RoutingUnavailableException::class, 0];
        yield 'empty body' => [503, '', RoutingUnavailableException::class, 0];
        yield 'JSON without a code' => [404, '{"error":"Not found"}', RoutingUnavailableException::class, 0];
        yield 'code not a number' => [400, '{"error_code":"442"}', RoutingUnavailableException::class, 0];
        yield 'JSON list' => [400, '[442]', RoutingUnavailableException::class, 0];
    }

    public function testDescribesAnAnswerWithoutACode(): void
    {
        $exception = (new ErrorTranslator())->translate(502, "<html>\n  <body>Bad Gateway</body>\n</html>");

        $this->assertSame('Valhalla answered with HTTP 502 without an error code: <html> <body>Bad Gateway</body> </html>', $exception->getMessage());
    }

    public function testDescribesAnEmptyAnswer(): void
    {
        $this->assertStringEndsWith('(empty body)', (new ErrorTranslator())->translate(503, " \n")->getMessage());
    }

    public function testShortensALongAnswerAtACharacter(): void
    {
        $exception = (new ErrorTranslator())->translate(502, str_repeat('č', 300));

        $this->assertStringEndsWith(': '.str_repeat('č', 200).'…', $exception->getMessage());
    }

    public function testShortensALongAnswerThatIsNotUtf8(): void
    {
        $exception = (new ErrorTranslator())->translate(502, str_repeat("\xFF", 300));

        $this->assertStringEndsWith(': '.str_repeat("\xFF", 200).'…', $exception->getMessage());
    }

    public function testNamesAnErrorWithoutAMessage(): void
    {
        $this->assertStringEndsWith('error 442: no message', (new ErrorTranslator())->translate(400, '{"error_code":442}')->getMessage());
    }

    private static function error(int $code, string $message): string
    {
        return json_encode(['error_code' => $code, 'error' => $message, 'status_code' => 400, 'status' => 'Bad Request'], \JSON_THROW_ON_ERROR);
    }
}
