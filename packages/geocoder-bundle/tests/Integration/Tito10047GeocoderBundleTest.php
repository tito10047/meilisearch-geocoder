<?php

namespace Tito10047\GeocoderBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tito10047\Geocoder\GreeterInterface;

/**
 * Boots the test application, which registers the bundle.
 */
final class Tito10047GeocoderBundleTest extends KernelTestCase
{
    public function testGreeterCanBeAutowired(): void
    {
        $greeter = self::getContainer()->get('test.'.GreeterInterface::class);

        $this->assertInstanceOf(GreeterInterface::class, $greeter);
        $this->assertSame('Hello Jane!', $greeter->greet('Jane'));
    }
}
