<?php

namespace Tito10047\Geocoder\Tests\Photon;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Photon\Feature;
use Tito10047\Geocoder\Photon\FeatureFactory;
use Tito10047\Geocoder\Photon\PhotonQuery;
use Tito10047\Geocoder\Photon\PhotonSearch;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Tests\Fake\FakeGeocoder;
use Tito10047\Geocoder\Tests\Fake\FakeNameCatalog;
use Tito10047\Geocoder\Tests\Fake\Suggestions;

final class PhotonSearchTest extends TestCase
{
    public function testPassesTheOptionsToTheGeocoder(): void
    {
        $geocoder = new FakeGeocoder([Suggestions::city(), Suggestions::village()]);
        $search = new PhotonSearch($geocoder, new FeatureFactory(new FakeNameCatalog(['SK|country|SK|en' => 'Slovakia'])), new SearchOptions(limit: 7, preferredCountry: 'SK'));

        $features = $search->search(PhotonQuery::fromQueryString('q=brat&lang=en_GB&countrycode=SK'))->features;

        $this->assertSame(['Bratislava', 'Viničky'], array_map(static fn (Feature $feature): ?string => $feature->name, $features));
        $this->assertSame('Slovakia', $features[0]->country);
        [$text, $options] = $geocoder->calls[0];
        $this->assertSame('brat', $text);
        $this->assertEquals(new SearchOptions('en_GB', 7, 'SK', ['SK']), $options);
    }

    public function testTheLimitOfTheQuery(): void
    {
        $geocoder = new FakeGeocoder([Suggestions::city(), Suggestions::village()]);

        $collection = (new PhotonSearch($geocoder, new FeatureFactory(new FakeNameCatalog())))->search(PhotonQuery::fromQueryString('q=a&limit=1'));

        $this->assertCount(1, $collection);
        $this->assertSame(1, $geocoder->calls[0][1]->limit);
    }

    public function testFiltersAFullWindow(): void
    {
        $geocoder = new FakeGeocoder([Suggestions::city(), Suggestions::hotel(), Suggestions::street(), Suggestions::station(), Suggestions::address()]);

        $features = (new PhotonSearch($geocoder, new FeatureFactory(new FakeNameCatalog())))
            ->search(PhotonQuery::fromQueryString('q=a&limit=1&layer=house&osm_tag=railway'))->features;

        $this->assertSame(['Bratislava hlavná stanica'], array_map(static fn (Feature $feature): ?string => $feature->name, $features));
        $options = $geocoder->calls[0][1];
        $this->assertSame(SearchOptions::MAX_LIMIT, $options->limit);
        $this->assertSame([Kind::Address, Kind::Poi], $options->kinds);
    }

    public function testBoundingBoxDropsWhatIsOutside(): void
    {
        $geocoder = new FakeGeocoder([Suggestions::vienna(), Suggestions::city()]);

        $features = (new PhotonSearch($geocoder, new FeatureFactory(new FakeNameCatalog())))
            ->search(PhotonQuery::fromQueryString('q=a&bbox=16.9,48,17.3,48.3'))->features;

        $this->assertSame(['Bratislava'], array_map(static fn (Feature $feature): ?string => $feature->name, $features));
    }

    public function testLayersWithoutAnythingOfTheIndexDoNotSearch(): void
    {
        $geocoder = new FakeGeocoder([Suggestions::city()]);

        $collection = (new PhotonSearch($geocoder, new FeatureFactory(new FakeNameCatalog())))->search(PhotonQuery::fromQueryString('q=a&layer=country'));

        $this->assertCount(0, $collection);
        $this->assertSame([], $geocoder->calls);
    }
}
