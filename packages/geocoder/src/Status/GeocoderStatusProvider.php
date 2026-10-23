<?php

namespace Tito10047\Geocoder\Status;

use Tito10047\Geocoder\Exception\ExceptionInterface;
use Tito10047\Geocoder\Exception\GeocoderNotConfiguredException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Import\ImporterInterface;
use Tito10047\Geocoder\Index\IndexManagerInterface;

/**
 * Reads the state of the live index from the index manager and the live build from the importer.
 *
 * An index manager throwing GeocoderNotConfiguredException (the geocoder switched off by its
 * configuration) makes the state NotConfigured; any other failure of it Unreachable.
 */
final readonly class GeocoderStatusProvider implements GeocoderStatusProviderInterface
{
    /**
     * @param string                 $indexUid the uid of the live index
     * @param ImporterInterface|null $importer reads the live build; none is read when null
     *
     * @throws InvalidArgumentException when the uid is empty
     */
    public function __construct(
        private IndexManagerInterface $indexes,
        private string $indexUid,
        private ?ImporterInterface $importer = null,
    ) {
        if ('' === $indexUid) {
            throw new InvalidArgumentException('The uid of the live index must not be empty.');
        }
    }

    public function status(): GeocoderStatus
    {
        [$liveBuild, $buildError] = $this->liveBuild();

        try {
            if (!$this->indexes->exists($this->indexUid)) {
                return new GeocoderStatus(GeocoderState::NoIndex, $this->indexUid, $this->indexes->version(), liveBuild: $liveBuild, buildError: $buildError);
            }
            $index = $this->indexes->status($this->indexUid);
        } catch (GeocoderNotConfiguredException $e) {
            return new GeocoderStatus(GeocoderState::NotConfigured, $this->indexUid, error: $e->getMessage(), liveBuild: $liveBuild, buildError: $buildError);
        } catch (ExceptionInterface $e) {
            return new GeocoderStatus(GeocoderState::Unreachable, $this->indexUid, error: $e->getMessage(), liveBuild: $liveBuild, buildError: $buildError);
        }

        return new GeocoderStatus(GeocoderState::Ready, $this->indexUid, $index->meilisearchVersion, $index, liveBuild: $liveBuild, buildError: $buildError);
    }

    /**
     * @return array{Build|null, string|null} the live build, or why it cannot be read
     */
    private function liveBuild(): array
    {
        if (!$this->importer instanceof ImporterInterface) {
            return [null, null];
        }

        try {
            return [$this->importer->live(), null];
        } catch (BuildNotFoundException) {
            return [null, null];
        } catch (ExceptionInterface $e) {
            return [null, $e->getMessage()];
        }
    }
}
