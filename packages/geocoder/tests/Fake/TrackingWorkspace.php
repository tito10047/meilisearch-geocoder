<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Import\Workspace\ConversionWorkspaceInterface;
use Tito10047\Geocoder\Import\Workspace\TranslationSource;
use Tito10047\Geocoder\Name\Translation;

/**
 * Delegates to a workspace and remembers whether it was closed.
 */
final class TrackingWorkspace implements ConversionWorkspaceInterface
{
    public bool $closed = false;

    public function __construct(
        private readonly ConversionWorkspaceInterface $workspace,
    ) {
    }

    public function firstOccurrence(string $key): bool
    {
        return $this->workspace->firstOccurrence($key);
    }

    public function addPlace(string $group, Document $place): void
    {
        $this->workspace->addPlace($group, $place);
    }

    public function placeGroups(): iterable
    {
        return $this->workspace->placeGroups();
    }

    public function addStreetOfWay(Document $street): void
    {
        $this->workspace->addStreetOfWay($street);
    }

    public function addStreetOfAddress(Document $street): void
    {
        $this->workspace->addStreetOfAddress($street);
    }

    public function streets(): iterable
    {
        return $this->workspace->streets();
    }

    public function voteForTranslation(Translation $translation, TranslationSource $source, int $votes = 1): void
    {
        $this->workspace->voteForTranslation($translation, $source, $votes);
    }

    public function translationCandidates(): iterable
    {
        return $this->workspace->translationCandidates();
    }

    public function close(): void
    {
        $this->closed = true;
        $this->workspace->close();
    }
}
