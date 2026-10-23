<?php

namespace Tito10047\Routing\Tests\Fake;

use Psr\Http\Client\ClientExceptionInterface;

/**
 * What a PSR-18 client throws when it cannot send a request for another reason.
 */
final class FakeClientException extends \RuntimeException implements ClientExceptionInterface
{
}
