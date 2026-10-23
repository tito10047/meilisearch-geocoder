<?php

namespace Tito10047\Geocoder\Tests\Name;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\Translation;

final class TranslationTest extends TestCase
{
    public function testKeepsValidValues(): void
    {
        $translation = new Translation('AT', NameKind::City, 'Wien', 'sk', 'Viedeň');

        $this->assertSame('Viedeň', $translation->translation);
    }

    #[DataProvider('provideInvalidValues')]
    public function testRejectsInvalidValues(string $countryCode, string $name, string $language, string $translation): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Translation($countryCode, NameKind::City, $name, $language, $translation);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'lower case country' => ['at', 'Wien', 'sk', 'Viedeň'];
        yield 'locale instead of language' => ['AT', 'Wien', 'sk_SK', 'Viedeň'];
        yield 'empty name' => ['AT', '', 'sk', 'Viedeň'];
        yield 'empty translation' => ['AT', 'Wien', 'sk', ''];
    }
}
