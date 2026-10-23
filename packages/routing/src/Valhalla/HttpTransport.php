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
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Exception\RouteNotFoundException;
use Tito10047\Routing\Exception\RoutingUnavailableException;

/**
 * Posts a JSON body to an action of Valhalla and parses the answer, translating and logging
 * every failure; shared by {@see ValhallaRouter} and {@see ValhallaMatrix}.
 *
 * @internal
 */
final readonly class HttpTransport
{
    private string $baseUrl;
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;

    /**
     * @throws InvalidArgumentException when the URL is not an absolute HTTP(S) URL, or no client or factory
     *                                  is given and none can be discovered
     */
    public function __construct(
        string $baseUrl,
        ?ClientInterface $client,
        ?RequestFactoryInterface $requestFactory,
        ?StreamFactoryInterface $streamFactory,
        private LoggerInterface $logger,
        private ErrorTranslatorInterface $errorTranslator,
    ) {
        $scheme = parse_url($baseUrl, \PHP_URL_SCHEME);
        if (false === filter_var($baseUrl, \FILTER_VALIDATE_URL) || !\in_array(\is_string($scheme) ? strtolower($scheme) : null, ['http', 'https'], true)) {
            throw new InvalidArgumentException(\sprintf('The Valhalla URL must be an absolute HTTP or HTTPS URL, "%s" given.', $baseUrl));
        }
        $this->baseUrl = rtrim($baseUrl, '/');

        try {
            $this->client = $client ?? Psr18ClientDiscovery::find();
            $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
            $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        } catch (NotFoundException $e) {
            throw new InvalidArgumentException('No PSR-18 HTTP client or PSR-17 factory was given and none could be discovered: install one (e.g. symfony/http-client and nyholm/psr7) or pass them.', 0, $e);
        }
    }

    /**
     * @template T
     *
     * @param string               $action  e.g. "route"
     * @param string               $body    the JSON body
     * @param array<string, mixed> $context logged with every message
     * @param callable(string): T  $parse   reads a successful answer, throws RoutingUnavailableException
     *                                      when it is not what was asked for
     *
     * @return array{T, array<string, mixed>} the parsed answer, and the context with the URL, the HTTP status and
     *                                        the milliseconds taken
     *
     * @throws RouteNotFoundException      when the error translator says so
     * @throws RoutingUnavailableException when Valhalla cannot be asked, fails, or answers something unexpected
     */
    public function post(string $action, string $body, array $context, callable $parse): array
    {
        $url = $this->baseUrl.'/'.$action;
        $httpRequest = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        $context = ['url' => $url] + $context;
        $started = hrtime(true);
        try {
            $response = $this->client->sendRequest($httpRequest);
            $responseBody = (string) $response->getBody();
        } catch (ClientExceptionInterface $e) {
            $what = $e instanceof NetworkExceptionInterface ? 'is unreachable (a network error or a timeout)' : 'could not be asked';

            throw $this->unavailable(new RoutingUnavailableException(\sprintf('Valhalla at %s %s: %s', $url, $what, $e->getMessage()), 0, $e), $context);
        } catch (\RuntimeException $e) {
            // reading the body of a streamed response may fail on its own
            throw $this->unavailable(new RoutingUnavailableException(\sprintf('The answer of Valhalla at %s could not be read: %s', $url, $e->getMessage()), 0, $e), $context);
        }
        $context['status'] = $response->getStatusCode();
        $context['milliseconds'] = (int) round((hrtime(true) - $started) / 1e6);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $exception = $this->errorTranslator->translate($response->getStatusCode(), $responseBody);
            if ($exception instanceof RouteNotFoundException) {
                $this->logger->info('Valhalla found no route: {message}', $context + ['message' => $exception->getMessage()]);

                throw $exception;
            }

            throw $this->unavailable($exception, $context);
        }

        try {
            return [$parse($responseBody), $context];
        } catch (RoutingUnavailableException $e) {
            throw $this->unavailable($e, $context);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function unavailable(RoutingUnavailableException $exception, array $context): RoutingUnavailableException
    {
        $this->logger->warning('Valhalla is unavailable: {message}', $context + ['message' => $exception->getMessage(), 'exception' => $exception]);

        return $exception;
    }
}
