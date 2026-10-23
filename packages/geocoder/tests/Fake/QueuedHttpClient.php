<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client answering the requests with the given responses or failures in turn, and remembering them.
 */
final class QueuedHttpClient implements ClientInterface
{
    /**
     * @var list<RequestInterface>
     */
    public array $requests = [];

    /**
     * @param list<ResponseInterface|\Throwable> $answers
     */
    public function __construct(
        private array $answers,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $answer = array_shift($this->answers) ?? throw new \LogicException('No answer left for '.$request->getUri());
        if ($answer instanceof \Throwable) {
            throw $answer;
        }

        return $answer;
    }
}
