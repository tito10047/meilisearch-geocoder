<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Name\Translation;
use Tito10047\Geocoder\Name\TranslationWriterInterface;

final class InMemoryTranslationWriter implements TranslationWriterInterface
{
    /** @var list<Translation> */
    public array $translations = [];
    public bool $closed = false;

    public function write(Translation $translation): void
    {
        $this->translations[] = $translation;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    /**
     * The translations as "CC|kind|name|lang" => translation.
     *
     * @return array<string, string>
     */
    public function map(): array
    {
        $map = [];
        foreach ($this->translations as $translation) {
            $map[$translation->countryCode.'|'.$translation->kind->value.'|'.$translation->name.'|'.$translation->language] = $translation->translation;
        }

        return $map;
    }
}
