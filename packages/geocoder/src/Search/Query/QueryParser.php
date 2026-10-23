<?php

namespace Tito10047\Geocoder\Search\Query;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * Takes the house number out of the typed text: "Hlavná 12 Košice" searches "Hlavná Košice" and
 * then the number 12 of the streets found.
 *
 * A house number is a token like "12", "12a" or "606/42". Two or more digit groups in a row are a
 * postcode ("094 34") and stay in the text, as does a number typed alone ("12"), which is then
 * all there is to search. When several tokens look like a house number, the last one is taken:
 * the number is usually typed after the street.
 */
final class QueryParser implements QueryParserInterface
{
    private const HOUSE_NUMBER = '#^\d+[a-zA-Z]?(?:/\d+[a-zA-Z]?)?$#D';
    private const DIGITS = '/^\d+$/D';

    public function parse(string $text): ?ParsedQuery
    {
        if (1 !== preg_match('//u', $text)) {
            throw new InvalidArgumentException('The text to search must be valid UTF-8.');
        }

        // commas separate the parts of an address ("Hlavná 12, Košice") like spaces
        $tokens = preg_split('/[\s,;]+/u', $text, -1, \PREG_SPLIT_NO_EMPTY);
        if (false === $tokens || [] === $tokens) {
            return null;
        }

        $houseNumberAt = $this->houseNumberPosition($tokens);
        if (null === $houseNumberAt) {
            return new ParsedQuery(implode(' ', $tokens));
        }

        $fullText = implode(' ', $tokens);
        $houseNumber = $tokens[$houseNumberAt];
        unset($tokens[$houseNumberAt]);

        return new ParsedQuery(implode(' ', $tokens), $houseNumber, $fullText);
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
     * Whether the token is a digit group next to another one: a part of a postcode like "094 34".
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
