<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Search\Query\CountryRecognizerInterface;

/**
 * Recognizes the names of a list, compared in lower case: "slovensko" => ["SK"]; the codes are those of the list.
 */
final readonly class FakeCountryRecognizer implements CountryRecognizerInterface
{
    public const NAMES = [
        'slovensko' => ['SK'], 'slovakia' => ['SK'], 'česko' => ['CZ'], 'česká republika' => ['CZ'],
        'österreich' => ['AT'], 'austria' => ['AT'], 'italia' => ['IT'], 'polska' => ['PL'], 'włochy' => ['IT'],
        'bosna i hercegovina' => ['BA'], 'hercegovina' => ['XH'],
    ];

    /**
     * @param array<string, list<string>> $names the countries by their names in lower case
     */
    public function __construct(
        private array $names = self::NAMES,
    ) {
    }

    public function countriesNamed(string $phrase): array
    {
        return $this->names[mb_strtolower($phrase)] ?? [];
    }

    public function countryOfCode(string $code): ?string
    {
        foreach ($this->names as $countries) {
            if (\in_array($code, $countries, true)) {
                return $code;
            }
        }

        return null;
    }
}
