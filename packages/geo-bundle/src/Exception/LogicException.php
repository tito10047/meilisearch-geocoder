<?php

namespace Tito10047\GeoBundle\Exception;

/**
 * The application lacks something its configuration asks for (a package, a PHP extension).
 */
final class LogicException extends \LogicException implements ExceptionInterface
{
}
