<?php

namespace Tito10047\Routing\Valhalla;

use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Exception\RouteNotFoundException;
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
    private string $routeUrl;
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;

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
        private ErrorTranslatorInterface $errorTranslator = new ErrorTranslator(),
    ) {
        $scheme = parse_url($baseUrl, \PHP_URL_SCHEME);
        if (false === filter_var($baseUrl, \FILTER_VALIDATE_URL) || !\in_array(\is_string($scheme) ? strtolower($scheme) : null, ['http', 'https'], true)) {
            throw new InvalidArgumentException(\sprintf('The Valhalla URL must be an absolute HTTP or HTTPS URL, "%s" given.', $baseUrl));
        }
        $this->routeUrl = rtrim($baseUrl, '/').'/route';

        try {
            $this->client = $client ?? Psr18ClientDiscovery::find();
            $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
            $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        } catch (NotFoundException $e) {
            throw new InvalidArgumentException('No PSR-18 HTTP client or PSR-17 factory was given and none could be discovered: install one (e.g. symfony/http-client and nyholm/psr7) or pass them.', 0, $e);
        }
    }

    public function route(RouteRequest $request): array
    {
        $body = $this->encoder->encode($request);
        $httpRequest = $this->requestFactory->createRequest('POST', $this->routeUrl)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        $context = ['url' => $this->routeUrl, 'points' => \count($request->points), 'costing' => $request->costing->value];
        $started = hrtime(true);
        try {
            $response = $this->client->sendRequest($httpRequest);
            $responseBody = (string) $response->getBody();
        } catch (ClientExceptionInterface $e) {
            $what = $e instanceof NetworkExceptionInterface ? 'is unreachable (a network error or a timeout)' : 'could not be asked';
            $exception = new RoutingUnavailableException(\sprintf('Valhalla at %s %s: %s', $this->routeUrl, $what, $e->getMessage()), 0, $e);
            $this->logger->warning('Valhalla is unavailable: {message}', $context + ['message' => $exception->getMessage(), 'exception' => $exception]);

            throw $exception;
        } catch (\RuntimeException $e) {
            // reading the body of a streamed response may fail on its own
            $exception = new RoutingUnavailableException(\sprintf('The answer of Valhalla at %s could not be read: %s', $this->routeUrl, $e->getMessage()), 0, $e);
            $this->logger->warning('Valhalla is unavailable: {message}', $context + ['message' => $exception->getMessage(), 'exception' => $exception]);

            throw $exception;
        }
        $context['status'] = $response->getStatusCode();
        $context['milliseconds'] = (int) round((hrtime(true) - $started) / 1e6);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $exception = $this->errorTranslator->translate($response->getStatusCode(), $responseBody);
            if ($exception instanceof RouteNotFoundException) {
                $this->logger->info('Valhalla found no route: {message}', $context + ['message' => $exception->getMessage()]);
            } else {
                $this->logger->warning('Valhalla is unavailable: {message}', $context + ['message' => $exception->getMessage(), 'exception' => $exception]);
            }

            throw $exception;
        }

        try {
            $routes = $this->parser->parse($responseBody);
        } catch (RoutingUnavailableException $e) {
            $this->logger->warning('Valhalla is unavailable: {message}', $context + ['message' => $e->getMessage(), 'exception' => $e]);

            throw $e;
        }
        $this->logger->debug('Valhalla found {routes} route(s) in {milliseconds} ms.', $context + ['routes' => \count($routes)]);

        return $routes;
    }
}
