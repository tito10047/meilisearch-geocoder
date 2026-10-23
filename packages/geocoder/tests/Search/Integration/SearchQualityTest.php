<?php

namespace Tito10047\Geocoder\Tests\Search\Integration;

use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Search\Geocoder;
use Tito10047\Geocoder\Search\GeocoderFactory;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;

/**
 * The quality checks of the research (_lab/scripts/quality.py, quality-rerank.py and
 * quality-city.py) against a real index: does the expected place come first while typing? And
 * does it stay first when only the complete matches are asked for, while a place outside the
 * imported countries finds nothing then?
 *
 * Needs the variant D index of _docs/01-merania.md; skipped unless MEILISEARCH_URL is set
 * (MEILISEARCH_KEY and MEILISEARCH_INDEX, "geo" by default, too) and when that index is missing
 * or holds fewer than MIN_DOCUMENTS documents. The scores are written to STDERR.
 */
#[Group('integration')]
final class SearchQualityTest extends TestCase
{
    /**
     * Fewer documents than this are not the full index the scores were measured on.
     */
    private const MIN_DOCUMENTS = 1000;

    /**
     * The typed text, the expected name (null for any) and country, as a trip planner expects them.
     */
    private const PLACES = [
        ['kosic', 'Košice', 'SK'], ['Košice', 'Košice', 'SK'], ['brati', 'Bratislava', 'SK'],
        ['zilina', 'Žilina', 'SK'], ['banska bys', 'Banská Bystrica', 'SK'], ['poprad', 'Poprad', 'SK'],
        ['nitra', 'Nitra', 'SK'], ['presov', 'Prešov', 'SK'], ['trnava', 'Trnava', 'SK'],
        ['piestany', 'Piešťany', 'SK'], ['stary smok', 'Starý Smokovec', 'SK'], ['bystre', 'Bystré', 'SK'],
        ['praha', 'Praha', 'CZ'], ['brno', 'Brno', 'CZ'], ['ostrava', 'Ostrava', 'CZ'],
        ['wien', 'Wien', 'AT'], ['viedeň', 'Wien', 'AT'], ['vieden', 'Wien', 'AT'], ['graz', 'Graz', 'AT'],
        ['budapest', 'Budapest', 'HU'], ['budapešť', 'Budapest', 'HU'], ['krakow', 'Kraków', 'PL'],
        ['zakopane', 'Zakopane', 'PL'], ['warszawa', 'Warszawa', 'PL'], ['varšava', 'Warszawa', 'PL'],
        ['zagreb', 'Zagreb', 'HR'], ['split', 'Split', 'HR'], ['makarska', 'Makarska', 'HR'],
        ['ljubljana', 'Ljubljana', 'SI'], ['münchen', 'München', 'DE'], ['munchen', 'München', 'DE'],
        ['mníchov', 'München', 'DE'], ['berlin', 'Berlin', 'DE'], ['roma', 'Roma', 'IT'], ['rím', 'Roma', 'IT'],
        ['venezia', 'Venezia', 'IT'], ['benátky', 'Venezia', 'IT'], ['lignano', 'Lignano Sabbiadoro', 'IT'],
        ['bibione', 'Bibione', 'IT'], ['tatralandia', 'Tatralandia', 'SK'], ['letisko bratislava', null, 'SK'],
        ['mlynske nivy', 'Mlynské nivy', 'SK'], ['hlavna kosice', 'Hlavná', 'SK'],
    ];

    /**
     * Street and town typed in another language: the typed text, the street, its town and country.
     */
    private const STREETS = [
        ['stephansplatz wien', 'Stephansplatz', 'Wien', 'AT'], ['stephansplatz viedeň', 'Stephansplatz', 'Wien', 'AT'],
        ['stephansplatz vieden', 'Stephansplatz', 'Wien', 'AT'], ['stephansplatz vídeň', 'Stephansplatz', 'Wien', 'AT'],
        ['stephansplatz wiedeń', 'Stephansplatz', 'Wien', 'AT'], ['stephansplatz bécs', 'Stephansplatz', 'Wien', 'AT'],
        ['stephansplatz vienna', 'Stephansplatz', 'Wien', 'AT'], ['stephansplatz vied', 'Stephansplatz', 'Wien', 'AT'],
        ['marienplatz münchen', 'Marienplatz', 'München', 'DE'], ['marienplatz mníchov', 'Marienplatz', 'München', 'DE'],
        ['marienplatz mnichov', 'Marienplatz', 'München', 'DE'], ['marienplatz munich', 'Marienplatz', 'München', 'DE'],
        ['marienplatz monachium', 'Marienplatz', 'München', 'DE'],
        ['via del corso roma', 'Via del Corso', 'Roma', 'IT'], ['via del corso rím', 'Via del Corso', 'Roma', 'IT'],
        ['via del corso rome', 'Via del Corso', 'Roma', 'IT'], ['via del corso rzym', 'Via del Corso', 'Roma', 'IT'],
        ['václavské náměstí praha', 'Václavské náměstí', 'Praha', 'CZ'],
        ['václavské náměstí prague', 'Václavské náměstí', 'Praha', 'CZ'],
        ['vaclavske namesti praga', 'Václavské náměstí', 'Praha', 'CZ'],
        ['andrássy út budapest', 'Andrássy út', 'Budapest', 'HU'], ['andrássy út budapešť', 'Andrássy út', 'Budapest', 'HU'],
        ['andrassy ut budapeszt', 'Andrássy út', 'Budapest', 'HU'],
        ['floriańska kraków', 'Floriańska', 'Kraków', 'PL'], ['florianska krakov', 'Floriańska', 'Kraków', 'PL'],
        ['floriańska cracow', 'Floriańska', 'Kraków', 'PL'],
        ['obchodná bratislava', 'Obchodná', 'Bratislava', 'SK'], ['obchodná pozsony', 'Obchodná', 'Bratislava', 'SK'],
        ['obchodna pressburg', 'Obchodná', 'Bratislava', 'SK'],
        ['hlavná košice', 'Hlavná', 'Košice', 'SK'], ['hlavná kassa', 'Hlavná', 'Košice', 'SK'],
        ['hlavna koszyce', 'Hlavná', 'Košice', 'SK'],
        ['ilica zagreb', 'Ilica', 'Zagreb', 'HR'], ['ilica záhreb', 'Ilica', 'Zagreb', 'HR'],
        ['ilica zágráb', 'Ilica', 'Zagreb', 'HR'],
        ['prešernov trg ljubljana', 'Prešernov trg', 'Ljubljana', 'SI'], ['prešernov trg lublaň', 'Prešernov trg', 'Ljubljana', 'SI'],
        ['presernov trg lubiana', 'Prešernov trg', 'Ljubljana', 'SI'],
    ];

    /**
     * Addresses outside the imported countries whose words partly match imported names
     * ("Champs" hamlets in Switzerland, the Champs-Élysées of Rust in Germany).
     */
    private const OUTSIDE = [
        'Champs-Élysées, Paris', 'Champs-Élysées, Par', 'champs elysees paris', 'rue de rivoli paris',
        'oxford street london', 'gran via madrid', 'Nevsky prospekt, Sankt Peterburg',
    ];

    private Geocoder $geocoder;

    protected function setUp(): void
    {
        $url = getenv('MEILISEARCH_URL');
        if (!\is_string($url) || '' === $url) {
            $this->markTestSkipped('Set MEILISEARCH_URL (and MEILISEARCH_KEY, MEILISEARCH_INDEX) to run the search quality checks.');
        }
        $key = getenv('MEILISEARCH_KEY');
        $index = getenv('MEILISEARCH_INDEX');
        $index = \is_string($index) && '' !== $index ? $index : 'geo';
        $client = new Client($url, \is_string($key) && '' !== $key ? $key : null);

        // the scores are those of the full index: a missing index or a small one (an integration
        // test's fixture) is not a failure of the ranking
        try {
            $documents = $client->index($index)->stats()['numberOfDocuments'] ?? null;
        } catch (ApiException $exception) {
            $this->markTestSkipped(\sprintf('The index "%s" cannot be read (%s): the quality checks need the full variant D index.', $index, $exception->getMessage()));
        }
        $documents = \is_int($documents) ? $documents : 0;
        if ($documents < self::MIN_DOCUMENTS) {
            $this->markTestSkipped(\sprintf('The index "%s" holds %d documents, fewer than %d: the quality checks need the full variant D index.', $index, $documents, self::MIN_DOCUMENTS));
        }

        // the house numbers and the translations are not measured: empty tables
        $connection = new \PDO('sqlite::memory:');
        $connection->exec('CREATE TABLE geo_house (street_key BIGINT NOT NULL, number VARCHAR(32) NOT NULL, orientation VARCHAR(32) NULL, postcode VARCHAR(16) NULL, lat DOUBLE PRECISION NOT NULL, lon DOUBLE PRECISION NOT NULL, suburb VARCHAR(255) NULL, reference VARCHAR(32) NULL)');
        $connection->exec('CREATE TABLE geo_name (cc CHAR(2) NOT NULL, kind VARCHAR(8) NOT NULL, name VARCHAR(255) NOT NULL, lang VARCHAR(3) NOT NULL, translation VARCHAR(255) NOT NULL)');

        $this->geocoder = GeocoderFactory::create($client, $connection, $index);
    }

    /**
     * @param int $expectedTop1 the score of _docs/01-merania.md and 03-meilisearch-index-a-ranking.md
     */
    #[DataProvider('providePreferredCountries')]
    public function testPlaces(?string $preferredCountry, int $expectedTop1, bool $completeMatchesOnly): void
    {
        $options = new SearchOptions(preferredCountry: $preferredCountry, completeMatchesOnly: $completeMatchesOnly);
        $top1 = $top3 = 0;
        $misses = $partial = [];
        $time = 0.0;
        foreach (self::PLACES as [$text, $name, $countryCode]) {
            $start = hrtime(true);
            $suggestions = $this->geocoder->suggest($text, $options);
            $time += (hrtime(true) - $start) / 1e6;

            $position = $this->position($suggestions, static fn (Suggestion $suggestion): bool => (null === $name || $suggestion->name === $name) && $suggestion->countryCode === $countryCode);
            $top1 += (int) (0 === $position);
            $top3 += (int) (null !== $position && $position < 3);
            if (0 !== $position) {
                $misses[] = $this->miss($text, $position, $suggestions);
            }
            if (null !== $position && !$suggestions[$position]->isCompleteMatch()) {
                $partial[] = $this->miss($text, $position, $suggestions);
            }
        }

        $this->report(\sprintf('places, preferred country %s%s', $preferredCountry ?? 'none', $completeMatchesOnly ? ', complete matches only' : ''), $top1, $top3, \count(self::PLACES), $time, $misses);
        $this->assertGreaterThanOrEqual($expectedTop1, $top1);
        $this->assertSame([], $partial, 'The expected places match every word typed.');
    }

    /**
     * @return iterable<string, array{string|null, int, bool}>
     */
    public static function providePreferredCountries(): iterable
    {
        foreach ([false => '', true => ', complete matches only'] as $completeMatchesOnly => $name) {
            yield 'none'.$name => [null, 42, (bool) $completeMatchesOnly];
            yield 'SK'.$name => ['SK', 43, (bool) $completeMatchesOnly];
            yield 'CZ'.$name => ['CZ', 42, (bool) $completeMatchesOnly];
            yield 'HU'.$name => ['HU', 42, (bool) $completeMatchesOnly];
            yield 'AT'.$name => ['AT', 42, (bool) $completeMatchesOnly];
        }
    }

    #[DataProvider('provideCompleteMatchesOnly')]
    public function testStreetsInTowns(bool $completeMatchesOnly): void
    {
        $options = new SearchOptions(completeMatchesOnly: $completeMatchesOnly);
        $top1 = $top3 = 0;
        $misses = $partial = [];
        $time = 0.0;
        foreach (self::STREETS as [$text, $name, $city, $countryCode]) {
            $start = hrtime(true);
            $suggestions = $this->geocoder->suggest($text, $options);
            $time += (hrtime(true) - $start) / 1e6;

            $position = $this->position($suggestions, static fn (Suggestion $suggestion): bool => $suggestion->name === $name && $suggestion->city === $city && $suggestion->countryCode === $countryCode);
            $top1 += (int) (0 === $position);
            $top3 += (int) (null !== $position && $position < 3);
            if (0 !== $position) {
                $misses[] = $this->miss($text, $position, $suggestions);
            }
            if (null !== $position && !$suggestions[$position]->isCompleteMatch()) {
                $partial[] = $this->miss($text, $position, $suggestions);
            }
        }

        $this->report('streets in towns'.($completeMatchesOnly ? ', complete matches only' : ''), $top1, $top3, \count(self::STREETS), $time, $misses);
        $this->assertGreaterThanOrEqual(36, $top1);
        $this->assertSame([], $partial, 'The expected streets match every word typed.');
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function provideCompleteMatchesOnly(): iterable
    {
        yield 'all matches' => [false];
        yield 'complete matches only' => [true];
    }

    public function testFindsNothingOutsideTheImportedCountries(): void
    {
        $found = [];
        foreach (self::OUTSIDE as $text) {
            $partial = $this->geocoder->suggest($text);
            fwrite(\STDERR, \sprintf("\n%-34s -> %d partial matches: %s", $text, \count($partial), implode(' | ', array_map(static fn (Suggestion $suggestion): string => $suggestion->label, \array_slice($partial, 0, 2)))));
            $complete = $this->geocoder->suggest($text, new SearchOptions(completeMatchesOnly: true));
            if ([] !== $complete) {
                $found[] = $this->miss($text, null, $complete);
            }
        }
        fwrite(\STDERR, "\n");

        $this->assertSame([], $found, 'A text outside the imported countries matches no suggestion completely.');
    }

    /**
     * @param list<Suggestion>           $suggestions
     * @param callable(Suggestion): bool $expected
     */
    private function position(array $suggestions, callable $expected): ?int
    {
        foreach ($suggestions as $position => $suggestion) {
            if ($expected($suggestion)) {
                return $position;
            }
        }

        return null;
    }

    /**
     * @param list<Suggestion> $suggestions
     */
    private function miss(string $text, ?int $position, array $suggestions): string
    {
        return \sprintf('%s %-26s -> %s', null === $position ? '--' : '#'.($position + 1), $text, implode(' | ', array_map(
            static fn (Suggestion $suggestion): string => \sprintf('%s (%s, %s)', $suggestion->name, $suggestion->city ?? '-', $suggestion->countryCode),
            \array_slice($suggestions, 0, 3),
        )));
    }

    /**
     * @param list<string> $misses
     */
    private function report(string $title, int $top1, int $top3, int $count, float $milliseconds, array $misses): void
    {
        fwrite(\STDERR, \sprintf("\n%s: top1 %d/%d, top3 %d/%d, %.1f ms per suggest()\n", $title, $top1, $count, $top3, $count, $milliseconds / $count));
        foreach ($misses as $miss) {
            fwrite(\STDERR, '   '.$miss."\n");
        }
    }
}
