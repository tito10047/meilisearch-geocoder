<?php

namespace Tito10047\GeocoderBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\GreeterInterface;

/**
 * Greets people, in plain text.
 *
 * Routed by the "tito10047_geocoder_hello" route.
 */
final readonly class HelloController
{
    public function __construct(
        private GreeterInterface $greeter,
    ) {
    }

    public function __invoke(string $name): Response
    {
        try {
            $greeting = $this->greeter->greet($name);
        } catch (InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        return new Response($greeting, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
