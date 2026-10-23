<?php

namespace Tito10047\GeoBundle\Tests\Routing;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\GeoBundle\Http\HttpClientFactory;
use Tito10047\GeoBundle\Routing\RoutingStatus;
use Tito10047\GeoBundle\Routing\ValhallaStatusProvider;
use Tito10047\GeoBundle\Tests\Fake\FakeHttpClient;
use Tito10047\GeoBundle\Tests\Fake\FakeNetworkException;
use Tito10047\GeoBundle\Tests\Fixtures\KeepAliveServer;
use Tito10047\Routing\Exception\RoutingUnavailableException;

final class ValhallaStatusProviderTest extends TestCase
{
    public function testReadsTheStatus(): void
    {
        $client = new FakeHttpClient(new Response(200, [], '{"version":"3.5.1","tileset_last_modified":1761177600,"available_actions":["status","route",7]}'));

        $status = (new ValhallaStatusProvider('http://valhalla:8002/', $client, new Psr17Factory()))->status();

        $this->assertEquals(new RoutingStatus('3.5.1', new \DateTimeImmutable('@1761177600'), ['status', 'route']), $status);
        $this->assertSame('GET', $client->requests[0]->getMethod());
        $this->assertSame('http://valhalla:8002/status', (string) $client->requests[0]->getUri());
    }

    public function testDetailsAreOptional(): void
    {
        $client = new FakeHttpClient(new Response(200, [], '{"version":"3.5.1"}'));

        $this->assertEquals(new RoutingStatus('3.5.1'), (new ValhallaStatusProvider('http://valhalla:8002', $client, new Psr17Factory()))->status());
    }

    /**
     * @return iterable<string, array{Response|\Throwable, string}>
     */
    public static function failures(): iterable
    {
        yield 'network error' => [new FakeNetworkException('Connection refused'), 'is unreachable: Connection refused'];
        yield 'server error' => [new Response(503), 'HTTP status 503'];
        yield 'invalid JSON' => [new Response(200, [], 'Valhalla'), 'invalid JSON'];
        yield 'no version' => [new Response(200, [], '{"available_actions":[]}'), 'without its version'];
        yield 'not an object' => [new Response(200, [], '"3.5.1"'), 'without its version'];
    }

    #[DataProvider('failures')]
    public function testFailureMeansUnavailable(Response|\Throwable $answer, string $message): void
    {
        $this->expectException(RoutingUnavailableException::class);
        $this->expectExceptionMessage($message);

        (new ValhallaStatusProvider('http://valhalla:8002', new FakeHttpClient($answer), new Psr17Factory()))->status();
    }

    public function testWorksWithTheDefaultClientOverAKeptOpenConnection(): void
    {
        $server = new KeepAliveServer();
        try {
            $status = (new ValhallaStatusProvider($server->url(), HttpClientFactory::valhalla(5.0), new Psr17Factory()))->status();
        } finally {
            $server->stop();
        }

        $this->assertSame('3.5.1', $status->version);
    }
}
