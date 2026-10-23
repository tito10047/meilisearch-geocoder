<?php

namespace Tito10047\Geocoder\Search\Query;

use Tito10047\Geocoder\Name\CountryNameSourceInterface;
use Tito10047\Geocoder\Search\Text\NameNormalizer;
use Tito10047\Geocoder\Search\Text\NameNormalizerInterface;

/**
 * Recognizes the names of the imported countries the name source lists (the translations of the
 * import languages, English and the countries' own names), and a few official long forms the
 * import does not know ("Česká republika").
 *
 * The names are read once, on the first question, and kept by the instance: there are at most a
 * few dozen per country, so the memory is bounded by the number of imported countries. A
 * long-running worker should call reset() after a publication (Symfony does it between the
 * requests through the "kernel.reset" tag).
 */
final class CatalogCountryRecognizer implements CountryRecognizerInterface
{
    /**
     * Official and other common names of the countries of the region that their OSM names lack;
     * used for the imported countries only.
     *
     * @var array<string, list<string>>
     */
    public const DEFAULT_ALIASES = [
        'AT' => ['Republik Österreich'],
        'CZ' => ['Česká republika', 'Czech Republic', 'Tschechische Republik'],
        'DE' => ['Bundesrepublik Deutschland'],
        'HR' => ['Republika Hrvatska'],
        'HU' => ['Magyar Köztársaság'],
        'IT' => ['Repubblica Italiana'],
        'PL' => ['Rzeczpospolita Polska'],
        'SI' => ['Republika Slovenija'],
        'SK' => ['Slovenská republika', 'Slovak Republic', 'Slowakische Republik'],
    ];

    /** The separators of the several names of a country's own name: "Schweiz/Suisse/Svizzera/Svizra". */
    private const NAME_SEPARATORS = '~\s*[/;]\s*~u';

    /**
     * @var array<string, list<string>>|null the countries by their normalized names; null until read
     */
    private ?array $countries = null;

    /**
     * @var array<string, true> the codes of the countries known
     */
    private array $codes = [];

    /**
     * @param array<string, list<string>> $aliases more names by country code, recognized for the imported countries only
     */
    public function __construct(
        private readonly CountryNameSourceInterface $names,
        private readonly NameNormalizerInterface $normalizer = new NameNormalizer(),
        private readonly array $aliases = self::DEFAULT_ALIASES,
    ) {
    }

    public function countriesNamed(string $phrase): array
    {
        $key = $this->normalizer->normalize($phrase);

        return '' === $key ? [] : ($this->countries()[$key] ?? []);
    }

    public function countryOfCode(string $code): ?string
    {
        if (1 !== preg_match('/^[A-Z]{2}$/D', $code)) {
            return null;
        }
        $this->countries();

        return isset($this->codes[$code]) ? $code : null;
    }

    /**
     * Forgets the names read, e.g. after a publication changed the imported countries.
     */
    public function reset(): void
    {
        $this->countries = null;
        $this->codes = [];
    }

    /**
     * @return array<string, list<string>>
     */
    private function countries(): array
    {
        if (null !== $this->countries) {
            return $this->countries;
        }

        $countries = [];
        $codes = [];
        foreach ($this->names->countryNames() as $translation) {
            $codes[$translation->countryCode] = true;
            foreach (preg_split(self::NAME_SEPARATORS, $translation->translation) ?: [] as $name) {
                $this->add($countries, $name, $translation->countryCode);
            }
        }
        foreach ($this->aliases as $code => $aliases) {
            if (isset($codes[$code])) {
                foreach ($aliases as $alias) {
                    $this->add($countries, $alias, $code);
                }
            }
        }

        $this->codes = $codes;

        return $this->countries = $countries;
    }

    /**
     * @param array<string, list<string>> $countries
     */
    private function add(array &$countries, string $name, string $code): void
    {
        $key = $this->normalizer->normalize($name);
        if ('' === $key || \in_array($code, $countries[$key] ?? [], true)) {
            return;
        }
        $countries[$key][] = $code;
    }
}
