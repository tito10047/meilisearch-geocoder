<?php

namespace Tito10047\GeoBundle\Routing;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Tito10047\Routing\Exception\RoutingUnavailableException;

/**
 * Asks Valhalla for its status (GET /status), through a PSR-18 client.
 */
final readonly class ValhallaStatusProvider implements RoutingStatusProviderInterface
{
    private string $statusUrl;

    /**
     * @param string $baseUrl the URL of the Valhalla server, e.g. "http://localhost:8002"
     */
    public function __construct(
        string $baseUrl,
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
    ) {
        $this->statusUrl = rtrim($baseUrl, '/').'/status';
    }

    public function status(): RoutingStatus
    {
        try {
            $response = $this->client->sendRequest($this->requestFactory->createRequest('GET', $this->statusUrl)->withHeader('Accept', 'application/json'));
            $body = (string) $response->getBody();
        } catch (ClientExceptionInterface|\RuntimeException $e) {
            throw new RoutingUnavailableException(\sprintf('Valhalla at %s is unreachable: %s', $this->statusUrl, $e->getMessage()), 0, $e);
        }
        if (200 !== $response->getStatusCode()) {
            throw new RoutingUnavailableException(\sprintf('Valhalla at %s answered with the HTTP status %d.', $this->statusUrl, $response->getStatusCode()));
        }

        try {
            $data = json_decode($body, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RoutingUnavailableException(\sprintf('Valhalla at %s answered with invalid JSON: %s', $this->statusUrl, $e->getMessage()), 0, $e);
        }
        $version = \is_array($data) ? ($data['version'] ?? null) : null;
        if (!\is_array($data) || !\is_string($version)) {
            throw new RoutingUnavailableException(\sprintf('Valhalla at %s answered without its version.', $this->statusUrl));
        }

        $modified = $data['tileset_last_modified'] ?? null;
        $actions = $data['available_actions'] ?? [];

        return new RoutingStatus(
            $version,
            \is_int($modified) ? new \DateTimeImmutable('@'.$modified) : null,
            \is_array($actions) ? array_values(array_filter($actions, is_string(...))) : [],
        );
    }
}
