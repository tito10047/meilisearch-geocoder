<?php

namespace Tito10047\Routing\Valhalla;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\RouteRequest;
use Tito10047\Routing\RouterInterface;

/**
 * Finds routes with a Valhalla server, through any PSR-18 HTTP client.
 *
 * PSR-18 has no timeout: set it on the client (Valhalla answers a long route in 0.1 to 0.3 s
 * when idle, but it queues concurrent requests on its single thread, so allow a few seconds).
 * A timeout reported by the client, like any other network failure, becomes a
 * {@see RoutingUnavailableException}.
 */
final readonly class ValhallaRouter implements RouterInterface
{
    private HttpTransport $transport;

    /**
     * The client and the factories are discovered (php-http/discovery) when not given.
     *
     * @param string $baseUrl the URL of the Valhalla server, e.g. "http://localhost:8002"
     *
     * @throws InvalidArgumentException when the URL is not an absolute HTTP(S) URL, or no client or factory
     *                                  is given and none can be discovered
     */
    public function __construct(
        string $baseUrl,
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private LoggerInterface $logger = new NullLogger(),
        private RequestEncoderInterface $encoder = new RequestEncoder(),
        private ResponseParserInterface $parser = new ResponseParser(),
        ErrorTranslatorInterface $errorTranslator = new ErrorTranslator(),
    ) {
        $this->transport = new HttpTransport($baseUrl, $client, $requestFactory, $streamFactory, $logger, $errorTranslator);
    }

    public function route(RouteRequest $request): array
    {
        [$routes, $context] = $this->transport->post(
            'route',
            $this->encoder->encode($request),
            ['points' => \count($request->points), 'costing' => $request->costing->value],
            $this->parser->parse(...),
        );
        $this->logger->debug('Valhalla found {routes} route(s) in {milliseconds} ms.', $context + ['routes' => \count($routes)]);

        return $routes;
    }
}
