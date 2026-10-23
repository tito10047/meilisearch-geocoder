<?php

namespace Tito10047\Geocoder\Search\Text;

/**
 * Normalizes names with ext-intl when it is loaded, otherwise with a built-in table.
 *
 * ext-intl ("Any-Latin; Latin-ASCII; Lower()") handles every script. Without it, the Latin letters
 * of Unicode's Latin-1 Supplement and Latin Extended-A blocks (all the alphabets of the countries
 * Photon covers in Europe but a few) are folded by the table, with the same result as intl; other
 * scripts are only trimmed and compared as typed. ext-intl is suggested, not required: the
 * fallback is exact for the European Latin alphabets, which is what the reranker compares.
 */
final class NameNormalizer implements NameNormalizerInterface
{
    private const RULES = 'Any-Latin; Latin-ASCII; Lower()';

    /**
     * The ASCII form of each letter, as Latin-ASCII transliterates it.
     */
    private const FOLDING = [
        'a' => 'ÀÁÂÃÄÅàáâãäåĀāĂăĄą',
        'ae' => 'Ææ',
        'c' => 'ÇçĆćĈĉĊċČč',
        'd' => 'ÐðĎďĐđ',
        'e' => 'ÈÉÊËèéêëĒēĔĕĖėĘęĚě',
        'g' => 'ĜĝĞğĠġĢģ',
        'h' => 'ĤĥĦħ',
        'i' => 'ÌÍÎÏìíîïĨĩĪīĬĭĮįİı',
        'ij' => 'Ĳĳ',
        'j' => 'Ĵĵ',
        'k' => 'Ķķ',
        'l' => 'ĹĺĻļĽľĿŀŁł',
        'n' => 'ÑñŃńŅņŇňŊŋ',
        'o' => 'ÒÓÔÕÖØòóôõöøŌōŎŏŐő',
        'oe' => 'Œœ',
        'r' => 'ŔŕŖŗŘř',
        's' => 'ŚśŜŝŞşŠšſ',
        'ss' => 'ß',
        't' => 'ŢţŤťŦŧ',
        'th' => 'Þþ',
        'u' => 'ÙÚÛÜùúûüŨũŪūŬŭŮůŰűŲų',
        'w' => 'Ŵŵ',
        'y' => 'ÝýÿŶŷŸ',
        'z' => 'ŹźŻżŽž',
    ];

    private ?\Transliterator $transliterator = null;

    /**
     * @var array<string, string>|null
     */
    private ?array $folding = null;

    /**
     * @param bool $useIntl false to use the built-in table even when ext-intl is loaded
     */
    public function __construct(bool $useIntl = true)
    {
        if ($useIntl && class_exists(\Transliterator::class)) {
            $this->transliterator = \Transliterator::create(self::RULES);
        }
    }

    public function normalize(string $name): string
    {
        $normalized = $this->transliterator?->transliterate($name);
        if (!\is_string($normalized)) {
            // strtolower() only changes ASCII letters since PHP 8.2, whatever the locale
            $normalized = strtolower(strtr($name, $this->folding()));
        }

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }

    /**
     * @return array<string, string>
     */
    private function folding(): array
    {
        if (null === $this->folding) {
            $this->folding = [];
            foreach (self::FOLDING as $ascii => $letters) {
                foreach (preg_split('//u', $letters, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $letter) {
                    $this->folding[$letter] = $ascii;
                }
            }
        }

        return $this->folding;
    }
}
