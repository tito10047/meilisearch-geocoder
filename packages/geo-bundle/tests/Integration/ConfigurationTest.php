<?php

namespace Tito10047\GeoBundle\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestKernel;
use Tito10047\GeoBundle\Tito10047GeoBundle;
use Tito10047\Geocoder\Import\SelectionRules;

/**
 * Processes configurations of the bundle with its configuration tree, and boots the test
 * application with one.
 */
final class ConfigurationTest extends KernelTestCase
{
    public function testUnknownOptionIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "unknown" under "tito10047_geo"');

        self::bootKernel(['extension_configs' => ['tito10047_geo' => ['unknown' => true]]]);
    }

    public function testSectionsAreDisabledByDefault(): void
    {
        $config = $this->process([]);

        $this->assertFalse($config['geocoder']['enabled']);
        $this->assertFalse($config['routing']['enabled']);
    }

    public function testGeocoderDefaults(): void
    {
        $geocoder = $this->process(Configs::geocoder())['geocoder'];

        $this->assertTrue($geocoder['enabled']);
        $this->assertSame([
            'url' => 'http://127.0.0.1:7700',
            'api_key' => 'key',
            'client' => null,
            'http_client' => null,
            'timeout' => 5.0,
            'compression' => false,
            'index' => 'geo',
        ], $geocoder['meilisearch']);
        $this->assertSame(['house_numbers' => 'geo_house', 'translations' => 'geo_name', 'metadata' => 'geo_meta'], $geocoder['database']['tables']);
        $this->assertSame(['default_limit' => 5, 'preferred_country' => null, 'max_rank_gap' => 25000, 'rerank_window' => 15], $geocoder['search']);
        $this->assertSame([
            'dumps_dir' => null,
            'languages' => SelectionRules::DEFAULT_LANGUAGES,
            'place_types' => SelectionRules::DEFAULT_PLACE_TYPES,
            'pois' => SelectionRules::DEFAULT_POIS,
            'poi_rank' => 20,
            'street_rank' => 25,
            'place_dedup_km' => 5.0,
        ], $geocoder['import']);
        $this->assertSame(['enabled' => 'auto', 'alias' => 'tito10047_geo_address'], $geocoder['ux_autocomplete']);
    }

    public function testRoutingDefaults(): void
    {
        $this->assertSame(
            ['url' => 'http://127.0.0.1:8002', 'enabled' => true, 'timeout' => 10.0, 'radius' => 200, 'http_client' => null],
            $this->process(Configs::routing())['routing'],
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, mixed, string}>
     */
    public static function validValues(): iterable
    {
        yield 'existing client' => [Configs::geocoder(['meilisearch' => ['client' => 'meilisearch.client']]), 'meilisearch.client', 'geocoder.meilisearch.client'];
        yield 'connection' => [Configs::geocoder(['database' => ['connection' => 'app.pdo']]), 'app.pdo', 'geocoder.database.connection'];
        yield 'dsn with credentials' => [Configs::geocoder(['database' => ['dsn' => 'mysql:host=db', 'user' => 'geo', 'password' => 'secret']]), 'geo', 'geocoder.database.user'];
        yield 'preferred country' => [Configs::geocoder(['search' => ['preferred_country' => 'SK']]), 'SK', 'geocoder.search.preferred_country'];
        yield 'limit at its maximum' => [Configs::geocoder(['search' => ['default_limit' => 20]]), 20, 'geocoder.search.default_limit'];
        yield 'zero rank gap' => [Configs::geocoder(['search' => ['max_rank_gap' => 0]]), 0, 'geocoder.search.max_rank_gap'];
        yield 'languages' => [Configs::geocoder(['import' => ['languages' => ['sk', 'hsb']]]), ['sk', 'hsb'], 'geocoder.import.languages'];
        yield 'place types replace the defaults' => [Configs::geocoder(['import' => ['place_types' => ['city' => 90, 'isolated_dwelling' => 0]]]), ['city' => 90, 'isolated_dwelling' => 0], 'geocoder.import.place_types'];
        yield 'points of interest replace the defaults' => [Configs::geocoder(['import' => ['pois' => ['shop' => ['mall']]]]), ['shop' => ['mall']], 'geocoder.import.pois'];
        yield 'autocomplete shorthand' => [Configs::geocoder(['ux_autocomplete' => false]), false, 'geocoder.ux_autocomplete.enabled'];
        yield 'autocomplete required' => [Configs::geocoder(['ux_autocomplete' => ['enabled' => true]]), true, 'geocoder.ux_autocomplete.enabled'];
        yield 'compression' => [Configs::geocoder(['meilisearch' => ['url' => 'http://m:7700', 'compression' => true]]), true, 'geocoder.meilisearch.compression'];
        yield 'routing radius zero' => [Configs::routing(['radius' => 0]), 0, 'routing.radius'];
        yield 'routing timeout' => [Configs::routing(['timeout' => 2.5]), 2.5, 'routing.timeout'];
        yield 'routing explicitly disabled' => [['routing' => ['enabled' => false]], false, 'routing.enabled'];
        yield 'environment variable' => [Configs::routing(['url' => '%env(VALHALLA_URL)%']), '%env(VALHALLA_URL)%', 'routing.url'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('validValues')]
    public function testValidValue(array $config, mixed $expected, string $path): void
    {
        $value = $this->process($config);
        foreach (explode('.', $path) as $key) {
            $this->assertIsArray($value);
            $value = $value[$key];
        }

        $this->assertSame($expected, $value);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'geocoder without Meilisearch' => [['geocoder' => ['database' => ['dsn' => 'sqlite::memory:']]], 'needs its "meilisearch"'];
        yield 'geocoder without database' => [['geocoder' => ['meilisearch' => ['url' => 'http://m:7700']]], 'needs its "database"'];
        yield 'Meilisearch without url nor client' => [Configs::geocoder(['meilisearch' => ['index' => 'geo']]), 'Give the "url" of Meilisearch'];
        yield 'API key without url' => [Configs::geocoder(['meilisearch' => ['client' => 'c', 'api_key' => 'k']]), 'give the "url" too'];
        yield 'client id with @' => [Configs::geocoder(['meilisearch' => ['client' => '@meilisearch.client']]), 'without the leading "@"'];
        yield 'invalid index uid' => [Configs::geocoder(['meilisearch' => ['url' => 'http://m:7700', 'index' => 'geo index']]), 'The index uid must be'];
        yield 'too long index uid' => [Configs::geocoder(['meilisearch' => ['url' => 'http://m:7700', 'index' => str_repeat('g', 386)]]), 'The index uid must be'];
        yield 'too short timeout' => [Configs::geocoder(['meilisearch' => ['url' => 'http://m:7700', 'timeout' => 0]]), 'Should be greater than or equal to 0.1'];
        yield 'dsn and connection' => [Configs::geocoder(['database' => ['dsn' => 'sqlite::memory:', 'connection' => 'app.pdo']]), 'not both'];
        yield 'neither dsn nor connection' => [Configs::geocoder(['database' => ['tables' => []]]), 'not both'];
        yield 'user with connection' => [Configs::geocoder(['database' => ['connection' => 'app.pdo', 'user' => 'geo']]), 'go with the "dsn"'];
        yield 'invalid table name' => [Configs::geocoder(['database' => ['dsn' => 'sqlite::memory:', 'tables' => ['house_numbers' => 'geo-house']]]), 'A table name must be'];
        yield 'lower case preferred country' => [Configs::geocoder(['search' => ['preferred_country' => 'sk']]), 'upper case'];
        yield 'three letter preferred country' => [Configs::geocoder(['search' => ['preferred_country' => 'SVK']]), 'upper case'];
        yield 'limit zero' => [Configs::geocoder(['search' => ['default_limit' => 0]]), 'Should be greater than or equal to 1'];
        yield 'limit over 20' => [Configs::geocoder(['search' => ['default_limit' => 21]]), 'Should be less than or equal to 20'];
        yield 'negative rank gap' => [Configs::geocoder(['search' => ['max_rank_gap' => -1]]), 'Should be greater than or equal to 0'];
        yield 'zero rerank window' => [Configs::geocoder(['search' => ['rerank_window' => 0]]), 'Should be greater than or equal to 1'];
        yield 'upper case language' => [Configs::geocoder(['import' => ['languages' => ['SK']]]), 'ISO 639 codes in lower case'];
        yield 'repeated language' => [Configs::geocoder(['import' => ['languages' => ['sk', 'sk']]]), 'must not repeat'];
        yield 'no language' => [Configs::geocoder(['import' => ['languages' => []]]), 'at least 1 element'];
        yield 'negative place rank' => [Configs::geocoder(['import' => ['place_types' => ['city' => -1]]]), 'Should be greater than or equal to 0'];
        yield 'empty points of interest of a key' => [Configs::geocoder(['import' => ['pois' => ['shop' => []]]]), 'at least 1 element'];
        yield 'negative dedup distance' => [Configs::geocoder(['import' => ['place_dedup_km' => -0.1]]), 'Should be greater than or equal to 0'];
        yield 'invalid autocomplete mode' => [Configs::geocoder(['ux_autocomplete' => 'always']), 'is not allowed'];
        yield 'invalid autocomplete alias' => [Configs::geocoder(['ux_autocomplete' => ['alias' => 'geo/address']]), 'The alias must be'];
        yield 'routing without url' => [['routing' => ['timeout' => 5]], 'needs the "url" of Valhalla'];
        yield 'routing negative radius' => [Configs::routing(['radius' => -1]), 'Should be greater than or equal to 0'];
        yield 'routing too short timeout' => [Configs::routing(['timeout' => 0.01]), 'Should be greater than or equal to 0.1'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationIsRejected(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        $this->process($config);
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

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        $extension = (new Tito10047GeoBundle())->getContainerExtension();
        $this->assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        $this->assertInstanceOf(ConfigurationInterface::class, $configuration);

        $processed = (new Processor())->processConfiguration($configuration, [$config]);
        $this->assertIsArray($processed);

        return $processed;
    }
}
