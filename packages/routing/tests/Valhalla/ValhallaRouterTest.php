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
use Tito10047\Routing\RouteRequest;
use Tito10047\Routing\Tests\Fake\FakeClientException;
use Tito10047\Routing\Tests\Fake\FakeHttpClient;
use Tito10047\Routing\Tests\Fake\FakeNetworkException;
use Tito10047\Routing\Tests\Fake\RecordingLogger;
use Tito10047\Routing\Tests\Fixtures\Fixture;
use Tito10047\Routing\Valhalla\ValhallaRouter;

final class ValhallaRouterTest extends TestCase
{
    public function testPostsTheRequestToTheRouteEndpoint(): void
    {
        $client = new FakeHttpClient(self::response(200, Fixture::responseBody('ba-split')));

        $this->router($client, 'http://valhalla:8002/')->route(Fixture::request('ba-split'));

        $this->assertCount(1, $client->requests);
        $request = $client->requests[0];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('http://valhalla:8002/route', (string) $request->getUri());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
        $this->assertSame(Fixture::requestBody('ba-split'), (string) $request->getBody());
    }

    public function testKeepsThePathOfTheBaseUrl(): void
    {
        $client = new FakeHttpClient(self::response(200, Fixture::responseBody('ba-split')));

        $this->router($client, 'https://example.com/valhalla')->route(Fixture::request('ba-split'));

        $this->assertSame('https://example.com/valhalla/route', (string) $client->requests[0]->getUri());
    }

    public function testReturnsTheRoutesBestFirst(): void
    {
        $logger = new RecordingLogger();
        $client = new FakeHttpClient(self::response(200, Fixture::responseBody('ba-kosice-alternatives')));

        $routes = $this->router($client, logger: $logger)->route(Fixture::request('ba-kosice-alternatives'));

        $this->assertCount(3, $routes);
        $this->assertSame(441442, $routes[0]->distanceMeters);
        $this->assertSame(['debug'], $logger->levels());
        $this->assertSame(3, $logger->records[0]['context']['routes']);
        $this->assertSame(200, $logger->records[0]['context']['status']);
    }

    public function testThrowsRouteNotFoundWhenNoPathConnectsThePoints(): void
    {
        $logger = new RecordingLogger();
        $client = new FakeHttpClient(self::response(400, Fixture::responseBody('stephansplatz-without-radius')));

        try {
            $this->router($client, logger: $logger)->route(Fixture::request('stephansplatz-without-radius'));
            $this->fail('An exception was expected.');
        } catch (RouteNotFoundException $e) {
            $this->assertSame(442, $e->getCode());
        }
        $this->assertSame(['info'], $logger->levels());
    }

    public function testThrowsUnavailableForAPointOutsideTheData(): void
    {
        $logger = new RecordingLogger();
        $client = new FakeHttpClient(self::response(400, Fixture::responseBody('outside-data')));

        try {
            $this->router($client, logger: $logger)->route(Fixture::request('outside-data'));
            $this->fail('An exception was expected.');
        } catch (RoutingUnavailableException $e) {
            $this->assertSame(171, $e->getCode());
        }
        $this->assertSame(['warning'], $logger->levels());
        $this->assertSame($e, $logger->records[0]['context']['exception']);
    }

    #[DataProvider('provideFailures')]
    public function testThrowsUnavailableWhenValhallaFails(ResponseInterface|ClientExceptionInterface $answer, string $message): void
    {
        $logger = new RecordingLogger();

        try {
            $this->router(new FakeHttpClient($answer), logger: $logger)->route(Fixture::request('ba-split'));
            $this->fail('An exception was expected.');
        } catch (RoutingUnavailableException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
            if ($answer instanceof \Throwable) {
                $this->assertSame($answer, $e->getPrevious());
            }
        }
        $this->assertSame(['warning'], $logger->levels());
    }

    /**
     * @return iterable<string, array{ResponseInterface|ClientExceptionInterface, string}>
     */
    public static function provideFailures(): iterable
    {
        $request = (new Psr17Factory())->createRequest('POST', 'http://localhost:8002/route');

        yield 'timeout' => [new FakeNetworkException($request), 'is unreachable (a network error or a timeout): Idle timeout reached'];
        yield 'connection refused' => [new FakeNetworkException($request, 'Connection refused'), 'Connection refused'];
        yield 'other client failure' => [new FakeClientException('Too many redirects'), 'could not be asked: Too many redirects'];
        yield 'server error' => [self::response(500, ''), 'HTTP 500 without an error code'];
        yield 'proxy error' => [self::response(502, '<html>Bad Gateway</html>'), 'HTTP 502 without an error code: <html>Bad Gateway</html>'];
        yield 'invalid JSON' => [self::response(200, '<html>'), 'invalid JSON'];
        yield 'unexpected JSON' => [self::response(200, '{"trip":{}}'), 'unexpected route'];
    }

    public function testThrowsUnavailableWhenTheBodyCannotBeRead(): void
    {
        $body = (new Psr17Factory())->createStream('{}');
        $body->detach();
        $client = new FakeHttpClient((new Response(200))->withBody($body));

        $this->expectException(RoutingUnavailableException::class);
        $this->expectExceptionMessage('could not be read');

        $this->router($client)->route(Fixture::request('ba-split'));
    }

    #[DataProvider('provideInvalidUrls')]
    public function testRejectsAnInvalidBaseUrl(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->router(new FakeHttpClient(new Response()), $url);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidUrls(): iterable
    {
        yield 'empty' => [''];
        yield 'relative' => ['/valhalla'];
        yield 'host only' => ['localhost:8002'];
        yield 'other scheme' => ['ftp://localhost:8002'];
    }

    public function testDiscoversTheClientAndTheFactories(): void
    {
        // symfony/http-client and nyholm/psr7 are installed for the tests; nothing listens on port 9
        $router = new ValhallaRouter('http://127.0.0.1:9');

        $this->expectException(RoutingUnavailableException::class);
        $this->expectExceptionMessage('Valhalla at http://127.0.0.1:9/route is unreachable');

        $router->route(Fixture::request('ba-split'));
    }

    public function testAcceptsAnUpperCaseScheme(): void
    {
        $client = new FakeHttpClient(self::response(200, Fixture::responseBody('ba-split')));

        $this->router($client, 'HTTP://localhost:8002')->route(new RouteRequest(Fixture::points(Fixture::BRATISLAVA, Fixture::SPLIT)));

        $this->assertCount(1, $client->requests);
    }

    private function router(FakeHttpClient $client, string $url = 'http://localhost:8002', ?RecordingLogger $logger = null): ValhallaRouter
    {
        $factory = new Psr17Factory();

        return new ValhallaRouter($url, $client, $factory, $factory, $logger ?? new RecordingLogger());
    }

    private static function response(int $status, string $body): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json;charset=utf-8'], $body);
    }
}
