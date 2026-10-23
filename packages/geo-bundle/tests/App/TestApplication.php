<?php

namespace Tito10047\GeoBundle\Tests\App;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A booted test application whose services used by the commands and the autocompleter are faked
 * (see Configs::FAKES), for the tests of the commands.
 */
final readonly class TestApplication
{
    private TestKernel $kernel;

    /**
     * @param array<string, mixed> $config the configuration of the bundle
     */
    public function __construct(array $config)
    {
        $this->kernel = new TestKernel(extensionConfigs: ['geo' => $config], fakes: Configs::FAKES);
        $this->kernel->boot();
    }

    public function command(string $name): CommandTester
    {
        return new CommandTester((new Application($this->kernel))->find($name));
    }

    /**
     * A service of the test container (the private services included), e.g. a fake.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function service(string $id, string $class): object
    {
        $container = $this->kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);
        $service = $container->get($id);
        if (!$service instanceof $class) {
            throw new \LogicException(\sprintf('The service "%s" is a %s, not a %s.', $id, get_debug_type($service), $class));
        }

        return $service;
    }

    public function shutdown(): void
    {
        $this->kernel->shutdown();
    }
}
