<?php

namespace Tito10047\Geocoder\Tests\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\HouseNumber\PdoHouseNumberStore;
use Tito10047\Geocoder\Search\Backend\SearchHit;
use Tito10047\Geocoder\Search\Geocoder;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\LabelFormatter;
use Tito10047\Geocoder\Search\RankedSuggestion;
use Tito10047\Geocoder\Search\Reranker;
use Tito10047\Geocoder\Search\RerankerInterface;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Storage\Pdo\PdoSchema;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\StreetKey;
use Tito10047\Geocoder\Tests\Fake\Documents;
use Tito10047\Geocoder\Tests\Fake\FakeHouseNumberStore;
use Tito10047\Geocoder\Tests\Fake\FakeNameCatalog;
use Tito10047\Geocoder\Tests\Fake\FakeSearchBackend;
use Tito10047\Geocoder\Tests\Fake\ReversingReranker;

final class GeocoderTest extends TestCase
{
    private const HLAVNA_KEY = 909109604356383787;

    private const TRANSLATIONS = [
        'AT|country|AT|sk' => 'Rakúsko',
        'SK|country|SK|sk' => 'Slovensko',
        'CZ|country|CZ|sk' => 'Česko',
        'AT|city|Wien|sk' => 'Viedeň',
        'SK|region|Košický kraj|en' => 'Košice Region',
    ];

    #[DataProvider('provideTooShort')]
    public function testDoesNotSearchATooShortText(string $text): void
    {
        $backend = new FakeSearchBackend([Documents::create('R1', 'Košice')]);

        $this->assertSame([], $this->geocoder($backend)->suggest($text));
        $this->assertSame([], $backend->queries);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTooShort(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'one letter' => ['k'];
        yield 'one multibyte letter' => [' ž '];
    }

    public function testSuggestsInTheRequestedLanguage(): void
    {
        $backend = new FakeSearchBackend([
            Documents::create('R109166', 'Wien', 'AT', type: 'city', rank: 90830, namesI18n: ['sk' => 'Viedeň', 'en' => 'Vienna']),
            Documents::create('s791028875740832172', 'Stephansplatz', 'AT', DocumentKind::Street, 'street', 25000, 'Wien', 'Wien', postcode: '1010'),
            Documents::create('W1', 'Hotel Sacher', 'AT', DocumentKind::Poi, 'hotel', 20000, 'Wien'),
        ]);

        $suggestions = $this->geocoder($backend)->suggest('  wien ', new SearchOptions(locale: 'sk_SK'));

        $this->assertSame('wien', $backend->queries[0]->text);
        $this->assertSame(['R109166', 's791028875740832172', 'W1'], $this->ids($suggestions));
        [$town, $street, $poi] = $suggestions;

        $this->assertSame(Kind::Place, $town->kind);
        $this->assertSame('city', $town->type);
        $this->assertSame('Viedeň', $town->name);
        $this->assertSame('Viedeň, Rakúsko', $town->label);
        $this->assertNull($town->city);
        $this->assertNull($town->region);
        $this->assertSame('AT', $town->countryCode);

        $this->assertSame(Kind::Street, $street->kind);
        $this->assertSame('Stephansplatz', $street->name);
        $this->assertSame('Viedeň', $street->city);
        $this->assertSame('Wien', $street->region);
        $this->assertSame('1010', $street->postcode);
        $this->assertSame('Stephansplatz, Viedeň, Rakúsko', $street->label);
        $this->assertNull($street->street);
        $this->assertNull($street->houseNumber);

        $this->assertSame(Kind::Poi, $poi->kind);
        $this->assertSame('Hotel Sacher, Viedeň, Rakúsko', $poi->label);
    }

    public function testSuggestsTheLocalNamesWithoutALocale(): void
    {
        $backend = new FakeSearchBackend([Documents::create('R109166', 'Wien', 'AT', region: 'Wien', namesI18n: ['sk' => 'Viedeň'])]);

        $suggestions = $this->geocoder($backend)->suggest('viedeň');

        $this->assertSame('Wien', $suggestions[0]->name);
        $this->assertSame('Wien', $suggestions[0]->region);
        $this->assertSame('Wien, AT', $suggestions[0]->label);
    }

    public function testTranslatesTheRegion(): void
    {
        $backend = new FakeSearchBackend([Documents::create('R1', 'Bystré', city: 'Bystré', region: 'Košický kraj')]);

        $suggestion = $this->geocoder($backend)->suggest('bystre', new SearchOptions(locale: 'en'))[0];

        $this->assertSame('Bystré', $suggestion->city);
        $this->assertSame('Košice Region', $suggestion->region);
        $this->assertSame('Bystré, Košice Region, SK', $suggestion->label);
    }

    /**
     * @param list<Kind>         $kinds
     * @param list<DocumentKind> $expectedKinds
     */
    #[DataProvider('provideBackendQueries')]
    public function testAsksTheBackend(SearchOptions $options, int $expectedLimit, array $kinds, array $expectedKinds): void
    {
        $backend = new FakeSearchBackend();

        $this->geocoder($backend)->suggest('Hlavná, Košice', $options->withKinds($kinds)->withCountries(['SK', 'CZ']));

        $query = $backend->queries[0];
        $this->assertSame('Hlavná Košice', $query->text);
        $this->assertSame($expectedLimit, $query->limit);
        $this->assertSame(['SK', 'CZ'], $query->countries);
        $this->assertSame($expectedKinds, $query->kinds);
    }

    /**
     * @return iterable<string, array{SearchOptions, int, list<Kind>, list<DocumentKind>}>
     */
    public static function provideBackendQueries(): iterable
    {
        yield 'the limit without a preferred country' => [new SearchOptions(limit: 7), 7, [], []];
        yield 'the rerank window with a preferred country' => [new SearchOptions(limit: 7, preferredCountry: 'SK'), 15, [], []];
        yield 'a limit above the window' => [new SearchOptions(limit: 20, preferredCountry: 'SK'), 20, [], []];
        yield 'kinds' => [new SearchOptions(), 5, [Kind::Place, Kind::Poi], [DocumentKind::Place, DocumentKind::Poi]];
        yield 'an address is a street' => [new SearchOptions(), 5, [Kind::Address, Kind::Street], [DocumentKind::Street]];
    }

    public function testCustomRerankWindow(): void
    {
        $backend = new FakeSearchBackend();
        $geocoder = new Geocoder($backend, new FakeHouseNumberStore(), new FakeNameCatalog(), new Reranker(), new LabelFormatter(new FakeNameCatalog()), rerankWindow: 10);

        $geocoder->suggest('bystre', new SearchOptions(preferredCountry: 'SK'));

        $this->assertSame(10, $backend->queries[0]->limit);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('provideInvalidArguments')]
    public function testRejectsInvalidArguments(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Geocoder(new FakeSearchBackend(), new FakeHouseNumberStore(), new FakeNameCatalog(), new Reranker(), new LabelFormatter(new FakeNameCatalog()), ...$arguments);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidArguments(): iterable
    {
        yield 'zero rerank window' => [['rerankWindow' => 0]];
        yield 'negative rerank window' => [['rerankWindow' => -1]];
    }

    public function testPutsTheAddressesBeforeTheirStreet(): void
    {
        $backend = new FakeSearchBackend([
            Documents::create('R1', 'Hlavná', type: 'hamlet'),
            $this->hlavna(),
            Documents::create('s12', 'Hlavná', 'CZ', DocumentKind::Street, 'street', 25000, 'Žampach'),
        ]);
        $houseNumbers = new FakeHouseNumberStore([
            $this->houseNumber('12/18', '040 01'),
            $this->houseNumber('120/7', null),
            $this->houseNumber('5', null),
        ]);

        $suggestions = $this->geocoder($backend, $houseNumbers)->suggest('Hlavná 12 Košice', new SearchOptions(locale: 'sk', limit: 10));

        $this->assertSame('Hlavná Košice', $backend->queries[0]->text);
        $this->assertSame([[self::HLAVNA_KEY, '12', 10], [12, '12', 10]], $houseNumbers->lookups);
        $this->assertSame(['R1', 's'.self::HLAVNA_KEY.'#12/18', 's'.self::HLAVNA_KEY.'#120/7', 's'.self::HLAVNA_KEY, 's12'], $this->ids($suggestions));

        $address = $suggestions[1];
        $this->assertSame(Kind::Address, $address->kind);
        $this->assertSame('house', $address->type);
        $this->assertSame('Hlavná 12/18', $address->name);
        $this->assertSame('Hlavná 12/18, Košice, Slovensko', $address->label);
        $this->assertSame('Hlavná', $address->street);
        $this->assertSame('12/18', $address->houseNumber);
        $this->assertSame('040 01', $address->postcode);
        $this->assertSame('Košice', $address->city);
        $this->assertSame('Košický kraj', $address->region);
        $this->assertEquals(new Coordinates(48.72, 21.25), $address->coordinates);
        $this->assertSame('SK', $address->countryCode);

        $this->assertSame('094 01', $suggestions[2]->postcode, 'The postcode of the street when the house has none.');
    }

    public function testSearchesTheWholeTextWhenNoStreetTakesTheNumber(): void
    {
        $backend = new FakeSearchBackend([Documents::create('R1', 'Praha 11', 'CZ', type: 'suburb')]);
        $houseNumbers = new FakeHouseNumberStore();

        $suggestions = $this->geocoder($backend, $houseNumbers)->suggest('Praha 11');

        $this->assertSame(['Praha', 'Praha 11'], array_map(static fn (\Tito10047\Geocoder\Search\Backend\BackendQuery $query): string => $query->text, $backend->queries));
        $this->assertSame(5, $backend->queries[1]->limit);
        $this->assertSame(['R1'], $this->ids($suggestions));
        $this->assertSame([], $houseNumbers->lookups);
    }

    public function testKeepsTheWholeTextInItsOrderForTheSecondSearch(): void
    {
        $backend = new FakeSearchBackend();

        $this->geocoder($backend)->suggest('Wien 3, Landstraße', new SearchOptions(preferredCountry: 'AT', countries: ['AT']));

        $this->assertSame(['Wien Landstraße', 'Wien 3 Landstraße'], array_map(static fn (\Tito10047\Geocoder\Search\Backend\BackendQuery $query): string => $query->text, $backend->queries));
        $this->assertSame(['AT'], $backend->queries[1]->countries);
        $this->assertSame(15, $backend->queries[1]->limit);
    }

    public function testDoesNotSearchAgainWhenAStreetWasFound(): void
    {
        $backend = new FakeSearchBackend([$this->hlavna()]);

        $suggestions = $this->geocoder($backend)->suggest('Hlavná 999');

        $this->assertCount(1, $backend->queries);
        $this->assertSame(['s'.self::HLAVNA_KEY], $this->ids($suggestions));
    }

    public function testDoesNotSearchAgainWithoutAHouseNumber(): void
    {
        $backend = new FakeSearchBackend();

        $this->assertSame([], $this->geocoder($backend)->suggest('Atlantis'));
        $this->assertCount(1, $backend->queries);
    }

    public function testSkipsTheHouseNumbersOfAStreetWithoutAKey(): void
    {
        $houseNumbers = new FakeHouseNumberStore();
        $backend = new FakeSearchBackend([Documents::create('way-1', 'Hlavná', kind: DocumentKind::Street, type: 'street')]);

        $suggestions = $this->geocoder($backend, $houseNumbers)->suggest('Hlavná 12');

        $this->assertSame(['way-1'], $this->ids($suggestions));
        $this->assertSame([], $houseNumbers->lookups);
    }

    /**
     * @param list<Kind>   $kinds
     * @param list<string> $expected
     */
    #[DataProvider('provideKinds')]
    public function testReturnsOnlyTheRequestedKinds(array $kinds, array $expected): void
    {
        // the fake backend does not filter, as a backend ignoring the kinds would not
        $backend = new FakeSearchBackend([
            Documents::create('R1', 'Hlavná', type: 'hamlet'),
            $this->hlavna(),
            Documents::create('W1', 'Hlavná pošta', kind: DocumentKind::Poi, type: 'post_office'),
        ]);
        $houseNumbers = new FakeHouseNumberStore([$this->houseNumber('12', null)]);

        $suggestions = $this->geocoder($backend, $houseNumbers)->suggest('Hlavná 12', new SearchOptions(kinds: $kinds));

        $this->assertSame($expected, $this->ids($suggestions));
    }

    /**
     * @return iterable<string, array{list<Kind>, list<string>}>
     */
    public static function provideKinds(): iterable
    {
        $street = 's'.self::HLAVNA_KEY;
        yield 'all' => [[], ['R1', $street.'#12', $street, 'W1']];
        yield 'places' => [[Kind::Place], ['R1']];
        yield 'streets' => [[Kind::Street], [$street]];
        yield 'addresses keep their streets' => [[Kind::Address], [$street.'#12', $street]];
        yield 'points of interest' => [[Kind::Poi], ['W1']];
    }

    public function testPrefersTheHomeCountry(): void
    {
        $backend = new FakeSearchBackend([
            Documents::create('R436705', 'Bystré', 'CZ', type: 'town', rank: 70497),
            Documents::create('N26036462', 'Rožňavské Bystré', rank: 50480),
            Documents::create('N26036911', 'Bystré', rank: 50487),
            Documents::create('R435514', 'Praha', 'CZ', type: 'city', rank: 90790),
            Documents::create('N26036928', 'Praha', rank: 50402),
        ]);
        $geocoder = $this->geocoder($backend);

        $this->assertSame(['R436705', 'N26036462', 'N26036911'], $this->ids($geocoder->suggest('bystre', new SearchOptions(limit: 3))));
        $this->assertSame(['N26036911', 'R436705', 'N26036462', 'R435514', 'N26036928'], $this->ids($geocoder->suggest('bystre', new SearchOptions(preferredCountry: 'SK'))));
        $this->assertSame(['N26036911', 'R436705'], $this->ids($geocoder->suggest('bystre', new SearchOptions(limit: 2, preferredCountry: 'SK'))), 'Cut to the limit after the rerank.');
    }

    public function testPassesTheMatchingWordsToTheReranker(): void
    {
        $backend = new FakeSearchBackend([
            new SearchHit($this->hlavna(), 2),
            new SearchHit(Documents::create('s12', 'Hlavná', 'CZ', DocumentKind::Street, 'street', 25000, 'Žampach'), 1),
        ]);

        $suggestions = $this->geocoder($backend)->suggest('hlavna kosice', new SearchOptions(preferredCountry: 'CZ'));

        $this->assertSame(['s'.self::HLAVNA_KEY, 's12'], $this->ids($suggestions));
    }

    public function testUsesAnyReranker(): void
    {
        $backend = new FakeSearchBackend([Documents::create('R1', 'Bystré'), Documents::create('R2', 'Bystrá')]);
        $reranker = new ReversingReranker();

        $suggestions = $this->geocoder($backend, reranker: $reranker)->suggest('bystr', new SearchOptions(locale: 'sk'));

        $this->assertSame(['R2', 'R1'], $this->ids($suggestions));
        $received = array_map(static fn (RankedSuggestion $candidate): Suggestion => $candidate->suggestion, $reranker->received);
        $this->assertSame(['R1', 'R2'], $this->ids($received));
        $this->assertSame('Bystré, Slovensko', $received[0]->label, 'The reranker receives complete suggestions.');
        $this->assertSame(50000, $reranker->received[0]->rank);
    }

    public function testGeocodeReturnsTheBestMatch(): void
    {
        $backend = new FakeSearchBackend([$this->hlavna(), Documents::create('s12', 'Hlavná', 'CZ', DocumentKind::Street, 'street')]);
        $houseNumbers = new FakeHouseNumberStore([$this->houseNumber('12', null), $this->houseNumber('120', null)]);

        $best = $this->geocoder($backend, $houseNumbers)->geocode('Hlavná 12, Košice', new SearchOptions(limit: 10));

        $this->assertInstanceOf(Suggestion::class, $best);
        $this->assertSame('s'.self::HLAVNA_KEY.'#12', $best->id);
        $this->assertSame(1, $backend->queries[0]->limit);
    }

    public function testGeocodeReturnsNullWithoutAMatch(): void
    {
        $this->assertNull($this->geocoder(new FakeSearchBackend())->geocode('Atlantis'));
        $this->assertNull($this->geocoder(new FakeSearchBackend())->geocode('x'));
    }

    public function testRejectsInvalidUtf8(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->geocoder(new FakeSearchBackend())->suggest("Ko\xC5");
    }

    /**
     * "Hlavná 42" over the house numbers stored by the import: the number 42 itself first, then
     * the conscription/orientation number "606/42" (42 on the street sign), then the conscription
     * number 42 ("42/7"), then the numbers that only start with 42.
     */
    public function testFindsTheOrientationNumberInTheDatabase(): void
    {
        $connection = new \PDO('sqlite::memory:', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $tables = new TableNames();
        $storage = new PdoBuildStorage($connection);
        $storage->create($tables);
        $writer = $storage->houseNumberWriter($tables);
        foreach (['420', '42/7', '606/42', '42a', '1/4', '42', '7/420'] as $number) {
            $writer->write($this->houseNumber($number, null));
        }
        $writer->close();
        (new PdoSchema($connection))->createIndexes($tables);
        $catalog = new FakeNameCatalog(self::TRANSLATIONS);
        $geocoder = new Geocoder(new FakeSearchBackend([$this->hlavna()]), new PdoHouseNumberStore($connection, $tables), $catalog, new Reranker(), new LabelFormatter($catalog));

        $suggestions = $geocoder->suggest('Hlavná 42', new SearchOptions(limit: 10));

        $numbers = array_map(static fn (Suggestion $suggestion): ?string => $suggestion->houseNumber, $suggestions);
        $this->assertSame(['42', '606/42', '42/7', '420', '42a', '7/420', null], $numbers);

        $withoutExact = $geocoder->suggest('Hlavná 606/4', new SearchOptions(limit: 10));
        $this->assertSame(['606/42', null], array_map(static fn (Suggestion $suggestion): ?string => $suggestion->houseNumber, $withoutExact));
    }

    public function testGivesTheSuburbTheCountyAndTheReference(): void
    {
        $street = Documents::create('s'.self::HLAVNA_KEY, 'Hlavná', 'SK', DocumentKind::Street, 'street', 25000, 'Košice', 'Košický kraj',
            postcode: '040 01', suburb: 'Staré Mesto', county: 'okres Košice I');
        $houseNumbers = new FakeHouseNumberStore([
            new HouseNumber(new StreetKey(self::HLAVNA_KEY), '12', null, new Coordinates(48.72, 21.25), 'Sever', '7101722639'),
        ]);

        [$address, $streetSuggestion] = $this->geocoder(new FakeSearchBackend([$street]), $houseNumbers)->suggest('Hlavná 12');

        $this->assertSame('Sever', $address->suburb);
        $this->assertSame('okres Košice I', $address->county);
        $this->assertSame('7101722639', $address->reference);
        $this->assertSame('Hlavná 12, Košice, SK', $address->label);
        $this->assertSame('Staré Mesto', $streetSuggestion->suburb);
        $this->assertSame('okres Košice I', $streetSuggestion->county);
        $this->assertNull($streetSuggestion->reference);
    }

    private function geocoder(FakeSearchBackend $backend, ?FakeHouseNumberStore $houseNumbers = null, ?RerankerInterface $reranker = null): Geocoder
    {
        $catalog = new FakeNameCatalog(self::TRANSLATIONS);

        return new Geocoder($backend, $houseNumbers ?? new FakeHouseNumberStore(), $catalog, $reranker ?? new Reranker(), new LabelFormatter($catalog));
    }

    private function hlavna(): Document
    {
        return Documents::create('s'.self::HLAVNA_KEY, 'Hlavná', 'SK', DocumentKind::Street, 'street', 25000, 'Košice', 'Košický kraj', postcode: '094 01');
    }

    private function houseNumber(string $number, ?string $postcode): HouseNumber
    {
        return new HouseNumber(new StreetKey(self::HLAVNA_KEY), $number, $postcode, new Coordinates(48.72, 21.25));
    }

    /**
     * @param list<Suggestion> $suggestions
     *
     * @return list<string>
     */
    private function ids(array $suggestions): array
    {
        return array_map(static fn (Suggestion $suggestion): string => $suggestion->id, $suggestions);
    }
}
