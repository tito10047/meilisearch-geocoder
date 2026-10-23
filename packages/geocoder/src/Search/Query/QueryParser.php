<?php

namespace Tito10047\Geocoder\Search\Query;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * Takes out of the typed text what is not searched by name: "Hlavná 12, 040 01 Košice, Slovensko"
 * searches "Hlavná Košice" in Slovakia and then the number 12 of the streets found.
 *
 * The text is split into parts at commas (and semicolons), and the parts into words at spaces.
 *
 * A postcode is recognized by its format and its place, as the countries of the region write it:
 *  - "040 01" (Slovakia, Czechia: a group of 3 digits and one of 2) and "31-019" (Poland), anywhere;
 *  - "04001", "1010" (4 or 5 digits) right before a word, at the beginning of a part or right after
 *    a house number: "1010 Wien", "Stephansplatz 1, 1010 Wien", "Hlavná 12 04001 Košice". Anywhere
 *    else 4 or 5 digits are a house number ("Hlavná 1010", "Hlavná 1010 Košice").
 * It is kept in the text when nothing else but numbers was typed.
 *
 * A country (with a recognizer) is recognized by its whole name, compared without diacritics and
 * case, never inside a word ("Slovenská" is not "Slovensko"):
 *  - a whole part of its own, the last or the first one: "Košice, Slovensko", "Slovensko, Košice";
 *    the last one may also be an ISO 3166-1 alpha-2 code in upper case: "Košice, SK";
 *  - otherwise among other words, at the end of the last part or the beginning of the first one,
 *    when a word remains in the part: "Kosice Slovakia", "Slovakia Kosice" (not "Polska 5, Kraków"). Such a name may also be a part of a name ("Via Italia",
 *    "Hotel Austria"): the literal reading (RecognizedCountry::$literal) is kept for the geocoder
 *    to choose.
 * It is not recognized when no word would remain: "Slovensko" alone, "Slovensko 12".
 *
 * A house number is then a token like "12", "12a" or "606/42". Two or more digit groups in a row
 * stay in the text (a postcode of another format), as does a number typed alone ("12"), which is
 * then all there is to search. When several tokens look like a house number, the last one is
 * taken: the number is usually typed after the street.
 */
final readonly class QueryParser implements QueryParserInterface
{
    private const HOUSE_NUMBER = '#^\d+[a-zA-Z]?(?:/\d+[a-zA-Z]?)?$#D';
    private const DIGITS = '/^\d+$/D';
    private const WORD = '/\p{L}/u';
    private const POSTCODE_POLISH = '/^\d{2}-\d{3}$/D';
    private const POSTCODE_FIRST_GROUP = '/^\d{3}$/D';
    private const POSTCODE_SECOND_GROUP = '/^\d{2}$/D';
    private const POSTCODE_BEFORE_TOWN = '/^\d{4,5}$/D';

    /**
     * @param CountryRecognizerInterface|null $countries the names of the countries; null to recognize none
     */
    public function __construct(
        private ?CountryRecognizerInterface $countries = null,
    ) {
    }

    public function parse(string $text): ?ParsedQuery
    {
        if (1 !== preg_match('//u', $text)) {
            throw new InvalidArgumentException('The text to search must be valid UTF-8.');
        }

        $parts = $this->parts($text);
        if ([] === $parts) {
            return null;
        }

        $postcode = $this->takePostcode($parts);
        // a part that was the postcode only ("Hlavná 12, 040 01, Košice") is no part
        $parts = array_values(array_filter($parts, static fn (array $tokens): bool => [] !== $tokens));
        $literal = $this->read($parts, $postcode);

        $country = $this->recognizeCountry($parts);
        if (null === $country) {
            return $literal;
        }

        [$countries, $part, $start, $length] = $country;
        $tokens = $parts[$part];
        $remaining = $parts;
        $remaining[$part] = [...\array_slice($tokens, 0, $start), ...\array_slice($tokens, $start + $length)];
        if (!$this->hasWord($remaining)) {
            // the country is all there is to search ("Slovensko", "Slovensko 12")
            return $literal;
        }

        return $this->read($remaining, $postcode, new RecognizedCountry(
            $countries,
            implode(' ', \array_slice($tokens, $start, $length)),
            $literal,
            implode(' ', \array_slice($tokens, 0, $start)),
            implode(' ', \array_slice($tokens, $start + $length)),
        ));
    }

    /**
     * The parts of the text between commas, as lists of words; the empty parts left out.
     *
     * @return list<non-empty-list<string>>
     */
    private function parts(string $text): array
    {
        $parts = [];
        foreach (preg_split('/[,;]/u', $text) ?: [] as $part) {
            $tokens = preg_split('/\s+/u', $part, -1, \PREG_SPLIT_NO_EMPTY);
            if (\is_array($tokens) && [] !== $tokens) {
                $parts[] = $tokens;
            }
        }

        return $parts;
    }

    /**
     * Takes the first postcode out of the parts.
     *
     * @param list<list<string>> $parts
     */
    private function takePostcode(array &$parts): ?string
    {
        // only numbers typed: they are what is searched
        if (!$this->hasWord($parts)) {
            return null;
        }

        foreach ($parts as $p => $tokens) {
            foreach ($tokens as $i => $token) {
                $next = $tokens[$i + 1] ?? null;
                $length = match (true) {
                    1 === preg_match(self::POSTCODE_POLISH, $token) => 1,
                    null !== $next && 1 === preg_match(self::POSTCODE_FIRST_GROUP, $token) && 1 === preg_match(self::POSTCODE_SECOND_GROUP, $next) => 2,
                    null !== $next && 1 === preg_match(self::POSTCODE_BEFORE_TOWN, $token) && 1 === preg_match(self::WORD, $next)
                        && (0 === $i || 1 === preg_match(self::HOUSE_NUMBER, $tokens[$i - 1])) => 1,
                    default => 0,
                };
                if ($length > 0) {
                    $parts[$p] = [...\array_slice($tokens, 0, $i), ...\array_slice($tokens, $i + $length)];

                    return implode(' ', \array_slice($tokens, $i, $length));
                }
            }
        }

        return null;
    }

    /**
     * Where a country is named: its countries, the part, the position of its first word in the part
     * and its number of words; null when none is.
     *
     * @param list<list<string>> $parts
     *
     * @return array{non-empty-list<string>, int, int, int}|null
     */
    private function recognizeCountry(array $parts): ?array
    {
        if (!$this->countries instanceof CountryRecognizerInterface || [] === $parts) {
            return null;
        }

        // a part of its own: the last one ("Košice, Slovensko", "Košice, SK") or the first one
        $last = \count($parts) - 1;
        if ($last > 0) {
            foreach ([$last, 0] as $part) {
                $tokens = $parts[$part];
                $countries = $this->countries->countriesNamed(implode(' ', $tokens));
                if ([] === $countries && $part === $last && 1 === \count($tokens)) {
                    $code = $this->countries->countryOfCode($tokens[0]);
                    $countries = null === $code ? [] : [$code];
                }
                if ([] !== $countries) {
                    return [$countries, $part, 0, \count($tokens)];
                }
            }
        }

        // among other words: at the end of the last part ("Kosice Slovakia") or the beginning of the
        // first one ("Slovakia Kosice"), the longest name first ("Bosna i Hercegovina")
        foreach ([[$last, true], [0, false]] as [$part, $atEnd]) {
            $tokens = $parts[$part];
            $count = \count($tokens);
            for ($length = $count - 1; $length >= 1; --$length) {
                $start = $atEnd ? $count - $length : 0;
                // the words left in the part must name something: "Polska 5" is a street and its number
                $others = [...\array_slice($tokens, 0, $start), ...\array_slice($tokens, $start + $length)];
                if (!$this->hasWord([$others])) {
                    continue;
                }
                $countries = $this->countries->countriesNamed(implode(' ', \array_slice($tokens, $start, $length)));
                if ([] !== $countries) {
                    return [$countries, $part, $start, $length];
                }
            }
        }

        return null;
    }

    /**
     * @param list<list<string>> $parts
     */
    private function read(array $parts, ?string $postcode, ?RecognizedCountry $country = null): ParsedQuery
    {
        $tokens = array_merge(...$parts);
        $fullText = implode(' ', $tokens);
        $houseNumberAt = $this->houseNumberPosition($tokens);
        if (null === $houseNumberAt) {
            return new ParsedQuery($fullText, null, null, $postcode, $country);
        }

        $houseNumber = $tokens[$houseNumberAt];
        unset($tokens[$houseNumberAt]);

        return new ParsedQuery(implode(' ', $tokens), $houseNumber, $fullText, $postcode, $country);
    }

    /**
     * @param list<list<string>> $parts
     */
    private function hasWord(array $parts): bool
    {
        foreach ($parts as $tokens) {
            foreach ($tokens as $token) {
                if (1 === preg_match(self::WORD, $token)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<string> $tokens
     */
    private function houseNumberPosition(array $tokens): ?int
    {
        if (\count($tokens) < 2) {
            return null;
        }

        $position = null;
        foreach ($tokens as $i => $token) {
            if (1 !== preg_match(self::HOUSE_NUMBER, $token) || $this->isInPostcode($tokens, $i)) {
                continue;
            }
            $position = $i;
        }

        return $position;
    }

    /**
     * Whether the token is a digit group next to another one: a part of a postcode of another format.
     *
     * @param list<string> $tokens
     */
    private function isInPostcode(array $tokens, int $i): bool
    {
        if (1 !== preg_match(self::DIGITS, $tokens[$i])) {
            return false;
        }

        return (isset($tokens[$i - 1]) && 1 === preg_match(self::DIGITS, $tokens[$i - 1]))
            || (isset($tokens[$i + 1]) && 1 === preg_match(self::DIGITS, $tokens[$i + 1]));
    }
}
