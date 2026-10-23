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

### Matching

The search engine finds a document when only some of the words typed match: a fragment of an
address is still found while it is typed, and "Champs-Élysées, Paris" finds the Champs-Élysées of
Rust (Germany) and the Swiss hamlets "Champs …" of an index without France. Every suggestion of
`suggest()` therefore tells how well it matched, `$suggestion->match`, a `QueryMatch`:

| | |
|---|---|
| `matchedWords` | the words of the searched text it matched |
| `queryWords` | the words of the searched text |
| `isComplete()` | every word matched (also `$suggestion->isCompleteMatch()`) |

The searched text is the typed one without the house number: "Hlavná 12, Košice" has 2 words, and
an address matched as its street (the house number is found in the database, or the street is
suggested without it). When no street takes the number, the whole text is searched again ("Praha
11": a part of Prague) and the number is a word. Meilisearch splits the words at spaces and
punctuation ("Champs-Élysées, Paris" has 3), counts a word matched with a typo ("kosice hlvana"),
as the beginning of a longer word (the last word, still being typed: "Champs-Élysées, Par") or
joined with the next one ("mlynskenivy") as matched, and searches the first 10 words only.

An application falling back to another geocoder when the address lies outside the imported
countries asks for the complete matches only, and falls back on `[]`:

```php
$suggestions = $geocoder->suggest('Champs-Élysées, Paris', (new SearchOptions(locale: 'sk'))->withCompleteMatchesOnly());
// [] without France: the partial matches are dropped after the reranking
```

`match` is null when nothing was searched (`findByReference()`: a reference identifies the house)
and when the search engine does not tell (an index ranked without the `words` rule); such a
suggestion is not a complete match. Words the index does not hold make every match partial: the
**postcodes and the names of countries are not searchable**, so "Hlavná 12, 040 01 Košice" or
"Stephansplatz, Wien, Österreich" match nothing completely — strip them from addresses formatted
for the post before searching with this option. The 43 places and 38 streets in towns of the
quality checks (`tests/Search/Integration/SearchQualityTest.php`) keep their top suggestion with
it.

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
