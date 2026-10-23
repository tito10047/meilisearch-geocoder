# Geocoder

Self-hosted geocoder and address autocomplete built from OpenStreetMap data on Meilisearch,
without a framework.

[![CI](https://github.com/tito10047/meilisearch-geocoder/actions/workflows/ci.yml/badge.svg)](https://github.com/tito10047/meilisearch-geocoder/actions/workflows/ci.yml)

Read-only split of the [tito10047/meilisearch-geocoder](https://github.com/tito10047/meilisearch-geocoder)
monorepo: report issues and send pull requests there. Symfony applications should install
[tito10047/geo-bundle](https://github.com/tito10047/geo-bundle) instead, which
configures this library.

> Work in progress: the library is not usable yet.

## Requirements

- PHP 8.2 or higher

## Installation

```console
composer require tito10047/geocoder
```

## Data

The geocoder is built from the weekly [Photon](https://github.com/komoot/photon) dumps of
OpenStreetMap data. The library downloads them, it does not distribute them: the data is
© OpenStreetMap contributors, available under the
[Open Database License](https://www.openstreetmap.org/copyright), and so is the index built
from it. Show the attribution wherever its results are displayed.

## Usage

Download the dumps (any PSR-18 client and PSR-17 factory), import them with `Import\Importer` (or the
commands of the bundle), search:

```php
use Tito10047\Geocoder\Import\Download\HttpDumpDownloader;
use Tito10047\Geocoder\Search\GeocoderFactory;
use Tito10047\Geocoder\Search\SearchOptions;

// europe/slovakia → var/geo/slovakia.jsonl.zst; downloaded again only when it changed (ETag)
$result = (new HttpDumpDownloader($psr18Client, $psr17Factory))->download('europe/slovakia', 'var/geo');

$meilisearch = new Meilisearch\Client('http://127.0.0.1:7700', $key);
$pdo = new \PDO('sqlite:var/geo/geo.sqlite');

$geocoder = GeocoderFactory::create($meilisearch, $pdo);
foreach ($geocoder->suggest('Hlavná 42 Košice', new SearchOptions(locale: 'sk', preferredCountry: 'SK')) as $suggestion) {
    echo $suggestion->label, "\n";   // "Hlavná 606/42, Košice, Slovensko": 42 finds the orientation number of 606/42
}
```

A suggestion carries the parts of its address separately: `street`, `houseNumber`, `suburb` (the
part of the town), `city`, `county` (the district: "okres Košice I"), `region`, `postcode`,
`countryCode` and `coordinates`, named in the requested language when the data translates them.

**Official address ids.** When the dump carries the official id of an address in an `extra` tag,
the import keeps it as the `reference` of the address: in Slovakia the id of the register of
addresses (`ref:minvskaddress`, its `buildingIndex`), on 91.5 % of the addresses. Configure the tag
of other countries with `SelectionRules::$referenceKeys`. Find an address by it:

```php
$lookup = GeocoderFactory::createAddressLookup($meilisearch, $pdo);
$address = $lookup->findByReference('7101722639', new SearchOptions(locale: 'sk'));
// the same Suggestion suggest() gives for that house, or null
```

**What becomes a street.** Every named `highway` of the dump is a street, except the things along
a road that are none (bus stops, platforms, motorway junctions, rest areas, …:
`SelectionRules::$streetExcludedHighways`). A street known from its houses is always one.

**Schema versions.** The tables of a build carry the version of their schema
(`PdoSchema::VERSION`, 2 since the suburbs and references of the house numbers): a build, an
export or an artifact of another version is refused and has to be imported again.

## Development

The library is developed in the [tito10047/meilisearch-geocoder](https://github.com/tito10047/meilisearch-geocoder)
monorepo, together with its Symfony bundle: install the dependencies and run the checks from its
root (see its README).

The changes of each version are listed in the [changelog](CHANGELOG.md).

## License

The geocoder is released under the MIT License, see the [LICENSE](LICENSE) file.
