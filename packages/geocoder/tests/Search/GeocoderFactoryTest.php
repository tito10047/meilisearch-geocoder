<?php

namespace Tito10047\Geocoder\Tests\Search;

use Meilisearch\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\GeocoderFactory;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Fake\FakeHttpClient;

#[RequiresPhpExtension('pdo_sqlite')]
final class GeocoderFactoryTest extends TestCase
{
    public function testBuildsAWorkingGeocoder(): void
    {
        $hits = ['hits' => [
            ['id' => 's909109604356383787', 'kind' => 'street', 'type' => 'street', 'name' => 'Hlavná', 'city' => 'Košice', 'region' => 'Košický kraj',
                'cc' => 'SK', 'postcode' => null, '_geo' => ['lat' => 48.75, 'lng' => 21.23], 'rank' => 25000],
        ]];
        $http = new FakeHttpClient(new Response(200, ['Content-Type' => 'application/json'], json_encode($hits, \JSON_THROW_ON_ERROR)));
        $factory = new Psr17Factory();
        $connection = new \PDO('sqlite::memory:');
        $connection->exec('CREATE TABLE h (street_key BIGINT NOT NULL, number VARCHAR(32) NOT NULL, orientation VARCHAR(32) NULL, postcode VARCHAR(16) NULL, lat DOUBLE PRECISION NOT NULL, lon DOUBLE PRECISION NOT NULL, suburb VARCHAR(255) NULL, reference VARCHAR(32) NULL)');
        $connection->exec("INSERT INTO h VALUES (909109604356383787, '9/12', '12', '040 01', 48.72, 21.26, NULL, '7101722639')");
        $connection->exec('CREATE TABLE n (cc CHAR(2) NOT NULL, kind VARCHAR(8) NOT NULL, name VARCHAR(255) NOT NULL, lang VARCHAR(3) NOT NULL, translation VARCHAR(255) NOT NULL)');
        $connection->exec("INSERT INTO n VALUES ('SK', 'country', 'SK', 'sk', 'Slovensko')");

        $geocoder = GeocoderFactory::create(new Client('http://meilisearch', 'key', $http, $factory, [], $factory), $connection, 'geo_live', new TableNames('h', 'n'));
        $suggestions = $geocoder->suggest('Hlavná 12 Košice', new SearchOptions(locale: 'sk', preferredCountry: 'SK'));

        $this->assertSame('/indexes/geo_live/search', $http->requests[0]->getUri()->getPath());
        $this->assertSame(['Hlavná 9/12, Košice, Slovensko', 'Hlavná, Košice, Slovensko'], array_map(static fn (\Tito10047\Geocoder\Search\Suggestion $suggestion): string => $suggestion->label, $suggestions));
    }

    public function testBuildsAWorkingAddressLookup(): void
    {
        $street = ['id' => 's909109604356383787', 'kind' => 'street', 'type' => 'street', 'name' => 'Hlavná', 'suburb' => 'Staré Mesto', 'city' => 'Košice',
            'county' => 'okres Košice I', 'region' => 'Košický kraj', 'cc' => 'SK', 'postcode' => null, '_geo' => ['lat' => 48.75, 'lng' => 21.23], 'rank' => 25000];
        $http = new FakeHttpClient(new Response(200, ['Content-Type' => 'application/json'], json_encode($street, \JSON_THROW_ON_ERROR)));
        $factory = new Psr17Factory();
        $connection = new \PDO('sqlite::memory:');
        $connection->exec('CREATE TABLE h (street_key BIGINT NOT NULL, number VARCHAR(32) NOT NULL, orientation VARCHAR(32) NULL, postcode VARCHAR(16) NULL, lat DOUBLE PRECISION NOT NULL, lon DOUBLE PRECISION NOT NULL, suburb VARCHAR(255) NULL, reference VARCHAR(32) NULL)');
        $connection->exec("INSERT INTO h VALUES (909109604356383787, '9/12', '12', '040 01', 48.72, 21.26, NULL, '7101722639')");
        $connection->exec('CREATE TABLE n (cc CHAR(2) NOT NULL, kind VARCHAR(8) NOT NULL, name VARCHAR(255) NOT NULL, lang VARCHAR(3) NOT NULL, translation VARCHAR(255) NOT NULL)');

        $lookup = GeocoderFactory::createAddressLookup(new Client('http://meilisearch', 'key', $http, $factory, [], $factory), $connection, 'geo_live', new TableNames('h', 'n'));
        $address = $lookup->findByReference('7101722639');

        $this->assertNotNull($address);
        $this->assertSame('/indexes/geo_live/documents/s909109604356383787', $http->requests[0]->getUri()->getPath());
        $this->assertSame('s909109604356383787#9/12', $address->id);
        $this->assertSame('Hlavná 9/12, Košice, SK', $address->label);
        $this->assertSame('Staré Mesto', $address->suburb);
        $this->assertSame('okres Košice I', $address->county);
        $this->assertSame('7101722639', $address->reference);
    }

    public function testRejectsAnInvalidIndexUid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GeocoderFactory::create(new Client('http://meilisearch', 'key', new FakeHttpClient(new Response())), new \PDO('sqlite::memory:'), 'geo live');
    }
}
