<?php

namespace Tito10047\Geocoder\Tests\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\SearchOptions;

final class SearchOptionsTest extends TestCase
{
    public function testHasDefaults(): void
    {
        $options = new SearchOptions();

        $this->assertNull($options->locale);
        $this->assertNull($options->language());
        $this->assertSame(5, $options->limit);
        $this->assertNull($options->preferredCountry);
        $this->assertSame([], $options->countries);
        $this->assertSame([], $options->kinds);
    }

    #[DataProvider('provideLanguages')]
    public function testLanguageOfTheLocale(string $locale, string $language): void
    {
        $this->assertSame($language, (new SearchOptions(locale: $locale))->language());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideLanguages(): iterable
    {
        yield 'language' => ['sk', 'sk'];
        yield 'underscore' => ['sk_SK', 'sk'];
        yield 'hyphen' => ['de-AT', 'de'];
        yield 'script' => ['sr_Latn_RS', 'sr'];
    }

    public function testWithersReturnModifiedCopies(): void
    {
        $options = new SearchOptions();

        $modified = $options
            ->withLocale('sk')
            ->withLimit(10)
            ->withPreferredCountry('SK')
            ->withCountries(['SK', 'CZ'])
            ->withKinds([Kind::Place]);

        $this->assertEquals(new SearchOptions('sk', 10, 'SK', ['SK', 'CZ'], [Kind::Place]), $modified);
        $this->assertEquals(new SearchOptions(), $options);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('provideInvalidArguments')]
    public function testRejectsInvalidArguments(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SearchOptions(...$arguments);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidArguments(): iterable
    {
        yield 'empty locale' => [['locale' => '']];
        yield 'upper case locale' => [['locale' => 'SK']];
        yield 'limit zero' => [['limit' => 0]];
        yield 'limit above maximum' => [['limit' => SearchOptions::MAX_LIMIT + 1]];
        yield 'lower case preferred country' => [['preferredCountry' => 'sk']];
        yield 'alpha-3 country' => [['countries' => ['SVK']]];
        yield 'country not a string' => [['countries' => [1]]];
        yield 'countries not a list' => [['countries' => ['a' => 'SK']]];
        yield 'kind not an enum' => [['kinds' => ['place']]];
        yield 'kinds not a list' => [['kinds' => ['a' => Kind::Place]]];
    }
}
