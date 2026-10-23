<?php

namespace Tito10047\Geocoder\Tests\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Photon\BoundingBox;
use Tito10047\Geocoder\Photon\CategoryFilter;
use Tito10047\Geocoder\Photon\Feature;
use Tito10047\Geocoder\Photon\Layer;
use Tito10047\Geocoder\Photon\OsmTagFilter;
use Tito10047\Geocoder\Photon\PhotonQuery;
use Tito10047\Geocoder\Search\Kind;

final class PhotonQueryTest extends TestCase
{
    public function testParsesEveryParameter(): void
    {
        $query = PhotonQuery::fromQueryString('q=Hlavn%C3%A1+12&lang=sk&limit=3&countrycode=sk&countrycode=CZ&layer=house&layer=street'
            .'&osm_tag=tourism:hotel&osm_tag=!railway&include=osm.tourism&exclude=osm.place&bbox=17,48,18,49&lat=48.1&lon=17.1'
            .'&zoom=12&location_bias_scale=0.2&dedupe=0&geometry=true&debug=1&suggest_addresses=true');

        $this->assertSame('Hlavná 12', $query->text);
        $this->assertSame('sk', $query->locale);
        $this->assertSame(3, $query->limit);
        $this->assertSame(['SK', 'CZ'], $query->countries);
        $this->assertSame([Layer::House, Layer::Street], $query->layers);
        $this->assertEquals([new OsmTagFilter('tourism', 'hotel'), new OsmTagFilter('railway', null, true)], $query->osmTags);
        $this->assertEquals([CategoryFilter::fromString('osm.tourism')], $query->includes);
        $this->assertEquals([CategoryFilter::fromString('osm.place')], $query->excludes);
        $this->assertEquals(new BoundingBox(17.0, 48.0, 18.0, 49.0), $query->boundingBox);
        $this->assertEquals(new Coordinates(48.1, 17.1), $query->focus);
    }

    public function testDefaults(): void
    {
        $query = PhotonQuery::fromQueryString('q=berlin');

        $this->assertNull($query->locale);
        $this->assertNull($query->limit);
        $this->assertSame([], $query->countries);
        $this->assertSame([], $query->kinds());
        $this->assertFalse($query->filters());
        $this->assertNull($query->boundingBox);
        $this->assertNull($query->focus);
    }

    public function testTheFirstOfARepeatedSingleParameterWins(): void
    {
        $this->assertSame(1, PhotonQuery::fromQueryString('q=a&limit=1&limit=2')->limit);
    }

    #[DataProvider('provideLimits')]
    public function testClampsTheLimitLikePhoton(string $limit, int $expected): void
    {
        $this->assertSame($expected, PhotonQuery::fromQueryString('q=a&limit='.$limit)->limit);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideLimits(): iterable
    {
        yield 'zero' => ['0', 1];
        yield 'negative' => ['-3', 1];
        yield 'over the maximum' => ['1000', 20];
        yield 'in range' => ['7', 7];
    }

    #[DataProvider('provideLanguages')]
    public function testLanguage(string $queryString, ?string $fallback, ?string $expected): void
    {
        $this->assertSame($expected, PhotonQuery::fromQueryString($queryString, $fallback)->locale);
    }

    /**
     * @return iterable<string, array{string, string|null, string|null}>
     */
    public static function provideLanguages(): iterable
    {
        yield 'given' => ['q=a&lang=en', 'sk', 'en'];
        yield 'default is the local names' => ['q=a&lang=default', 'sk', null];
        yield 'from the Accept-Language' => ['q=a', 'sk_SK', 'sk_SK'];
        yield 'invalid fallback ignored' => ['q=a', '*', null];
        yield 'none' => ['q=a', null, null];
    }

    #[DataProvider('provideInvalidQueries')]
    public function testRejectsAnInvalidQuery(string $queryString, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        PhotonQuery::fromQueryString($queryString);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidQueries(): iterable
    {
        yield 'no text' => ['', '"q" is required'];
        yield 'blank text' => ['q=+&limit=2', '"q" is required'];
        yield 'unknown parameter' => ['q=a&foo=1', "Unknown query parameter 'foo'."];
        yield 'PHP array syntax' => ['q=a&layer[]=city', "Unknown query parameter 'layer[]'."];
        yield 'limit not a number' => ['q=a&limit=abc', 'The limit must be an integer'];
        yield 'empty limit' => ['q=a&limit=', 'The limit must be an integer'];
        yield 'empty language' => ['q=a&lang=', 'The language must be'];
        yield 'invalid language' => ['q=a&lang=EN', 'The language must be'];
        yield 'unknown layer' => ['q=a&layer=bogus', 'Unknown layer type "bogus"'];
        yield 'comma-separated layers' => ['q=a&layer=city,street', 'Unknown layer type'];
        yield 'invalid country' => ['q=a&countrycode=SVK', 'ISO 3166-1 alpha-2'];
        yield 'invalid OSM tag' => ['q=a&osm_tag=a:b:c', 'The OSM tag filter must be'];
        yield 'category group only' => ['q=a&include=osm', 'A category must be'];
        yield 'invalid bounding box' => ['q=a&bbox=1,2,3', 'The bounding box must be'];
        yield 'latitude without longitude' => ['q=a&lat=48', "Missing parameter 'lon'."];
        yield 'longitude without latitude' => ['q=a&lon=17', "Missing parameter 'lat'."];
        yield 'latitude out of range' => ['q=a&lat=100&lon=17', 'The latitude must be between'];
        yield 'latitude not a number' => ['q=a&lat=x&lon=17', '"lat" must be a number'];
        yield 'zoom not a number' => ['q=a&zoom=abc', '"zoom" must be a number'];
        yield 'bias scale not a number' => ['q=a&location_bias_scale=x', '"location_bias_scale" must be a number'];
    }

    #[DataProvider('provideConstructorArguments')]
    public function testConstructorValidates(\Closure $create): void
    {
        $this->expectException(InvalidArgumentException::class);

        $create();
    }

    /**
     * @return iterable<string, array{\Closure(): PhotonQuery}>
     */
    public static function provideConstructorArguments(): iterable
    {
        /* @phpstan-ignore argument.type (the invalid limit is the point of the test) */
        yield 'limit' => [static fn (): PhotonQuery => new PhotonQuery('a', limit: 21)];
        /* @phpstan-ignore argument.type (the invalid list is the point of the test) */
        yield 'countries not a list' => [static fn (): PhotonQuery => new PhotonQuery('a', countries: ['x' => 'SK'])];
        yield 'country in lower case' => [static fn (): PhotonQuery => new PhotonQuery('a', countries: ['sk'])];
        /* @phpstan-ignore argument.type (the invalid layer is the point of the test) */
        yield 'layer' => [static fn (): PhotonQuery => new PhotonQuery('a', layers: ['city'])];
        /* @phpstan-ignore argument.type (the invalid filter is the point of the test) */
        yield 'OSM tag' => [static fn (): PhotonQuery => new PhotonQuery('a', osmTags: ['tourism'])];
    }

    /**
     * @param list<Kind>|null $expected
     */
    #[DataProvider('provideLayers')]
    public function testKindsOfTheLayers(string $layers, ?array $expected): void
    {
        $this->assertSame($expected, PhotonQuery::fromQueryString('q=a'.$layers)->kinds());
    }

    /**
     * @return iterable<string, array{string, list<Kind>|null}>
     */
    public static function provideLayers(): iterable
    {
        yield 'none' => ['', []];
        yield 'house' => ['&layer=house', [Kind::Address, Kind::Poi]];
        yield 'places once' => ['&layer=city&layer=district&layer=locality', [Kind::Place]];
        yield 'street and city' => ['&layer=street&layer=city', [Kind::Street, Kind::Place]];
        yield 'nothing of the index' => ['&layer=state&layer=country', null];
    }

    #[DataProvider('provideFilters')]
    public function testMatches(string $filters, Feature $feature, bool $expected): void
    {
        $query = PhotonQuery::fromQueryString('q=a'.$filters);

        $this->assertTrue($query->filters());
        $this->assertSame($expected, $query->matches($feature));
    }

    /**
     * @return iterable<string, array{string, Feature, bool}>
     */
    public static function provideFilters(): iterable
    {
        $hotel = new Feature(new Coordinates(48.14, 17.10), Layer::House, 'SK', osmKey: 'tourism', osmValue: 'hotel');
        $street = new Feature(new Coordinates(48.71, 21.25), Layer::Street, 'SK', osmKey: 'highway');

        yield 'layer in' => ['&layer=house', $hotel, true];
        yield 'layer out' => ['&layer=street', $hotel, false];
        yield 'in the box' => ['&bbox=17,48,18,49', $hotel, true];
        yield 'out of the box' => ['&bbox=17,48,18,49', $street, false];
        yield 'tag included' => ['&osm_tag=tourism:hotel', $hotel, true];
        yield 'any included tag' => ['&osm_tag=railway&osm_tag=tourism', $hotel, true];
        yield 'tag not included' => ['&osm_tag=railway', $hotel, false];
        yield 'tag excluded' => ['&osm_tag=!tourism', $hotel, false];
        yield 'other tag excluded' => ['&osm_tag=!railway', $hotel, true];
        yield 'included and excluded' => ['&osm_tag=tourism&osm_tag=:!hotel', $hotel, false];
        yield 'street by its key' => ['&osm_tag=highway', $street, true];
        yield 'street by a value' => ['&osm_tag=highway:residential', $street, false];
        yield 'category included' => ['&include=osm.tourism', $hotel, true];
        yield 'every include must match' => ['&include=osm.tourism&include=osm.railway', $hotel, false];
        yield 'any category of an include' => ['&include=osm.railway,osm.tourism.hotel', $hotel, true];
        yield 'other group' => ['&include=food.shop', $hotel, false];
        yield 'excluded when all match' => ['&exclude=osm.tourism,osm.tourism.hotel', $hotel, false];
        yield 'kept when one does not match' => ['&exclude=osm.tourism,osm.railway', $hotel, true];
    }
}
