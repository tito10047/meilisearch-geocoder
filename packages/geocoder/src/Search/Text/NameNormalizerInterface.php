<?php

namespace Tito10047\Geocoder\Search\Text;

/**
 * Reduces a name to the form in which two spellings of it compare equal.
 */
interface NameNormalizerInterface
{
    /**
     * The name without diacritics, in lower case and with single spaces: "Bystré" and "BYSTRE"
     * both give "bystre".
     */
    public function normalize(string $name): string;
}
