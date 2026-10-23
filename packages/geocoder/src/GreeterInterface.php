<?php

namespace Tito10047\Geocoder;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * Greets people.
 *
 * With the Symfony bundle, type-hint this interface to get the "tito10047_geocoder.greeter" service autowired.
 */
interface GreeterInterface
{
    /**
     * Returns the greeting of the given name, e.g. "Hello Jane!".
     *
     * @throws InvalidArgumentException when the name is empty
     */
    public function greet(string $name): string;
}
