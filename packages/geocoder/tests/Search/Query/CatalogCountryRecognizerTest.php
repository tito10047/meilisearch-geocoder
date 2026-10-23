<?php

namespace Tito10047\Geocoder\Tests\Search\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Search\Query\CatalogCountryRecognizer;
use Tito10047\Geocoder\Search\Text\NameNormalizer;
use Tito10047\Geocoder\Tests\Fake\FakeCountryNameSource;

final class CatalogCountryRecognizerTest extends TestCase
{
    private const NAMES = [
        'SK' => ['sk' => 'Slovensko', 'en' => 'Slovakia', 'pl' => 'Słowacja', 'und' => 'Slovensko'],
        'CZ' => ['sk' => 'Česko', 'en' => 'Czechia'],
        'BA' => ['hr' => 'Bosna i Hercegovina', 'und' => 'Bosna i Hercegovina / Босна и Херцеговина'],
        'CH' => ['und' => 'Schweiz/Suisse/Svizzera/Svizra'],
        'XA' => ['en' => 'Twinland'],
        'XB' => ['en' => 'Twinland'],
    ];

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideNames')]
    public function testRecognizesTheNames(string $phrase, array $expected): void
    {
        $this->assertSame($expected, (new CatalogCountryRecognizer(new FakeCountryNameSource(self::NAMES)))->countriesNamed($phrase));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provideNames(): iterable
    {
        yield 'a translation' => ['Slovakia', ['SK']];
        yield 'the own name' => ['Slovensko', ['SK']];
        yield 'without diacritics' => ['Slowacja', ['SK']];
        yield 'in upper case' => ['ČESKO', ['CZ']];
        yield 'repeated spaces' => ['Bosna  i   Hercegovina', ['BA']];
        yield 'one of several own names' => ['Suisse', ['CH']];
        yield 'one of several own names, another script' => ['Босна и Херцеговина', ['BA']];
        yield 'a name of two countries' => ['Twinland', ['XA', 'XB']];
        yield 'an alias of an imported country' => ['Česká republika', ['CZ']];
        yield 'an alias of a country not imported' => ['Republik Österreich', []];
        yield 'a part of a name' => ['Slovenská', []];
        yield 'a part of a long name' => ['Hercegovina', []];
        yield 'a longer phrase' => ['Slovensko Košice', []];
        yield 'blank' => ['  ', []];
    }

    public function testRecognizesTheCodesOfTheCountriesKnown(): void
    {
        $recognizer = new CatalogCountryRecognizer(new FakeCountryNameSource(self::NAMES));

        $this->assertSame('SK', $recognizer->countryOfCode('SK'));
        $this->assertSame('CH', $recognizer->countryOfCode('CH'));
        $this->assertNull($recognizer->countryOfCode('sk'), 'A code in lower case is a word.');
        $this->assertNull($recognizer->countryOfCode('AT'), 'Not a country known.');
        $this->assertNull($recognizer->countryOfCode('SVK'));
    }

    public function testUsesTheAliasesGiven(): void
    {
        $recognizer = new CatalogCountryRecognizer(new FakeCountryNameSource(self::NAMES), new NameNormalizer(), ['SK' => ['Slovenská republika'], 'AT' => ['Österreich']]);

        $this->assertSame(['SK'], $recognizer->countriesNamed('slovenska republika'));
        $this->assertSame([], $recognizer->countriesNamed('Česká republika'));
        $this->assertSame([], $recognizer->countriesNamed('Österreich'));
    }

    public function testReadsTheNamesOnceUntilReset(): void
    {
        $source = new FakeCountryNameSource(self::NAMES);
        $recognizer = new CatalogCountryRecognizer($source);
        $this->assertSame(0, $source->reads, 'Nothing is read before the first question.');

        $recognizer->countriesNamed('Slovensko');
        $recognizer->countriesNamed('Česko');
        $recognizer->countryOfCode('SK');
        $this->assertSame(1, $source->reads);

        $recognizer->reset();
        $this->assertSame('SK', $recognizer->countryOfCode('SK'));
        $this->assertSame(2, $source->reads);
    }
}
