<?php

namespace Tito10047\Geocoder\Tests\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\LabelFormatter;
use Tito10047\Geocoder\Search\Suggestion;
use Tito10047\Geocoder\Tests\Fake\FakeNameCatalog;

final class LabelFormatterTest extends TestCase
{
    #[DataProvider('provideSuggestions')]
    public function testFormats(Suggestion $suggestion, ?string $language, string $expected): void
    {
        $catalog = new FakeNameCatalog([
            'AT|country|AT|sk' => 'Rakúsko',
            'SK|country|SK|sk' => 'Slovensko',
            'SK|country|SK|en' => 'Slovakia',
        ]);

        $this->assertSame($expected, (new LabelFormatter($catalog))->format($suggestion, $language));
    }

    /**
     * @return iterable<string, array{Suggestion, string|null, string}>
     */
    public static function provideSuggestions(): iterable
    {
        yield 'street in a translated town' => [self::suggestion('Stephansplatz', 'AT', 'Viedeň'), 'sk', 'Stephansplatz, Viedeň, Rakúsko'];
        yield 'address' => [self::suggestion('Hlavná 12', 'SK', 'Košice', 'Košický kraj', Kind::Address), 'sk', 'Hlavná 12, Košice, Slovensko'];
        yield 'town without a region' => [self::suggestion('Viedeň', 'AT'), 'sk', 'Viedeň, Rakúsko'];
        yield 'village being its own town shows the region' => [self::suggestion('Bystré', 'SK', 'Bystré', 'Prešovský kraj'), 'sk', 'Bystré, Prešovský kraj, Slovensko'];
        yield 'town compared without diacritics and case' => [self::suggestion('Bystre', 'SK', 'BYSTRÉ', 'Prešovský kraj'), 'sk', 'Bystre, Prešovský kraj, Slovensko'];
        yield 'region equal to the name' => [self::suggestion('Wien', 'AT', null, 'Wien'), 'sk', 'Wien, Rakúsko'];
        yield 'town hides the region' => [self::suggestion('Petržalka', 'SK', 'Bratislava', 'Bratislavský kraj'), 'en', 'Petržalka, Bratislava, Slovakia'];
        yield 'blank town' => [self::suggestion('Petržalka', 'SK', ' ', 'Bratislavský kraj'), 'sk', 'Petržalka, Bratislavský kraj, Slovensko'];
        yield 'country without a translation' => [self::suggestion('Wien', 'AT'), 'en', 'Wien, AT'];
        yield 'local names' => [self::suggestion('Wien', 'AT'), null, 'Wien, AT'];
        yield 'country equal to the name' => [self::suggestion('Slovensko', 'SK'), 'sk', 'Slovensko'];
    }

    private static function suggestion(string $name, string $countryCode, ?string $city = null, ?string $region = null, Kind $kind = Kind::Place): Suggestion
    {
        return new Suggestion('X1', $kind, 'village', $name, 'ignored', new Coordinates(48.0, 17.0), $countryCode, $city, $region);
    }
}
