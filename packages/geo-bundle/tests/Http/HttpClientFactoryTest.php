<?php

namespace Tito10047\GeoBundle\Tests\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Tito10047\GeoBundle\Http\HttpClientFactory;
use Tito10047\GeoBundle\Tests\Fixtures\KeepAliveServer;
use Tito10047\Routing\Coordinates;
use Tito10047\Routing\RouteRequest;
use Tito10047\Routing\Valhalla\ValhallaRouter;

/**
 * Talks to a local server that keeps the connections open, as Valhalla does.
 */
final class HttpClientFactoryTest extends TestCase
{
    private static KeepAliveServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = new KeepAliveServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    public function testMeilisearchClientRefusesCompressionByDefault(): void
    {
        $headers = $this->echoedHeaders(HttpClientFactory::meilisearch(5.0));

        $this->assertSame('identity', $headers['accept-encoding'] ?? null);
    }

    public function testMeilisearchClientMayAcceptCompression(): void
    {
        $headers = $this->echoedHeaders(HttpClientFactory::meilisearch(5.0, true));

        $this->assertStringContainsString('gzip', $headers['accept-encoding'] ?? '');
    }

    public function testValhallaClientDoesNotWaitForTheConnectionToClose(): void
    {
        $router = new ValhallaRouter(self::$server->url(), HttpClientFactory::valhalla(10.0));
        $request = new RouteRequest([new Coordinates(48.1486, 17.1077), new Coordinates(48.7164, 21.2611)]);

        $started = hrtime(true);
        // twice: the second request reuses the connection left open
        $router->route($request);
        $routes = $router->route($request);
        $seconds = (hrtime(true) - $started) / 1e9;

        $this->assertSame(400500, $routes[0]->distanceMeters);
        $this->assertLessThan(2.0, $seconds, 'The client waited for the server to close the connection.');
    }

    /**
     * @return array<string, string>
     */
    private function echoedHeaders(ClientInterface $client): array
    {
        $factory = new Psr17Factory();
        $response = $client->sendRequest($factory->createRequest('GET', self::$server->url().'/echo'));
        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, string> $headers */
        $headers = json_decode((string) $response->getBody(), true, 4, \JSON_THROW_ON_ERROR);

        return $headers;
    }
}
