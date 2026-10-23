<?php

namespace Tito10047\GeoBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Tito10047\GeoBundle\Tests\App\TestKernel;

/**
 * Boots the test application with the given configuration of the bundle.
 */
final class ConfigurationTest extends KernelTestCase
{
    public function testUnknownOptionIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "unknown" under "tito10047_geo"');

        self::bootKernel(['extension_configs' => ['tito10047_geo' => ['unknown' => true]]]);
    }

    /**
     * Supports the "extension_configs" option, see TestKernel::__construct().
     *
     * @param array<mixed> $options
     */
    protected static function createKernel(array $options = []): TestKernel
    {
        return new TestKernel(extensionConfigs: $options['extension_configs'] ?? []);
    }
}
