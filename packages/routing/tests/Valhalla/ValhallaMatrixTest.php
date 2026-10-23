<?php

namespace Tito10047\Routing\Tests\Valhalla;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Exception\RouteNotFoundException;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\MatrixRequest;
use Tito10047\Routing\Tests\Fake\FakeClientException;
use Tito10047\Routing\Tests\Fake\FakeHttpClient;
use Tito10047\Routing\Tests\Fake\FakeNetworkException;
use Tito10047\Routing\Tests\Fake\RecordingLogger;
use Tito10047\Routing\Tests\Fixtures\Fixture;
use Tito10047\Routing\Valhalla\ErrorTranslator;
use Tito10047\Routing\Valhalla\MatrixRequestEncoder;
use Tito10047\Routing\Valhalla\MatrixResponseParser;
use Tito10047\Routing\Valhalla\ValhallaMatrix;

final class ValhallaMatrixTest extends TestCase
{
    public function testPostsTheRequestToTheMatrixEndpoint(): void
    {
        $client = new FakeHttpClient(self::response(200, Fixture::responseBody('matrix-sk-2x3')));

        $this->matrix($client, 'https://example.com/valhalla/')->matrix(Fixture::matrixRequest('matrix-sk-2x3'));

        $this->assertCount(1, $client->requests);
        $request = $client->requests[0];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://example.com/valhalla/sources_to_targets', (string) $request->getUri());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame(Fixture::requestBody('matrix-sk-2x3'), (string) $request->getBody());
    }

    public function testReturnsTheMatrix(): void
    {
        $logger = new RecordingLogger();
        $client = new FakeHttpClient(self::response(200, Fixture::responseBody('matrix-unreachable')));

        $matrix = $this->matrix($client, logger: $logger)->matrix(Fixture::matrixRequest('matrix-unreachable'));

        $this->assertSame(57_977, $matrix->distanceMeters(0, 0));
        $this->assertNull($matrix->distanceMeters(0, 1));
        $this->assertNull($matrix->durationSeconds(0, 2));
        $this->assertSame(['debug'], $logger->levels());
        $this->assertSame(1, $logger->records[0]['context']['sources']);
        $this->assertSame(3, $logger->records[0]['context']['targets']);
        $this->assertSame(200, $logger->records[0]['context']['status']);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideRecordedErrors(): iterable
    {
        yield 'points too far apart' => ['matrix-too-far', 154];
        yield 'point outside the data' => ['matrix-outside-data', 170];
    }

    #[DataProvider('provideRecordedErrors')]
    public function testARecordedErrorIsUnavailable(string $fixture, int $code): void
    {
        $logger = new RecordingLogger();
        $client = new FakeHttpClient(self::response(400, Fixture::responseBody($fixture)));

        try {
            $this->matrix($client, logger: $logger)->matrix(Fixture::matrixRequest($fixture));
            $this->fail('An exception was expected.');
        } catch (RoutingUnavailableException $e) {
            $this->assertSame($code, $e->getCode());
        }
        $this->assertSame(['warning'], $logger->levels());
    }

    public function testANotFoundOfACustomTranslatorIsUnavailable(): void
    {
        $client = new FakeHttpClient(self::response(400, Fixture::responseBody('matrix-outside-data')));
        $factory = new Psr17Factory();
        $matrix = new ValhallaMatrix('http://localhost:8002', $client, $factory, $factory, errorTranslator: new ErrorTranslator());

        try {
            $matrix->matrix(Fixture::matrixRequest('matrix-outside-data'));
            $this->fail('An exception was expected.');
        } catch (RoutingUnavailableException $e) {
            $this->assertSame(170, $e->getCode());
            $this->assertInstanceOf(RouteNotFoundException::class, $e->getPrevious());
        }
    }

    #[DataProvider('provideFailures')]
    public function testThrowsUnavailableWhenValhallaFails(ResponseInterface|ClientExceptionInterface $answer, string $message): void
    {
        $logger = new RecordingLogger();

        try {
            $this->matrix(new FakeHttpClient($answer), logger: $logger)->matrix(Fixture::matrixRequest('matrix-sk-2x3'));
            $this->fail('An exception was expected.');
        } catch (RoutingUnavailableException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
        $this->assertSame(['warning'], $logger->levels());
    }

    /**
     * @return iterable<string, array{ResponseInterface|ClientExceptionInterface, string}>
     */
    public static function provideFailures(): iterable
    {
        $request = (new Psr17Factory())->createRequest('POST', 'http://localhost:8002/sources_to_targets');

        yield 'timeout' => [new FakeNetworkException($request), 'sources_to_targets is unreachable (a network error or a timeout)'];
        yield 'other client failure' => [new FakeClientException('Too many redirects'), 'could not be asked: Too many redirects'];
        yield 'too many pairs for the server' => [self::response(400, '{"error_code":150,"error":"Exceeded max locations: 2500.000000","status_code":400,"status":"Bad Request"}'), 'error 150: Exceeded max locations'];
        yield 'proxy error' => [self::response(502, '<html>Bad Gateway</html>'), 'HTTP 502 without an error code'];
        yield 'answer of another size' => [self::response(200, Fixture::responseBody('matrix-unreachable')), 'unexpected matrix'];
    }

    public function testRefusesAMatrixLargerThanTheMaximumWithoutAsking(): void
    {
        $client = new FakeHttpClient(self::response(200, Fixture::responseBody('matrix-sk-2x3')));
        $factory = new Psr17Factory();
        $matrix = new ValhallaMatrix('http://localhost:8002', $client, $factory, $factory, maxLocationPairs: 5);

        try {
            $matrix->matrix(Fixture::matrixRequest('matrix-sk-2x3'));
            $this->fail('An exception was expected.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The matrix of 2 × 3 = 6 location pairs exceeds the maximum of 5.', $e->getMessage());
        }
        $this->assertSame([], $client->requests);
    }

    public function testRefusesTheDefaultMaximumOfValhalla(): void
    {
        $points = array_fill(0, 50, Fixture::points(Fixture::BRATISLAVA)[0]);
        $client = new FakeHttpClient(self::response(200, ''));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('51 × 50 = 2550 location pairs exceeds the maximum of 2500');

        $this->matrix($client)->matrix(new MatrixRequest([...$points, $points[0]], $points));
    }

    public function testRejectsAMaximumBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ValhallaMatrix('http://localhost:8002', new FakeHttpClient(new Response()), maxLocationPairs: 0);
    }

    public function testRejectsAnInvalidBaseUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->matrix(new FakeHttpClient(new Response()), 'localhost:8002');
    }

    public function testDiscoversTheClientAndTheFactories(): void
    {
        // symfony/http-client and nyholm/psr7 are installed for the tests; nothing listens on port 9
        $matrix = new ValhallaMatrix('http://127.0.0.1:9', encoder: new MatrixRequestEncoder(), parser: new MatrixResponseParser());

        $this->expectException(RoutingUnavailableException::class);
        $this->expectExceptionMessage('Valhalla at http://127.0.0.1:9/sources_to_targets is unreachable');

        $matrix->matrix(Fixture::matrixRequest('matrix-sk-2x3'));
    }

    private function matrix(FakeHttpClient $client, string $url = 'http://localhost:8002', ?RecordingLogger $logger = null): ValhallaMatrix
    {
        $factory = new Psr17Factory();

        return new ValhallaMatrix($url, $client, $factory, $factory, $logger ?? new RecordingLogger());
    }

    private static function response(int $status, string $body): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json;charset=utf-8'], $body);
    }
}
