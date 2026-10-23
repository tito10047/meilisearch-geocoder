<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Nyholm\Psr7\Request;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * A network failure of a PSR-18 client.
 */
final class FakeNetworkException extends \RuntimeException implements NetworkExceptionInterface
{
    public function getRequest(): RequestInterface
    {
        return new Request('GET', 'http://localhost/');
    }
}
