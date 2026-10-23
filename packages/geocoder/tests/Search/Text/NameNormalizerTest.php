<?php

namespace Tito10047\Geocoder\Tests\Search\Text;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Search\Text\NameNormalizer;

final class NameNormalizerTest extends TestCase
{
    #[DataProvider('provideNames')]
    public function testNormalizesWithoutIntl(string $name, string $expected): void
    {
        $this->assertSame($expected, (new NameNormalizer(useIntl: false))->normalize($name));
    }

    #[DataProvider('provideNames')]
    #[RequiresPhpExtension('intl')]
    public function testNormalizesWithIntl(string $name, string $expected): void
    {
        $this->assertSame($expected, (new NameNormalizer())->normalize($name));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideNames(): iterable
    {
        yield 'Slovak' => ['Bystré', 'bystre'];
        yield 'upper case' => ['ŽILINA', 'zilina'];
        yield 'Czech' => ['Vídeň', 'viden'];
        yield 'Hungarian' => ['Győr', 'gyor'];
        yield 'Polish' => ['Łódź', 'lodz'];
        yield 'German' => ['Straße', 'strasse'];
        yield 'Croatian' => ['Đakovo', 'dakovo'];
        yield 'Slovenian' => ['Prešernov trg', 'presernov trg'];
        yield 'Italian' => ['Forlì', 'forli'];
        yield 'whitespace' => ["  Banská \t Bystrica ", 'banska bystrica'];
        yield 'digits' => ['Praha 11', 'praha 11'];
        yield 'empty' => ['', ''];
    }

    #[RequiresPhpExtension('intl')]
    public function testTheTableFoldsLikeIntl(): void
    {
        $intl = new NameNormalizer();
        $table = new NameNormalizer(useIntl: false);
        // Latin-1 Supplement and Latin Extended-A, but the signs and the two archaic letters ĸ and ŉ
        foreach (array_merge(range(0xC0, 0xFF), range(0x100, 0x17F)) as $codePoint) {
            if (\in_array($codePoint, [0xD7, 0xF7, 0x138, 0x149], true)) {
                continue;
            }
            $letter = \IntlChar::chr($codePoint);
            $this->assertIsString($letter);
            $this->assertSame($intl->normalize($letter), $table->normalize($letter), \sprintf('U+%04X %s', $codePoint, $letter));
        }
    }
}
