<?php

namespace Tito10047\Geocoder\Tests\Import\Workspace;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\Workspace\TranslationCandidate;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\Translation;

final class TranslationCandidateTest extends TestCase
{
    public function testTellsWhetherItIsTheNameItself(): void
    {
        $this->assertTrue((new TranslationCandidate(new Translation('SK', NameKind::City, 'Bytča', 'sk', 'Bytča'), 3, 0))->isName());
        $this->assertFalse((new TranslationCandidate(new Translation('SK', NameKind::City, 'Bytča', 'hu', 'Nagybiccse'), 0, 1))->isName());
    }

    #[DataProvider('invalidVotes')]
    public function testRejectsInvalidVotes(int $addresses, int $ownNames): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TranslationCandidate(new Translation('SK', NameKind::City, 'Bytča', 'hu', 'Nagybiccse'), $addresses, $ownNames);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidVotes(): iterable
    {
        yield 'none' => [0, 0];
        yield 'negative addresses' => [-1, 2];
        yield 'negative own names' => [2, -1];
    }
}
