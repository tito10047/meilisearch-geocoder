<?php

namespace Tito10047\Geocoder\Search;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * How to search: the language of the labels, the number of suggestions, the countries, the kinds
 * and whether partial matches are wanted.
 *
 * Immutable: the with*() methods return a modified copy.
 */
final readonly class SearchOptions
{
    public const MAX_LIMIT = 20;

    /**
     * @param string|null  $locale              the language of the labels ("sk": "Viedeň"), as a locale ("sk", "sk_SK", "sk-SK"); null for the local names
     * @param int<1, 20>   $limit               the maximum number of suggestions
     * @param string|null  $preferredCountry    ISO 3166-1 alpha-2: wins among the suggestions of the same name, excludes nothing
     * @param list<string> $countries           ISO 3166-1 alpha-2: only these countries; [] for every imported country
     * @param list<Kind>   $kinds               only these kinds; [] for every kind
     * @param bool         $completeMatchesOnly only the suggestions known to match every word of the text
     *                                          (Suggestion::isCompleteMatch()): [] rather than an address
     *                                          of the same name elsewhere, for an application with a fallback
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public function __construct(
        public ?string $locale = null,
        public int $limit = 5,
        public ?string $preferredCountry = null,
        public array $countries = [],
        public array $kinds = [],
        public bool $completeMatchesOnly = false,
    ) {
        if (null !== $locale && 1 !== preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/D', $locale)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a valid locale, e.g. "sk" or "sk_SK".', $locale));
        }
        $this->assertLimit($limit);
        if (null !== $preferredCountry) {
            $this->assertCountryCode($preferredCountry);
        }
        if (!array_is_list($countries)) {
            throw new InvalidArgumentException('The countries must be a list.');
        }
        foreach ($countries as $country) {
            $this->assertCountryCode($country);
        }
        if (!array_is_list($kinds)) {
            throw new InvalidArgumentException('The kinds must be a list.');
        }
        foreach ($kinds as $kind) {
            if (!$kind instanceof Kind) {
                throw new InvalidArgumentException(\sprintf('The kinds must be instances of %s.', Kind::class));
            }
        }
    }

    /**
     * The language of the locale, ISO 639: "sk" for "sk_SK"; null for the local names.
     */
    public function language(): ?string
    {
        return null === $this->locale ? null : strtolower(preg_split('/[_-]/', $this->locale, 2)[0] ?? $this->locale);
    }

    public function withLocale(?string $locale): self
    {
        return new self($locale, $this->limit, $this->preferredCountry, $this->countries, $this->kinds, $this->completeMatchesOnly);
    }

    /**
     * @param int<1, 20> $limit
     *
     * @throws InvalidArgumentException when the limit is out of range
     */
    public function withLimit(int $limit): self
    {
        return new self($this->locale, $limit, $this->preferredCountry, $this->countries, $this->kinds, $this->completeMatchesOnly);
    }

    public function withPreferredCountry(?string $preferredCountry): self
    {
        return new self($this->locale, $this->limit, $preferredCountry, $this->countries, $this->kinds, $this->completeMatchesOnly);
    }

    /**
     * @param list<string> $countries
     */
    public function withCountries(array $countries): self
    {
        return new self($this->locale, $this->limit, $this->preferredCountry, $countries, $this->kinds, $this->completeMatchesOnly);
    }

    /**
     * @param list<Kind> $kinds
     */
    public function withKinds(array $kinds): self
    {
        return new self($this->locale, $this->limit, $this->preferredCountry, $this->countries, $kinds, $this->completeMatchesOnly);
    }

    /**
     * Only the suggestions matching every word of the text, or all of them.
     */
    public function withCompleteMatchesOnly(bool $completeMatchesOnly = true): self
    {
        return new self($this->locale, $this->limit, $this->preferredCountry, $this->countries, $this->kinds, $completeMatchesOnly);
    }

    /**
     * The PHPDoc range is not trusted: callers may not be analysed.
     */
    private function assertLimit(int $limit): void
    {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new InvalidArgumentException(\sprintf('The limit must be between 1 and %d, %d given.', self::MAX_LIMIT, $limit));
        }
    }

    private function assertCountryCode(mixed $country): void
    {
        if (!\is_string($country) || 1 !== preg_match('/^[A-Z]{2}$/D', $country)) {
            throw new InvalidArgumentException(\sprintf('A country must be an ISO 3166-1 alpha-2 code in upper case, %s given.', get_debug_type($country)));
        }
    }
}
