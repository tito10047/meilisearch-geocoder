<?php

namespace Tito10047\Geocoder\Tests\Index\Fixtures;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

final class FakeNetworkException extends \RuntimeException implements NetworkExceptionInterface
{
    public function __construct(private readonly RequestInterface $request)
    {
        parent::__construct('Connection refused');
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
