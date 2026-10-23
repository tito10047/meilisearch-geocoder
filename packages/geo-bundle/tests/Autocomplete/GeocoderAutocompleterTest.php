<?php

namespace Tito10047\GeoBundle\Tests\Autocomplete;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\UX\Autocomplete\AutocompleterInterface;
use Tito10047\GeoBundle\Autocomplete\GeocoderAutocompleter;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestKernel;
use Tito10047\GeoBundle\Tests\Fake\FakeGeocoder;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;

final class GeocoderAutocompleterTest extends TestCase
{
    private FakeGeocoder $geocoder;
    private RequestStack $requests;
    private GeocoderAutocompleter $autocompleter;

    protected function setUp(): void
    {
        if (!interface_exists(AutocompleterInterface::class)) {
            $this->markTestSkipped('symfony/ux-autocomplete 3.5 or newer is not installed.');
        }

        $this->geocoder = new FakeGeocoder();
        $this->geocoder->suggestions = [
            new Suggestion('R1', Kind::Place, 'city', 'Viedeň', 'Viedeň, Rakúsko', new Coordinates(48.2083537, 16.3725042), 'AT'),
            new Suggestion('R2', Kind::Place, 'city', 'Bratislava', 'Bratislava, Slovensko', new Coordinates(48.1, -17.25), 'SK'),
        ];
        $this->requests = new RequestStack();
        $this->autocompleter = new GeocoderAutocompleter($this->geocoder, $this->requests, new SearchOptions(limit: 4, preferredCountry: 'SK'));
    }

    public function testResultsCarryTheCoordinatesAndTheLabel(): void
    {
        $results = $this->autocompleter->fetchResults('vied', 1);

        $this->assertSame([
            ['value' => '48.2083537,16.3725042|Viedeň, Rakúsko', 'text' => 'Viedeň, Rakúsko'],
            ['value' => '48.1,-17.25|Bratislava, Slovensko', 'text' => 'Bratislava, Slovensko'],
        ], $results->results);
        $this->assertFalse($results->hasNextPage);
        $this->assertEquals([['vied', new SearchOptions(limit: 4, preferredCountry: 'SK')]], $this->geocoder->calls);
    }

    public function testThereIsASinglePage(): void
    {
        $results = $this->autocompleter->fetchResults('vied', 2);

        $this->assertSame([], $results->results);
        $this->assertSame([], $this->geocoder->calls);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, SearchOptions}>
     */
    public static function queries(): iterable
    {
        $defaults = new SearchOptions(limit: 4, preferredCountry: 'SK');

        yield 'locale' => [['locale' => 'sk_SK'], $defaults->withLocale('sk_SK')];
        yield 'empty locale' => [['locale' => ''], $defaults];
        yield 'countries separated by commas' => [['country' => 'sk, at,SK'], $defaults->withCountries(['SK', 'AT'])];
        yield 'countries as an array' => [['country' => ['cz', 'HU']], $defaults->withCountries(['CZ', 'HU'])];
        yield 'empty country' => [['country' => ''], $defaults];
        yield 'other parameters' => [['query' => 'vied', 'page' => '1'], $defaults];
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('queries')]
    public function testQueryStringNarrowsTheSearch(array $query, SearchOptions $options): void
    {
        $this->requests->push(new Request($query));

        $this->autocompleter->fetchResults('vied', 1);

        $this->assertEquals([['vied', $options]], $this->geocoder->calls);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'invalid locale' => [['locale' => 'Slovak']];
        yield 'invalid country' => [['country' => 'SVK']];
        yield 'nested country' => [['country' => [['SK']]]];
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('invalidQueries')]
    public function testInvalidQueryIsABadRequest(array $query): void
    {
        $this->requests->push(new Request($query));

        $this->expectException(BadRequestHttpException::class);

        $this->autocompleter->fetchResults('vied', 1);
    }

    public function testEndpointAnswersTheResults(): void
    {
        $kernel = new TestKernel(extensionConfigs: ['tito10047_geo' => Configs::geocoder()], fakes: Configs::FAKES);
        $kernel->boot();
        $browser = new KernelBrowser($kernel);
        // the fake keeps its state between requests
        $browser->disableReboot();
        $geocoder = $browser->getContainer()->get('tito10047_geo.geocoder');
        $this->assertInstanceOf(FakeGeocoder::class, $geocoder);
        $geocoder->suggestions = $this->geocoder->suggestions;

        $browser->request('GET', '/autocomplete/tito10047_geo_address?query=vied&locale=sk&country=AT');

        $this->assertSame(200, $browser->getResponse()->getStatusCode());
        $this->assertSame(
            ['results' => [
                ['value' => '48.2083537,16.3725042|Viedeň, Rakúsko', 'text' => 'Viedeň, Rakúsko'],
                ['value' => '48.1,-17.25|Bratislava, Slovensko', 'text' => 'Bratislava, Slovensko'],
            ], 'next_page' => null],
            json_decode((string) $browser->getResponse()->getContent(), true, 8, \JSON_THROW_ON_ERROR),
        );
        $this->assertEquals([['vied', new SearchOptions('sk', 5, null, ['AT'])]], $geocoder->calls);
        $kernel->shutdown();
    }
}
