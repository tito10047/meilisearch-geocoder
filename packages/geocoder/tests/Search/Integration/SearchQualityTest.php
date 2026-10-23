<?php

namespace Tito10047\Geocoder\Tests\Search\Integration;

use Meilisearch\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Search\Geocoder;
use Tito10047\Geocoder\Search\GeocoderFactory;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;

/**
 * The quality checks of the research (_lab/scripts/quality.py, quality-rerank.py and
 * quality-city.py) against a real index: does the expected place come first while typing?
 *
 * Needs the variant D index of _docs/01-merania.md; skipped unless MEILISEARCH_URL is set
 * (MEILISEARCH_KEY and MEILISEARCH_INDEX, "geo" by default, too). The scores are written to STDERR.
 */
#[Group('integration')]
final class SearchQualityTest extends TestCase
{
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

    private Geocoder $geocoder;

    protected function setUp(): void
    {
        $url = getenv('MEILISEARCH_URL');
        if (!\is_string($url) || '' === $url) {
            $this->markTestSkipped('Set MEILISEARCH_URL (and MEILISEARCH_KEY, MEILISEARCH_INDEX) to run the search quality checks.');
        }
        $key = getenv('MEILISEARCH_KEY');
        $index = getenv('MEILISEARCH_INDEX');

        // the house numbers and the translations are not measured: empty tables
        $connection = new \PDO('sqlite::memory:');
        $connection->exec('CREATE TABLE geo_house (street_key BIGINT NOT NULL, number VARCHAR(32) NOT NULL, orientation VARCHAR(32) NULL, postcode VARCHAR(16) NULL, lat DOUBLE PRECISION NOT NULL, lon DOUBLE PRECISION NOT NULL)');
        $connection->exec('CREATE TABLE geo_name (cc CHAR(2) NOT NULL, kind VARCHAR(8) NOT NULL, name VARCHAR(255) NOT NULL, lang VARCHAR(3) NOT NULL, translation VARCHAR(255) NOT NULL)');

        $this->geocoder = GeocoderFactory::create(
            new Client($url, \is_string($key) && '' !== $key ? $key : null),
            $connection,
            \is_string($index) && '' !== $index ? $index : 'geo',
        );
    }

    /**
     * @param int $expectedTop1 the score of _docs/01-merania.md and 03-meilisearch-index-a-ranking.md
     */
    #[DataProvider('providePreferredCountries')]
    public function testPlaces(?string $preferredCountry, int $expectedTop1): void
    {
        $options = new SearchOptions(preferredCountry: $preferredCountry);
        $top1 = $top3 = 0;
        $misses = [];
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
        }

        $this->report(\sprintf('places, preferred country %s', $preferredCountry ?? 'none'), $top1, $top3, \count(self::PLACES), $time, $misses);
        $this->assertGreaterThanOrEqual($expectedTop1, $top1);
    }

    /**
     * @return iterable<string, array{string|null, int}>
     */
    public static function providePreferredCountries(): iterable
    {
        yield 'none' => [null, 42];
        yield 'SK' => ['SK', 43];
        yield 'CZ' => ['CZ', 42];
        yield 'HU' => ['HU', 42];
        yield 'AT' => ['AT', 42];
    }

    public function testStreetsInTowns(): void
    {
        $top1 = $top3 = 0;
        $misses = [];
        $time = 0.0;
        foreach (self::STREETS as [$text, $name, $city, $countryCode]) {
            $start = hrtime(true);
            $suggestions = $this->geocoder->suggest($text);
            $time += (hrtime(true) - $start) / 1e6;

            $position = $this->position($suggestions, static fn (Suggestion $suggestion): bool => $suggestion->name === $name && $suggestion->city === $city && $suggestion->countryCode === $countryCode);
            $top1 += (int) (0 === $position);
            $top3 += (int) (null !== $position && $position < 3);
            if (0 !== $position) {
                $misses[] = $this->miss($text, $position, $suggestions);
            }
        }

        $this->report('streets in towns', $top1, $top3, \count(self::STREETS), $time, $misses);
        $this->assertGreaterThanOrEqual(36, $top1);
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
