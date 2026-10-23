<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * The address rules of the prototype (convert.php), which work for the countries of central Europe.
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
