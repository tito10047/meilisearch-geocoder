<?php

namespace Tito10047\Geocoder\Tests\Index;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\PrefixSearch;
use Tito10047\Geocoder\Index\ProximityPrecision;

final class IndexSettingsTest extends TestCase
{
    public function testDefaultsAreTheTunedSettingsOfVariantD(): void
    {
        $this->assertSame([
            'searchableAttributes' => ['names', 'cities'],
            'displayedAttributes' => ['id', 'kind', 'type', 'name', 'names_i18n', 'city', 'region', 'cc', 'postcode', '_geo', 'rank'],
            'filterableAttributes' => [[
                'attributePatterns' => ['cc', 'kind'],
                'features' => ['facetSearch' => false, 'filter' => ['equality' => true, 'comparison' => false]],
            ]],
            'sortableAttributes' => [],
            'rankingRules' => ['words', 'typo', 'proximity', 'rank:desc', 'attribute', 'exactness'],
            'proximityPrecision' => 'byAttribute',
            'prefixSearch' => 'indexingTime',
            'facetSearch' => false,
            'typoTolerance' => ['disableOnNumbers' => true],
            'pagination' => ['maxTotalHits' => 20],
        ], (new IndexSettings())->toArray());
    }

    public function testWithoutFilterableAttributes(): void
    {
        $this->assertSame([], (new IndexSettings(filterableAttributes: []))->toArray()['filterableAttributes']);
    }

    public function testHashIsStableAndDependsOnEverySetting(): void
    {
        $default = new IndexSettings();

        $this->assertSame((new IndexSettings())->hash(), $default->hash());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $default->hash());

        $variants = [
            new IndexSettings(searchableAttributes: ['names', 'city']),
            new IndexSettings(searchableAttributes: ['cities', 'names']),
            new IndexSettings(displayedAttributes: ['*']),
            new IndexSettings(filterableAttributes: ['cc']),
            new IndexSettings(sortableAttributes: ['rank']),
            new IndexSettings(rankingRules: ['words', 'typo', 'proximity', 'attribute', 'exactness', 'rank:desc']),
            new IndexSettings(proximityPrecision: ProximityPrecision::ByWord),
            new IndexSettings(prefixSearch: PrefixSearch::Disabled),
            new IndexSettings(facetSearch: true),
            new IndexSettings(typoDisableOnNumbers: false),
            new IndexSettings(maxTotalHits: 50),
        ];
        $hashes = array_map(static fn (IndexSettings $settings): string => $settings->hash(), $variants);
        $hashes[] = $default->hash();

        $this->assertCount(\count($hashes), array_unique($hashes));
    }

    public function testReadsTheSettingsReturnedByMeilisearch(): void
    {
        // GET /indexes/{uid}/settings of Meilisearch 1.35.1 after applying the defaults
        $settings = [
            'displayedAttributes' => ['id', 'kind', 'type', 'name', 'names_i18n', 'city', 'region', 'cc', 'postcode', '_geo', 'rank'],
            'searchableAttributes' => ['names', 'cities'],
            'filterableAttributes' => [['attributePatterns' => ['cc', 'kind'], 'features' => ['facetSearch' => false, 'filter' => ['equality' => true, 'comparison' => false]]]],
            'sortableAttributes' => [],
            'rankingRules' => ['words', 'typo', 'proximity', 'rank:desc', 'attribute', 'exactness'],
            'stopWords' => [],
            'synonyms' => [],
            'distinctAttribute' => null,
            'proximityPrecision' => 'byAttribute',
            'typoTolerance' => ['enabled' => true, 'minWordSizeForTypos' => ['oneTypo' => 5, 'twoTypos' => 9], 'disableOnWords' => [], 'disableOnAttributes' => [], 'disableOnNumbers' => true],
            'pagination' => ['maxTotalHits' => 20],
            'facetSearch' => false,
            'prefixSearch' => 'indexingTime',
        ];

        $this->assertEquals(new IndexSettings(), IndexSettings::fromArray($settings));
        $this->assertSame((new IndexSettings())->hash(), IndexSettings::fromArray($settings)->hash());
    }

    public function testRoundTrip(): void
    {
        $settings = new IndexSettings(displayedAttributes: ['*'], filterableAttributes: [], proximityPrecision: ProximityPrecision::ByWord, maxTotalHits: 100);

        $this->assertEquals($settings, IndexSettings::fromArray($settings->toArray()));
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('provideUnsupportedSettings')]
    public function testRejectsUnsupportedSettings(array $override): void
    {
        $this->expectException(InvalidArgumentException::class);

        IndexSettings::fromArray(array_merge((new IndexSettings())->toArray(), $override));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideUnsupportedSettings(): iterable
    {
        yield 'filterable as plain attributes' => [['filterableAttributes' => ['cc', 'kind']]];
        yield 'comparison filters' => [['filterableAttributes' => [['attributePatterns' => ['cc'], 'features' => ['facetSearch' => false, 'filter' => ['equality' => true, 'comparison' => true]]]]]];
        yield 'facet search on an attribute' => [['filterableAttributes' => [['attributePatterns' => ['cc'], 'features' => ['facetSearch' => true, 'filter' => ['equality' => true, 'comparison' => false]]]]]];
        yield 'unknown proximity precision' => [['proximityPrecision' => 'byLetter']];
        yield 'missing prefix search' => [['prefixSearch' => null]];
        yield 'missing typo tolerance' => [['typoTolerance' => null]];
        yield 'malformed pagination' => [['pagination' => ['maxTotalHits' => '20']]];
        yield 'searchable not a list' => [['searchableAttributes' => 'names']];
        yield 'ranking rules with a number' => [['rankingRules' => ['words', 1]]];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('provideInvalidArguments')]
    public function testRejectsInvalidArguments(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        new IndexSettings(...$arguments);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidArguments(): iterable
    {
        yield 'no searchable attribute' => [['searchableAttributes' => []]];
        yield 'searchable not a list' => [['searchableAttributes' => ['a' => 'names']]];
        yield 'attribute with a space' => [['searchableAttributes' => ['first name']]];
        yield 'empty attribute' => [['displayedAttributes' => ['']]];
        yield 'attribute not a string' => [['displayedAttributes' => [1]]];
        yield 'repeated attribute' => [['displayedAttributes' => ['id', 'id']]];
        yield 'wildcard with others' => [['displayedAttributes' => ['*', 'id']]];
        yield 'wildcard filterable' => [['filterableAttributes' => ['*']]];
        yield 'no ranking rule' => [['rankingRules' => []]];
        yield 'ranking rules not a list' => [['rankingRules' => [1 => 'words']]];
        yield 'unknown ranking rule' => [['rankingRules' => ['words', 'popularity']]];
        yield 'custom rule without direction' => [['rankingRules' => ['rank']]];
        yield 'repeated ranking rule' => [['rankingRules' => ['words', 'words']]];
        yield 'no hits' => [['maxTotalHits' => 0]];
    }
}
