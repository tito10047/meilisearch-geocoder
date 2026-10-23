<?php

namespace Tito10047\GeoBundle\Tests\Photon;

use Geocoder\Model\Bounds;
use Geocoder\Model\Coordinates as ProviderCoordinates;
use Geocoder\Model\Country;
use Geocoder\Provider\Photon\Model\PhotonAddress;
use Geocoder\Provider\Photon\Photon;
use Geocoder\Query\GeocodeQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestKernel;
use Tito10047\GeoBundle\Tests\Fake\FakeGeocoder;
use Tito10047\GeoBundle\Tests\Fake\FakeNameCatalog;
use Tito10047\GeoBundle\Tests\Fake\KernelHttpClient;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\Suggestion;

/**
 * geocoder-php/photon-provider against the Photon API of the test application: the requests it
 * builds are accepted, the answers it parses carry what it reads.
 */
final class PhotonProviderContractTest extends TestCase
{
    private TestKernel $kernel;
    private FakeGeocoder $geocoder;
    private Photon $provider;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel(
            extensionConfigs: ['tito10047_geo' => Configs::geocoder(['photon_api' => true])],
            fakes: Configs::FAKES + ['tito10047_geo.geocoder.name_catalog' => FakeNameCatalog::class],
        );
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        $this->assertInstanceOf(ContainerInterface::class, $container);
        $geocoder = $container->get('tito10047_geo.geocoder');
        $this->assertInstanceOf(FakeGeocoder::class, $geocoder);
        $this->geocoder = $geocoder;
        $catalog = $container->get('tito10047_geo.geocoder.name_catalog');
        $this->assertInstanceOf(FakeNameCatalog::class, $catalog);
        $catalog->translations = ['SK|country|SK|en' => 'Slovakia'];
        $this->provider = new Photon(new KernelHttpClient($this->kernel), 'http://localhost/photon/');

        $this->geocoder->suggestions = [
            new Suggestion('R2256517', Kind::Poi, 'hotel', 'Hotel Devín', 'Hotel Devín, Bratislava, Slovakia', new Coordinates(48.140187, 17.1072709), 'SK', 'Bratislava', 'Bratislava Region', '811 02'),
            new Suggestion('sSK|Košice|Hlavná#12', Kind::Address, 'house', 'Hlavná 12', 'Hlavná 12, Košice, Slovakia', new Coordinates(48.7189389, 21.2580166), 'SK', 'Košice', 'Košice Region', '040 01', 'Hlavná', '12'),
            new Suggestion('sSK|Košice|Hlavná', Kind::Street, 'street', 'Hlavná', 'Hlavná, Košice, Slovakia', new Coordinates(48.72, 21.258), 'SK', 'Košice', 'Košice Region', '040 01'),
        ];
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testGeocodes(): void
    {
        $addresses = $this->provider->geocodeQuery(GeocodeQuery::create('hotel devin')->withLocale('en')->withLimit(5));

        $this->assertCount(3, $addresses);

        $hotel = $addresses->get(0);
        $this->assertInstanceOf(PhotonAddress::class, $hotel);
        $this->assertSame('Hotel Devín', $hotel->getName());
        $this->assertSame(2256517, $hotel->getOSMId());
        $this->assertSame('R', $hotel->getOSMType());
        $this->assertEquals((object) ['key' => 'tourism', 'value' => 'hotel'], $hotel->getOSMTag());
        $this->assertSame('house', $hotel->getType());
        $this->assertSame('Bratislava', $hotel->getLocality());
        $this->assertSame('811 02', $hotel->getPostalCode());
        $this->assertSame('Bratislava Region', $hotel->getState());
        $country = $hotel->getCountry();
        $this->assertInstanceOf(Country::class, $country);
        $this->assertSame('Slovakia', $country->getName());
        $this->assertSame('SK', $country->getCode());
        $coordinates = $hotel->getCoordinates();
        $this->assertInstanceOf(ProviderCoordinates::class, $coordinates);
        $this->assertEqualsWithDelta(48.140187, $coordinates->getLatitude(), \PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(17.1072709, $coordinates->getLongitude(), \PHP_FLOAT_EPSILON);
        $this->assertNull($hotel->getBounds());

        $address = $addresses->get(1);
        $this->assertInstanceOf(PhotonAddress::class, $address);
        $this->assertSame('Hlavná', $address->getStreetName());
        $this->assertSame('12', $address->getStreetNumber());
        $this->assertNull($address->getName());
        $this->assertNull($address->getOSMId());

        $street = $addresses->get(2);
        $this->assertInstanceOf(PhotonAddress::class, $street);
        $this->assertSame('street', $street->getType());
        // the value of the ways of a merged street is unknown, and the provider keeps a tag with both only
        $this->assertNull($street->getOSMTag());
    }

    public function testFilters(): void
    {
        $query = GeocodeQuery::create('hlavna')
            ->withData('layer', ['house', 'street'])
            ->withData('osm_tag', ['!tourism'])
            ->withData('lat', 48.7)
            ->withData('lon', 21.2)
            ->withBounds(new Bounds(48.0, 21.0, 49.0, 22.0));

        $addresses = $this->provider->geocodeQuery($query);

        $names = [];
        foreach ($addresses as $address) {
            $this->assertInstanceOf(PhotonAddress::class, $address);
            $names[] = $address->getStreetName() ?? $address->getName();
        }
        $this->assertSame(['Hlavná', 'Hlavná'], $names, 'the hotel is excluded');
        $this->assertSame([Kind::Address, Kind::Poi, Kind::Street], $this->geocoder->calls[0][1]?->kinds);
    }

    public function testNothingFound(): void
    {
        $this->geocoder->suggestions = [];

        $this->assertCount(0, $this->provider->geocodeQuery(GeocodeQuery::create('zzzz')));
    }
}
