<?php

namespace Tito10047\Geocoder\Import\Workspace;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Name\Translation;

/**
 * A candidate translation of a name and how many records voted for it.
 */
final readonly class TranslationCandidate
{
    /**
     * @param Translation $translation equal to the name when the records say it is not translated
     * @param int         $addresses   the addresses voting for it (TranslationSource::Address)
     * @param int         $ownNames    the records of the named thing voting for it (TranslationSource::OwnName)
     *
     * @throws InvalidArgumentException when a count is negative or both are zero
     */
    public function __construct(
        public Translation $translation,
        public int $addresses,
        public int $ownNames,
    ) {
        if ($addresses < 0 || $ownNames < 0 || 0 === $addresses + $ownNames) {
            throw new InvalidArgumentException(\sprintf('A candidate translation needs votes, %d and %d given.', $addresses, $ownNames));
        }
    }

    /**
     * Whether it says the name is not translated: the translation is the name itself.
     */
    public function isName(): bool
    {
        return $this->translation->translation === $this->translation->name;
    }
}
