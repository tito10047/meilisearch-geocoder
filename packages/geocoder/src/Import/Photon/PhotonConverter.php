<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\InvalidDumpException;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\HouseNumber\HouseNumberWriterInterface;
use Tito10047\Geocoder\Import\ConverterInterface;
use Tito10047\Geocoder\Import\DistancePlaceDeduplicator;
use Tito10047\Geocoder\Import\DumpSummary;
use Tito10047\Geocoder\Import\ImportProgressInterface;
use Tito10047\Geocoder\Import\PlaceDeduplicatorInterface;
use Tito10047\Geocoder\Import\SelectionRules;
use Tito10047\Geocoder\Import\Workspace\ConversionWorkspaceFactoryInterface;
use Tito10047\Geocoder\Import\Workspace\ConversionWorkspaceInterface;
use Tito10047\Geocoder\Import\Workspace\SqliteConversionWorkspaceFactory;
use Tito10047\Geocoder\Name\Translation;
use Tito10047\Geocoder\Name\TranslationWriterInterface;

/**
 * Converts a Photon dump with the rules of the prototype (convert.php, variant D of the documents).
 *
 * Points of interest and house numbers are written while the dump is read; places and streets
 * once it is read, after the places of the same name were deduplicated and the segments of each
 * street merged. Both wait in a workspace (an SQLite file by default), so the memory does not
 * grow with the size of the dump.
 */
final readonly class PhotonConverter implements ConverterInterface
{
    private DumpReaderInterface $reader;
    private PlaceSelectorInterface $selector;
    private DocumentFactoryInterface $documents;
    private PlaceDeduplicatorInterface $deduplicator;
    private TranslationExtractorInterface $translations;
    private ConversionWorkspaceFactoryInterface $workspaces;

    /**
     * Every collaborator left out is the default one, configured by the rules.
     *
     * @param int $progressInterval the progress is reported every that many objects of the dump
     *
     * @throws InvalidArgumentException when the interval is not positive
     */
    public function __construct(
        SelectionRules $rules = new SelectionRules(),
        ?DumpReaderInterface $reader = null,
        ?PlaceSelectorInterface $selector = null,
        ?DocumentFactoryInterface $documents = null,
        ?PlaceDeduplicatorInterface $deduplicator = null,
        ?TranslationExtractorInterface $translations = null,
        ?ConversionWorkspaceFactoryInterface $workspaces = null,
        private int $progressInterval = 100_000,
    ) {
        if ($progressInterval < 1) {
            throw new InvalidArgumentException('The progress interval must be positive.');
        }

        $this->reader = $reader ?? new PhotonDumpReader();
        $this->selector = $selector ?? new RulesPlaceSelector($rules);
        $this->documents = $documents ?? new PhotonDocumentFactory($rules);
        $this->deduplicator = $deduplicator ?? new DistancePlaceDeduplicator($rules->placeDedupKm);
        $this->translations = $translations ?? new PhotonTranslationExtractor($rules->languages);
        $this->workspaces = $workspaces ?? new SqliteConversionWorkspaceFactory();
    }

    public function convert(
        string $dump,
        DocumentWriterInterface $documents,
        HouseNumberWriterInterface $houseNumbers,
        TranslationWriterInterface $translations,
        ?ImportProgressInterface $progress = null,
    ): array {
        $run = new ConversionRun($dump, $documents, $houseNumbers, $translations, $progress);
        $header = null;
        $objects = 0;

        $workspace = $this->workspaces->create();
        try {
            foreach ($this->reader->read($dump) as $record) {
                if ($record instanceof Place) {
                    $this->convertPlace($record, $workspace, $run);
                    if (0 === ++$objects % $this->progressInterval) {
                        $run->reportProgress();
                    }
                } elseif ($record instanceof CountryInfo) {
                    foreach ($record->countries as $country) {
                        $this->writeTranslations($this->translations->fromCountry($country), $workspace, $run);
                    }
                } else {
                    $header = $record;
                }
            }

            foreach ($workspace->placeGroups() as $group) {
                foreach ($this->deduplicator->deduplicate($group) as $place) {
                    $run->writeDocument($place);
                }
            }
            foreach ($workspace->streets() as $street) {
                $run->writeDocument($street);
            }
        } finally {
            $workspace->close();
        }

        if (null === $header) {
            // a reader of another implementation may not check it
            throw new InvalidDumpException($dump, 0, 'it has no header.');
        }
        $run->reportProgress();

        return [new DumpSummary(DumpSummary::nameOf($dump), $header->dataTimestamp, $run->countryCodes(), $run->counts())];
    }

    private function convertPlace(Place $place, ConversionWorkspaceInterface $workspace, ConversionRun $run): void
    {
        // the houses of a street follow each other with the same address: its translations are written already
        if (!$run->isPreviousAddress($place)) {
            $this->writeTranslations($this->translations->fromPlace($place), $workspace, $run);
        }

        $kind = $this->selector->select($place);
        if (null !== $place->houseNumber) {
            $houseNumber = $this->documents->houseNumber($place);
            if ($houseNumber instanceof HouseNumber) {
                $run->writeHouseNumber($houseNumber);
            }
            // a street known only from its houses still has to be found; the next house of the same
            // street adds nothing
            if (!$houseNumber instanceof HouseNumber || $run->isNewStreetOfHouses($houseNumber->streetKey)) {
                $street = $this->documents->streetOfAddress($place);
                if ($street instanceof Document) {
                    $workspace->addStreetOfAddress($street);
                }
            }
            if (DocumentKind::Poi !== $kind) {
                return;
            }
        }

        switch ($kind) {
            case DocumentKind::Street:
                $street = $this->documents->street($place);
                if ($street instanceof Document) {
                    $workspace->addStreetOfWay($street);
                }
                break;
            case DocumentKind::Place:
                $document = $this->documents->place($place);
                if ($document instanceof Document && $this->isFirst($document, $workspace)) {
                    $workspace->addPlace($document->countryCode.'|'.$document->name, $document);
                }
                break;
            case DocumentKind::Poi:
                $document = $this->documents->poi($place);
                if ($document instanceof Document && $this->isFirst($document, $workspace)) {
                    $run->writeDocument($document);
                }
                break;
            case null:
                break;
        }
    }

    /**
     * Whether neither the object (see duplicateKey()) nor the id of the document was seen before.
     *
     * An OSM object with several main tags is several records of the dump, e.g. a node both
     * "leisure=resort" and "tourism=zoo": the same id twice, which the index would merge into one
     * document anyway (the last one), while the counts and an NDJSON file would count it twice.
     * The first record wins: its rank is the same (one object, one importance) and keeping it
     * needs no buffering. Streets are left out: their ids ("s…") are unique by construction.
     */
    private function isFirst(Document $document, ConversionWorkspaceInterface $workspace): bool
    {
        return $workspace->firstOccurrence($this->duplicateKey($document))
            && $workspace->firstOccurrence('i|'.$document->id);
    }

    /**
     * Objects of the same name and type at the same spot (to 0.01°, about a kilometre) are one:
     * a town is both its boundary relation and its label node, a hotel both its building and its node.
     */
    private function duplicateKey(Document $document): string
    {
        return \sprintf('d|%s|%s|%s|%.2F|%.2F', $document->countryCode, $document->name, $document->type,
            $document->coordinates->latitude, $document->coordinates->longitude);
    }

    /**
     * @param list<Translation> $translations
     */
    private function writeTranslations(array $translations, ConversionWorkspaceInterface $workspace, ConversionRun $run): void
    {
        foreach ($translations as $translation) {
            // every address of a town repeats its translations: write each once
            $key = 't|'.$translation->countryCode.'|'.$translation->kind->value.'|'.$translation->name.'|'.$translation->language;
            if ($workspace->firstOccurrence($key)) {
                $run->writeTranslation($translation);
            }
        }
    }
}
