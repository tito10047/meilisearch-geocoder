<?php

namespace Tito10047\GeoBundle\Tests\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tito10047\GeoBundle\Photon\PhotonApiController;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestKernel;
use Tito10047\GeoBundle\Tests\Fake\FakeGeocoder;
use Tito10047\GeoBundle\Tests\Fake\FakeNameCatalog;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;

/**
 * "GET /photon/api" through the test application, with a fake geocoder.
 */
final class PhotonApiControllerTest extends TestCase
{
    private TestKernel $kernel;
    private KernelBrowser $browser;
    private FakeGeocoder $geocoder;

    protected function setUp(): void
    {
        $this->boot(['photon_api' => ['max_age' => 600]]);
        $this->geocoder->suggestions = [
            new Suggestion('N530544342', Kind::Place, 'city', 'Bratislava', 'Bratislava, Slovensko', new Coordinates(48.1435026, 17.1082867), 'SK', null, 'Bratislavský kraj', '815 18'),
            new Suggestion('sSK|Košice|Hlavná#12', Kind::Address, 'house', 'Hlavná 12', 'Hlavná 12, Košice, Slovensko', new Coordinates(48.7189389, 21.2580166), 'SK', 'Košice', 'Košický kraj', '040 01', 'Hlavná', '12'),
        ];
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testAnswersLikePhoton(): void
    {
        $this->browser->request('GET', '/photon/api?q=brat&lang=sk&limit=2');

        $response = $this->browser->getResponse();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame(
            '{"type":"FeatureCollection","features":['
            .'{"type":"Feature","properties":{"osm_type":"N","osm_id":530544342,"osm_key":"place","osm_value":"city","type":"city","name":"Bratislava","state":"Bratislavský kraj","country":"Slovensko","postcode":"815 18","countrycode":"SK"},"geometry":{"type":"Point","coordinates":[17.1082867,48.1435026]}},'
            .'{"type":"Feature","properties":{"osm_key":"place","osm_value":"house","type":"house","housenumber":"12","street":"Hlavná","city":"Košice","state":"Košický kraj","country":"Slovensko","postcode":"040 01","countrycode":"SK"},"geometry":{"type":"Point","coordinates":[21.2580166,48.7189389]}}'
            .']}',
            $response->getContent(),
        );
        $this->assertEquals([['brat', new SearchOptions('sk', 2)]], $this->geocoder->calls);
    }

    public function testHeaders(): void
    {
        $this->browser->request('GET', '/photon/api?q=brat');

        $headers = $this->browser->getResponse()->headers;
        $this->assertSame('max-age=600, public', $headers->get('Cache-Control'));
        $this->assertSame(['Accept-Language'], $this->browser->getResponse()->getVary());
        $this->assertSame(PhotonApiController::LICENSE_LINK, $headers->get('Link'));
        $this->assertFalse($headers->has('Access-Control-Allow-Origin'));
    }

    public function testNoCachingHeadersWithoutAMaxAge(): void
    {
        $this->kernel->shutdown();
        $this->boot(['photon_api' => ['max_age' => 0]]);

        $this->browser->request('GET', '/photon/api?q=brat');

        $this->assertStringNotContainsString('public', (string) $this->browser->getResponse()->headers->get('Cache-Control'));
    }

    public function testTheLanguageOfTheAcceptLanguageHeader(): void
    {
        $this->browser->request('GET', '/photon/api?q=brat', server: ['HTTP_ACCEPT_LANGUAGE' => 'en-GB,en;q=0.8']);

        $this->assertSame('en_GB', $this->geocoder->calls[0][1]?->locale);
        $this->assertStringContainsString('"country":"Slovakia"', (string) $this->browser->getResponse()->getContent());
    }

    public function testRepeatedParametersOfTheRawQueryString(): void
    {
        $request = Request::create('/photon/api');
        $request->server->set('QUERY_STRING', 'q=brat&layer=street&layer=house');

        $response = $this->kernel->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([Kind::Street, Kind::Address, Kind::Poi], $this->geocoder->calls[0][1]?->kinds);
        $features = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['features'];
        $this->assertCount(1, $features, 'the city is no street nor house');
    }

    #[DataProvider('provideInvalidQueries')]
    public function testInvalidParametersAreABadRequest(string $query, string $message): void
    {
        $this->browser->request('GET', '/photon/api'.$query);

        $response = $this->browser->getResponse();
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $body = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);
        $this->assertSame(['message'], array_keys($body));
        $this->assertIsString($body['message']);
        $this->assertStringContainsString($message, $body['message']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidQueries(): iterable
    {
        yield 'no text' => ['', '"q" is required'];
        yield 'unknown parameter' => ['?q=a&foo=1', "Unknown query parameter 'foo'."];
        yield 'unknown layer' => ['?q=a&layer=bogus', 'Unknown layer type'];
        yield 'invalid OSM tag' => ['?q=a&osm_tag=a:b:c', 'The OSM tag filter must be'];
    }

    public function testInvalidTextOfTheGeocoderIsABadRequest(): void
    {
        $this->geocoder->exception = new InvalidArgumentException('The text is not valid UTF-8.');

        $this->browser->request('GET', '/photon/api?q=%FF');

        $this->assertSame(400, $this->browser->getResponse()->getStatusCode());
        $this->assertSame('{"message":"The text is not valid UTF-8."}', $this->browser->getResponse()->getContent());
    }

    public function testUnavailableGeocoderIsAServiceUnavailable(): void
    {
        $this->geocoder->exception = new GeocoderUnavailableException('Meilisearch at http://10.0.0.5:7700 is down.');

        $this->browser->request('GET', '/photon/api?q=brat');

        $response = $this->browser->getResponse();
        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('{"message":"The geocoder is unavailable, try again later."}', $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testOnlyGetAndHead(): void
    {
        $this->browser->request('POST', '/photon/api?q=brat');

        $this->assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $this->browser->getResponse()->getStatusCode());
    }

    /**
     * @param array<string, mixed> $geocoder
     */
    private function boot(array $geocoder): void
    {
        $this->kernel = new TestKernel(
            extensionConfigs: ['geo' => Configs::geocoder($geocoder)],
            fakes: Configs::FAKES + ['geo.geocoder.name_catalog' => FakeNameCatalog::class],
        );
        $this->kernel->boot();
        $this->browser = new KernelBrowser($this->kernel);
        $this->browser->disableReboot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        $this->assertInstanceOf(ContainerInterface::class, $container);
        $geocoderService = $container->get('geo.geocoder');
        $this->assertInstanceOf(FakeGeocoder::class, $geocoderService);
        $this->geocoder = $geocoderService;
        $catalog = $container->get('geo.geocoder.name_catalog');
        $this->assertInstanceOf(FakeNameCatalog::class, $catalog);
        $catalog->translations = ['SK|country|SK|sk' => 'Slovensko', 'SK|country|SK|en' => 'Slovakia'];
    }
}
