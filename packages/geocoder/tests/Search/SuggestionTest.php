<?php

namespace Tito10047\Geocoder\Tests\Search;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Search\QueryMatch;
use Tito10047\Geocoder\Tests\Fake\Suggestions;

final class SuggestionTest extends TestCase
{
    public function testHasNoMatchByDefault(): void
    {
        $suggestion = Suggestions::address();

        $this->assertNull($suggestion->match);
        $this->assertFalse($suggestion->isCompleteMatch(), 'An unknown match is not known to be complete.');
    }

    public function testWithMatchReturnsAModifiedCopy(): void
    {
        $suggestion = Suggestions::address();

        $matched = $suggestion->withMatch(new QueryMatch(2, 2));

        $this->assertNull($suggestion->match);
        $this->assertEquals(new QueryMatch(2, 2), $matched->match);
        $this->assertTrue($matched->isCompleteMatch());
        $this->assertEquals($suggestion, $matched->withMatch(null));
        $this->assertFalse($suggestion->withMatch(new QueryMatch(1, 2))->isCompleteMatch());
    }

    public function testWithLabelKeepsEverythingElse(): void
    {
        $suggestion = Suggestions::address()->withMatch(new QueryMatch(1, 2));

        $relabelled = $suggestion->withLabel('Hlavná 12/18, Kassa');

        $this->assertSame('Hlavná 12/18, Kassa', $relabelled->label);
        $this->assertEquals($suggestion, $relabelled->withLabel($suggestion->label));
    }
}
