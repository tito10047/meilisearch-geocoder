<?php

namespace Tito10047\Geocoder\Tests\Search;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\LabelFormatter;
use Tito10047\Geocoder\Search\SuggestionFactory;
use Tito10047\Geocoder\StreetKey;
use Tito10047\Geocoder\Tests\Fake\Documents;
use Tito10047\Geocoder\Tests\Fake\FakeNameCatalog;

final class SuggestionFactoryTest extends TestCase
{
    private const TRANSLATIONS = [
        'SK|city|Košice|hu' => 'Kassa',
        'SK|suburb|Staré Mesto|hu' => 'Óváros',
        'SK|suburb|Sever|hu' => 'Észak',
        'SK|county|okres Košice I|hu' => 'Kassai I. járás',
        'SK|region|Košický kraj|hu' => 'Kassai kerület',
        'SK|country|SK|hu' => 'Szlovákia',
    ];

    public function testSuggestsADocument(): void
    {
        $suggestion = $this->factory()->fromDocument($this->street(), 'hu');

        $this->assertSame('s1', $suggestion->id);
        $this->assertSame(Kind::Street, $suggestion->kind);
        $this->assertSame('Fő utca', $suggestion->name);
        $this->assertSame('Óváros', $suggestion->suburb);
        $this->assertSame('Kassa', $suggestion->city);
        $this->assertSame('Kassai I. járás', $suggestion->county);
        $this->assertSame('Kassai kerület', $suggestion->region);
        $this->assertSame('Fő utca, Kassa, Szlovákia', $suggestion->label);
        $this->assertNull($suggestion->reference);

        $local = $this->factory()->fromDocument($this->street(), null);
        $this->assertSame('Staré Mesto', $local->suburb);
        $this->assertSame('okres Košice I', $local->county);

        $withoutParts = $this->factory()->fromDocument(Documents::create('R1', 'Košice'), 'hu');
        $this->assertNull($withoutParts->suburb);
        $this->assertNull($withoutParts->county);
    }

    public function testSuggestsAnAddress(): void
    {
        $factory = $this->factory();
        $street = $factory->fromDocument($this->street(), 'hu');

        $address = $factory->address($this->street(), $street, new HouseNumber(new StreetKey(1), '606/42', null, new Coordinates(48.7, 21.2), 'Sever', '7101722639'), 'hu');

        $this->assertSame('s1#606/42', $address->id);
        $this->assertSame(Kind::Address, $address->kind);
        $this->assertSame(SuggestionFactory::ADDRESS_TYPE, $address->type);
        $this->assertSame('Fő utca 606/42', $address->name);
        $this->assertSame('Fő utca 606/42, Kassa, Szlovákia', $address->label);
        $this->assertSame('Észak', $address->suburb);
        $this->assertSame('Kassai I. járás', $address->county);
        $this->assertSame('040 01', $address->postcode);
        $this->assertSame('7101722639', $address->reference);

        $inherited = $factory->address($this->street(), $street, new HouseNumber(new StreetKey(1), '1', '040 11', new Coordinates(48.7, 21.2)), 'hu');
        $this->assertSame('Óváros', $inherited->suburb);
        $this->assertSame('040 11', $inherited->postcode);
        $this->assertNull($inherited->reference);
    }

    private function street(): \Tito10047\Geocoder\Document\Document
    {
        return Documents::create('s1', 'Hlavná', 'SK', DocumentKind::Street, 'street', 25000, 'Košice', 'Košický kraj', ['hu' => 'Fő utca'], '040 01', 'Staré Mesto', 'okres Košice I');
    }

    private function factory(): SuggestionFactory
    {
        $catalog = new FakeNameCatalog(self::TRANSLATIONS);

        return new SuggestionFactory($catalog, new LabelFormatter($catalog));
    }
}
