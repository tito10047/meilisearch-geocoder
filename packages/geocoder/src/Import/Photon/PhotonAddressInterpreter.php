<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Name\NameKind;

/**
 * The address rules of the prototype (convert.php), which work for the countries of central Europe.
 *
 * The translations of a town or of a part of a town are trusted for the catalog of names only when
 * one of them, in any language, is its local name: then the name and the translations come from
 * the same boundary. A building in Bytča tagged "addr:city=Bratislava" has "city:sk=Bytča" and no
 * translation "Bratislava". In the dumps of 2026-10-17 that leaves out the town translations of
 * 0.2 % of the addresses of Slovakia, 0.4 % of Czechia, 6 % of Austria (an address naming its
 * village, translated as its municipality). Counties and regions come from the boundaries (the
 * few objects naming their Swiss canton name it by its abbreviation, "ZH"), and some are named in
 * two languages, which no translation equals ("Bern/Berne"): their translations are always trusted.
 */
final readonly class PhotonAddressInterpreter implements AddressInterpreterInterface
{
    /**
     * @param list<string> $languages ISO 639-1: the translations to read
     *
     * @throws InvalidArgumentException when a language is not an ISO 639 code in lower case
     */
    public function __construct(
        private array $languages,
    ) {
        foreach ($languages as $language) {
            if (!\is_string($language) || 1 !== preg_match('/^[a-z]{2,3}$/D', $language)) {
                throw new InvalidArgumentException('The languages must be a list of ISO 639 codes in lower case.');
            }
        }
    }

    public function town(Place $place): string
    {
        $address = $place->address;

        return $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['locality'] ?? '';
    }

    public function townOfPlace(Place $place): string
    {
        return $place->address['city'] ?? $place->address['town'] ?? '';
    }

    public function street(Place $place): string
    {
        return $place->address['street'] ?? $place->address['place'] ?? '';
    }

    public function region(Place $place): string
    {
        return $place->address['state'] ?? '';
    }

    public function suburb(Place $place): string
    {
        return $place->address['suburb'] ?? '';
    }

    public function county(Place $place): string
    {
        return $place->address['county'] ?? '';
    }

    public function townTranslations(Place $place, string $town): array
    {
        if ('' === $town) {
            return [];
        }

        // the translations sit under the key of the level the address has, whichever name was used
        $address = $place->address;
        $key = isset($address['city']) ? 'city' : (isset($address['town']) ? 'town' : 'village');

        return $this->translations($address, $key, $town);
    }

    public function regionTranslations(Place $place): array
    {
        $region = $this->region($place);

        return '' === $region ? [] : $this->translations($place->address, 'state', $region);
    }

    public function suburbTranslations(Place $place): array
    {
        $suburb = $this->suburb($place);

        return '' === $suburb ? [] : $this->translations($place->address, 'suburb', $suburb);
    }

    public function countyTranslations(Place $place): array
    {
        $county = $this->county($place);

        return '' === $county ? [] : $this->translations($place->address, 'county', $county);
    }

    public function catalogTranslations(Place $place, NameKind $kind): array
    {
        $address = $place->address;
        [$name, $key] = match ($kind) {
            NameKind::City => [$this->town($place), isset($address['city']) ? 'city' : (isset($address['town']) ? 'town' : 'village')],
            NameKind::Suburb => [$this->suburb($place), 'suburb'],
            NameKind::County => [$this->county($place), 'county'],
            NameKind::Region => [$this->region($place), 'state'],
            NameKind::Country => ['', ''],
        };
        if ('' === $name || (\in_array($kind, [NameKind::City, NameKind::Suburb], true) && !$this->translatesItself($address, $key, $name))) {
            return [];
        }

        $translations = [];
        foreach ($this->languages as $language) {
            $translation = $address[$key.':'.$language] ?? null;
            if (null !== $translation && '' !== $translation) {
                $translations[$language] = $translation;
            }
        }

        return $translations;
    }

    /**
     * Whether one of the translations of the level, in any language, is its local name.
     *
     * @param array<string, string> $address
     */
    private function translatesItself(array $address, string $key, string $name): bool
    {
        $prefix = $key.':';
        foreach ($address as $addressKey => $value) {
            if ($value === $name && str_starts_with($addressKey, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $address
     *
     * @return array<string, string>
     */
    private function translations(array $address, string $key, string $local): array
    {
        $translations = [];
        foreach ($this->languages as $language) {
            $translation = $address[$key.':'.$language] ?? null;
            if (null !== $translation && '' !== $translation && $translation !== $local) {
                $translations[$language] = $translation;
            }
        }

        return $translations;
    }
}
