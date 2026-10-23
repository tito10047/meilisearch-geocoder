<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * A PSR-18 client sending the requests to a kernel, so that an HTTP client library (a geocoder
 * provider) talks to the application without a server.
 *
 * The raw query string is kept: Request::create() would parse it like PHP and lose the repeated
 * parameters.
 */
final readonly class KernelHttpClient implements ClientInterface
{
    public function __construct(
        private HttpKernelInterface $kernel,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $uri = $request->getUri();
        $symfonyRequest = Request::create((string) $uri, $request->getMethod(), server: array_map(
            static fn (array $values): string => implode(', ', $values),
            array_combine(array_map(static fn (string $name): string => 'HTTP_'.strtoupper(str_replace('-', '_', $name)), array_keys($request->getHeaders())), $request->getHeaders()),
        ));
        $symfonyRequest->server->set('QUERY_STRING', $uri->getQuery());

        $response = $this->kernel->handle($symfonyRequest);

        return new Response($response->getStatusCode(), $response->headers->all(), (string) $response->getContent());
    }
}
