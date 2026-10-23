<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\HouseNumber\HouseNumberWriterInterface;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Import\ImportProgressInterface;
use Tito10047\Geocoder\Name\Translation;
use Tito10047\Geocoder\Name\TranslationWriterInterface;
use Tito10047\Geocoder\StreetKey;

/**
 * The writers of one conversion and what was written to them.
 *
 * @internal
 */
final class ConversionRun
{
    private int $places = 0;
    private int $streets = 0;
    private int $pois = 0;
    private int $houseNumbers = 0;
    /** @var array<string, true> */
    private array $countryCodes = [];
    private ?string $previousCountryCode = null;
    /** @var array<string, string> */
    private array $previousAddress = [];
    private ?int $previousStreetOfHouses = null;

    public function __construct(
        private readonly string $dump,
        private readonly DocumentWriterInterface $documents,
        private readonly HouseNumberWriterInterface $houseNumberWriter,
        private readonly TranslationWriterInterface $translations,
        private readonly ?ImportProgressInterface $progress,
    ) {
    }

    public function writeDocument(Document $document): void
    {
        $this->documents->write($document);
        match ($document->kind) {
            DocumentKind::Place => ++$this->places,
            DocumentKind::Street => ++$this->streets,
            DocumentKind::Poi => ++$this->pois,
        };
        $this->countryCodes[$document->countryCode] = true;
    }

    public function writeHouseNumber(HouseNumber $houseNumber): void
    {
        $this->houseNumberWriter->write($houseNumber);
        ++$this->houseNumbers;
    }

    public function writeTranslation(Translation $translation): void
    {
        $this->translations->write($translation);
    }

    /**
     * Whether the place has the address of the previous place; it becomes the previous one.
     */
    public function isPreviousAddress(Place $place): bool
    {
        if ($place->countryCode === $this->previousCountryCode && $place->address === $this->previousAddress) {
            return true;
        }
        $this->previousCountryCode = $place->countryCode;
        $this->previousAddress = $place->address;

        return false;
    }

    /**
     * Whether the street differs from that of the previous house; it becomes the previous one.
     */
    public function isNewStreetOfHouses(StreetKey $street): bool
    {
        if ($street->value === $this->previousStreetOfHouses) {
            return false;
        }
        $this->previousStreetOfHouses = $street->value;

        return true;
    }

    public function reportProgress(): void
    {
        $this->progress?->advance($this->dump, $this->counts());
    }

    public function counts(): ImportCounts
    {
        return new ImportCounts($this->places, $this->streets, $this->pois, $this->houseNumbers);
    }

    /**
     * @return list<string>
     */
    public function countryCodes(): array
    {
        $countryCodes = array_map(strval(...), array_keys($this->countryCodes));
        sort($countryCodes);

        return $countryCodes;
    }
}
