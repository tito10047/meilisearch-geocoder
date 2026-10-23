<?php

namespace Tito10047\Routing\Tests\Fake;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * What a PSR-18 client throws when the server is unreachable or too slow.
 */
final class FakeNetworkException extends \RuntimeException implements NetworkExceptionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        string $message = 'Idle timeout reached',
    ) {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
