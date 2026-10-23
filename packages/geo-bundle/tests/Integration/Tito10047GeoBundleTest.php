<?php

namespace Tito10047\GeoBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tito10047\GeoBundle\Tito10047GeoBundle;

/**
 * Boots the test application, which registers the bundle.
 */
final class Tito10047GeoBundleTest extends KernelTestCase
{
    public function testBundleIsRegistered(): void
    {
        $kernel = self::bootKernel();

        $this->assertInstanceOf(Tito10047GeoBundle::class, $kernel->getBundle('Tito10047GeoBundle'));
    }
}
