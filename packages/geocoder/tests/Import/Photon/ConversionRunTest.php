<?php

namespace Tito10047\Geocoder\Tests\Import\Photon;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Import\Photon\ConversionRun;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\Translation;
use Tito10047\Geocoder\StreetKey;
use Tito10047\Geocoder\Tests\Fake\ImportDocuments;
use Tito10047\Geocoder\Tests\Fake\InMemoryDocumentWriter;
use Tito10047\Geocoder\Tests\Fake\InMemoryHouseNumberWriter;
use Tito10047\Geocoder\Tests\Fake\InMemoryTranslationWriter;
use Tito10047\Geocoder\Tests\Fake\Places;
use Tito10047\Geocoder\Tests\Fake\RecordingProgress;

final class ConversionRunTest extends TestCase
{
    public function testWritesAndCounts(): void
    {
        $documents = new InMemoryDocumentWriter();
        $houseNumbers = new InMemoryHouseNumberWriter();
        $translations = new InMemoryTranslationWriter();
        $progress = new RecordingProgress();
        $run = new ConversionRun('sk.jsonl', $documents, $houseNumbers, $translations, $progress);

        $run->writeDocument(ImportDocuments::create(countryCode: 'SK'));
        $run->writeDocument(ImportDocuments::create(kind: DocumentKind::Street, countryCode: 'HU'));
        $run->writeDocument(ImportDocuments::create(kind: DocumentKind::Poi, countryCode: 'AT'));
        $run->writeDocument(ImportDocuments::create(kind: DocumentKind::Poi, countryCode: 'SK'));
        $run->writeHouseNumber(new HouseNumber(new StreetKey(1), '12', null, new Coordinates(48.0, 21.0)));
        $run->writeTranslation(new Translation('SK', NameKind::City, 'Viničky', 'hu', 'Szőlőske'));
        $run->reportProgress();

        $this->assertCount(4, $documents->documents);
        $this->assertCount(1, $houseNumbers->houseNumbers);
        $this->assertCount(1, $translations->translations);
        $this->assertEquals(new ImportCounts(1, 1, 2, 1), $run->counts());
        $this->assertSame(['AT', 'HU', 'SK'], $run->countryCodes());
        $this->assertEquals([['sk.jsonl', new ImportCounts(1, 1, 2, 1)]], $progress->calls);
    }

    public function testWorksWithoutProgress(): void
    {
        $run = new ConversionRun('sk.jsonl', new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter(), null);

        $run->reportProgress();

        $this->assertEquals(new ImportCounts(), $run->counts());
        $this->assertSame([], $run->countryCodes());
    }

    public function testRemembersThePreviousAddress(): void
    {
        $run = new ConversionRun('sk.jsonl', new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter(), null);

        $this->assertFalse($run->isPreviousAddress(Places::create(houseNumber: '1')));
        $this->assertTrue($run->isPreviousAddress(Places::create(houseNumber: '2')));
        $this->assertFalse($run->isPreviousAddress(Places::create(countryCode: 'HU')));
        $this->assertFalse($run->isPreviousAddress(Places::create(address: ['city' => 'Košice'], countryCode: 'HU')));
        $this->assertTrue($run->isPreviousAddress(Places::create(address: ['city' => 'Košice'], countryCode: 'HU')));
    }

    public function testCountsTheAddressesGivingTheSameTranslations(): void
    {
        $run = new ConversionRun('sk.jsonl', new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter(), null);
        $kassa = [new Translation('SK', NameKind::City, 'Košice', 'hu', 'Kassa')];
        $zsolna = [new Translation('SK', NameKind::City, 'Žilina', 'hu', 'Zsolna')];

        $this->assertSame([[], 0], $run->replaceAddressVotes($kassa));
        $run->repeatAddressVotes();
        $run->repeatAddressVotes();
        $this->assertSame([$kassa, 3], $run->replaceAddressVotes($zsolna));
        $this->assertSame([$zsolna, 1], $run->takeAddressVotes());
        $this->assertSame([[], 0], $run->takeAddressVotes());
    }

    public function testRemembersTheStreetOfThePreviousHouse(): void
    {
        $run = new ConversionRun('sk.jsonl', new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter(), null);

        $this->assertTrue($run->isNewStreetOfHouses(new StreetKey(1)));
        $this->assertFalse($run->isNewStreetOfHouses(new StreetKey(1)));
        $this->assertTrue($run->isNewStreetOfHouses(new StreetKey(2)));
        $this->assertTrue($run->isNewStreetOfHouses(new StreetKey(1)));
    }
}
