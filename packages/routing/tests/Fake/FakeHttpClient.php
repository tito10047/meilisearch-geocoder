<?php

namespace Tito10047\Routing\Tests\Fake;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Answers every request with the same response or exception, and remembers the requests.
 */
final class FakeHttpClient implements ClientInterface
{
    /**
     * @var list<RequestInterface>
     */
    public array $requests = [];

    public function __construct(
        private readonly ResponseInterface|ClientExceptionInterface $answer,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        if ($this->answer instanceof ClientExceptionInterface) {
            throw $this->answer;
        }

        return $this->answer;
    }
}
