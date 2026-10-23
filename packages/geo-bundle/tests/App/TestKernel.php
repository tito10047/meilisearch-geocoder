<?php

namespace Tito10047\GeoBundle\Tests\App;

use Meilisearch\Client;
use Nyholm\Psr7\Response;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\Autocomplete\AutocompleteBundle;
use Symfony\UX\Autocomplete\AutocompleterInterface;
use Tito10047\GeoBundle\Tests\Fake\FakeHttpClient;
use Tito10047\GeoBundle\Tito10047GeoBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;

/**
 * Minimal application registering Tito10047GeoBundle, booted by the integration and functional tests.
 *
 * The bundle directory is read-only: the cache and the logs are written to the system temporary
 * directory, in a directory specific to the configuration of the kernel. tests/bootstrap.php
 * removes them before each run, so that every run compiles fresh containers.
 *
 * symfony/ux-autocomplete is registered when its version supports the autocompleter of the bundle
 * (3.5 or newer), with its routes under /autocomplete. The routes of the bundle are under /photon.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, array<string, mixed>> $extensionConfigs configuration of the extensions, indexed by alias,
     *                                                              applied last, e.g. ['geo' => [...]]
     * @param array<string, class-string>         $fakes            fakes decorating services of the bundle: id of the
     *                                                              service => class of the fake, constructed without arguments
     */
    public function __construct(
        string $environment = 'test',
        bool $debug = true,
        private readonly array $extensionConfigs = [],
        private readonly array $fakes = [],
    ) {
        parent::__construct($environment, $debug);
    }

    /**
     * The bundles are typed as Bundle: the BundleInterface of HttpKernel is deprecated since Symfony 8.1,
     * and the interface replacing it does not exist in Symfony 7.4.
     *
     * @return iterable<Bundle>
     */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        if (self::hasUxAutocomplete()) {
            yield new AutocompleteBundle();
        }
        yield new Tito10047GeoBundle();
    }

    /**
     * The application has no files of its own: using this directory as the project directory
     * keeps the files of the bundle (config, templates, translations) from being loaded as
     * application files.
     */
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return $this->getVarDir().'/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getVarDir().'/log';
    }

    /**
     * Whether symfony/ux-autocomplete is installed in a version the bundle integrates with.
     */
    public static function hasUxAutocomplete(): bool
    {
        return interface_exists(AutocompleterInterface::class);
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PublicServicesPass());
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'router' => ['utf8' => true],
            // needed by symfony/ux-autocomplete
            'property_access' => self::hasUxAutocomplete(),
            'secret' => 'test',
        ]);

        // see Configs::EMPTY_ENV
        $container->parameters()->set('env(GEO_TEST_EMPTY)', '');
        // see Configs::INDEX_ENV
        $container->parameters()->set('env(GEO_TEST_INDEX)', 'places');

        // the default logger prints the errors, which PHPUnit reports as risky: the test application logs nothing
        $services = $container->services();
        $services->set('logger', NullLogger::class);

        // existing services a configuration may refer to instead of letting the bundle build them
        $services->set('app.meilisearch', Client::class)->args(['http://127.0.0.1:7700', 'key'])->public();
        $services->set('app.pdo', \PDO::class)->args(['sqlite::memory:'])->public();
        $services->set('app.psr18', FakeHttpClient::class)->args([inline_service(Response::class)])->public();

        foreach ($this->fakes as $id => $class) {
            // ignored when the decorated service is not registered (its section disabled)
            $services->set('test.fake.'.$id, $class)->decorate($id, invalidBehavior: ContainerInterface::IGNORE_ON_INVALID_REFERENCE)->public();
        }

        foreach ($this->extensionConfigs as $alias => $config) {
            $container->extension($alias, $config);
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        if (self::hasUxAutocomplete()) {
            $routes->import('@AutocompleteBundle/config/routes.php')->prefix('/autocomplete');
        }
        // imported whatever the configuration, as an application does: no route while the Photon API is disabled
        $routes->import('@Tito10047GeoBundle/config/routes.php')->prefix('/photon');
    }

    /**
     * The directory of the caches and the logs of the test kernels of this copy of the bundle.
     */
    public static function getTemporaryDir(): string
    {
        return sys_get_temp_dir().'/tito10047-geo-bundle/'.hash('xxh128', __DIR__);
    }

    private function getVarDir(): string
    {
        return self::getTemporaryDir().'/'.hash('xxh128', serialize([$this->extensionConfigs, $this->fakes]));
    }
}
