<?php

namespace Tito10047\Geocoder\Tests\Import\Workspace;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Import\Workspace\SqliteConversionWorkspace;
use Tito10047\Geocoder\Import\Workspace\TranslationCandidate;
use Tito10047\Geocoder\Import\Workspace\TranslationSource;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\Translation;
use Tito10047\Geocoder\Tests\Fake\ImportDocuments;

final class SqliteConversionWorkspaceTest extends TestCase
{
    private string $path;
    private SqliteConversionWorkspace $workspace;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/geocoder-workspace-test-'.bin2hex(random_bytes(6)).'.sqlite';
        $this->workspace = new SqliteConversionWorkspace($this->path);
    }

    protected function tearDown(): void
    {
        $this->workspace->close();
    }

    public function testFirstOccurrence(): void
    {
        $this->assertTrue($this->workspace->firstOccurrence('a'));
        $this->assertFalse($this->workspace->firstOccurrence('a'));
        $this->assertTrue($this->workspace->firstOccurrence('b'));

        // beyond the keys remembered in memory, the database answers
        for ($i = 0; $i < 5000; ++$i) {
            $this->workspace->firstOccurrence('key '.$i);
        }
        $this->assertFalse($this->workspace->firstOccurrence('a'));
        $this->assertFalse($this->workspace->firstOccurrence('key 4999'));
    }

    public function testPlaceGroups(): void
    {
        $this->workspace->addPlace('SK|Košice', ImportDocuments::create(id: 'R1', name: 'Košice'));
        $this->workspace->addPlace('SK|Žilina', ImportDocuments::create(id: 'R2', name: 'Žilina'));
        $this->workspace->addPlace('SK|Košice', ImportDocuments::create(id: 'N1', name: 'Košice', postcode: '041 26'));

        $groups = [];
        foreach ($this->workspace->placeGroups() as $group) {
            $groups[] = array_map(static fn (Document $document): string => $document->id, $group);
        }

        $this->assertSame([['R1', 'N1'], ['R2']], $groups);
    }

    public function testNoPlaces(): void
    {
        $this->assertSame([], iterator_to_array($this->workspace->placeGroups()));
    }

    public function testKeepsTheDocumentsWhole(): void
    {
        $document = ImportDocuments::create(id: 'N1', postcode: '076 31', latitude: 48.3996662, longitude: 21.747595, rank: 50480);
        $this->workspace->addPlace('SK|Viničky', $document);

        foreach ($this->workspace->placeGroups() as $group) {
            $this->assertEquals([$document], $group);
        }
    }

    public function testMergesTheStreets(): void
    {
        // a street known from an address first, then from two ways: the last way wins…
        $this->workspace->addStreetOfAddress($this->street('s1', ['Hlavná'], 48.1));
        $this->workspace->addStreetOfWay($this->street('s1', ['Hlavná'], 48.2));
        $this->workspace->addStreetOfWay($this->street('s1', ['Hlavná'], 48.3));
        // …unless a way has translations
        $this->workspace->addStreetOfWay($this->street('s2', ['Fő utca', 'Hlavná'], 48.4));
        $this->workspace->addStreetOfWay($this->street('s2', ['Fő utca'], 48.5));
        // an address does not replace a street
        $this->workspace->addStreetOfWay($this->street('s3', ['Nová'], 48.6));
        $this->workspace->addStreetOfAddress($this->street('s3', ['Nová'], 48.7));
        // a street known only from addresses keeps the first
        $this->workspace->addStreetOfAddress($this->street('s4', ['Viničky'], 48.8));
        $this->workspace->addStreetOfAddress($this->street('s4', ['Viničky'], 48.9));

        $streets = [];
        foreach ($this->workspace->streets() as $document) {
            $streets[$document->id] = $document->coordinates->latitude;
        }

        // in the order the streets were first added
        $this->assertSame(['s1' => 48.3, 's2' => 48.4, 's3' => 48.6, 's4' => 48.8], $streets);
    }

    public function testCountsTheVotesForTranslationsByNameAndLanguage(): void
    {
        $bytca = new Translation('SK', NameKind::City, 'Bratislava', 'sk', 'Bytča');
        $same = new Translation('SK', NameKind::City, 'Bratislava', 'sk', 'Bratislava');
        $pozsony = new Translation('SK', NameKind::City, 'Bratislava', 'hu', 'Pozsony');
        $this->workspace->voteForTranslation($same, TranslationSource::Address, 40);
        $this->workspace->voteForTranslation($bytca, TranslationSource::Address);
        $this->workspace->voteForTranslation($pozsony, TranslationSource::OwnName);
        $this->workspace->voteForTranslation($same, TranslationSource::Address, 2);
        $this->workspace->voteForTranslation($same, TranslationSource::OwnName);
        $this->workspace->voteForTranslation($pozsony, TranslationSource::Address, 7);

        $this->assertEquals([
            [new TranslationCandidate($pozsony, 7, 1)],
            [new TranslationCandidate($same, 42, 1), new TranslationCandidate($bytca, 1, 0)],
        ], iterator_to_array($this->workspace->translationCandidates(), false));
    }

    /**
     * The votes summed in memory and those already in the database add up.
     */
    public function testAddsTheVotesBeyondTheMemory(): void
    {
        $kassa = new Translation('SK', NameKind::City, 'Košice', 'hu', 'Kassa');
        $this->workspace->voteForTranslation($kassa, TranslationSource::Address);
        for ($i = 0; $i < 10000; ++$i) {
            $this->workspace->voteForTranslation(new Translation('SK', NameKind::Suburb, 'part '.$i, 'hu', 'rész '.$i), TranslationSource::Address);
        }
        $this->workspace->voteForTranslation($kassa, TranslationSource::Address, 2);

        $candidates = iterator_to_array($this->workspace->translationCandidates(), false);

        $this->assertCount(10001, $candidates);
        $this->assertEquals([new TranslationCandidate($kassa, 3, 0)], $candidates[0]);
    }

    public function testRejectsNoVotes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->workspace->voteForTranslation(new Translation('SK', NameKind::City, 'Košice', 'hu', 'Kassa'), TranslationSource::Address, 0);
    }

    public function testNoTranslationCandidates(): void
    {
        $this->assertSame([], iterator_to_array($this->workspace->translationCandidates()));
    }

    public function testCloseDeletesTheFile(): void
    {
        $this->assertFileExists($this->path);

        $this->workspace->close();
        $this->workspace->close();

        $this->assertFileDoesNotExist($this->path);
    }

    public function testCannotBeUsedOnceClosed(): void
    {
        $this->workspace->close();

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('closed');

        $this->workspace->addPlace('SK|Viničky', ImportDocuments::create());
    }

    public function testCannotVoteOnceClosed(): void
    {
        $this->workspace->close();

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('closed');

        $this->workspace->voteForTranslation(new Translation('SK', NameKind::City, 'Košice', 'hu', 'Kassa'), TranslationSource::Address);
    }

    public function testRejectsAnEmptyPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SqliteConversionWorkspace('');
    }

    public function testFailsWhenTheDatabaseCannotBeCreated(): void
    {
        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Cannot create the workspace database');

        new SqliteConversionWorkspace('/nonexistent/directory/workspace.sqlite');
    }

    /**
     * @param non-empty-list<string> $names
     */
    private function street(string $id, array $names, float $latitude): Document
    {
        return ImportDocuments::create(id: $id, kind: DocumentKind::Street, type: 'street', name: $names[0], names: $names, latitude: $latitude);
    }
}
