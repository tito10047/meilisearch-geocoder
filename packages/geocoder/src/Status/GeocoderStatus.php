<?php

namespace Tito10047\Geocoder\Status;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Index\IndexStatus;

/**
 * The state of the geocoder: its live index in the search engine and the live build in the
 * database, each with what went wrong reading it.
 *
 * The two are read independently: the build is known while the search engine is down, and the
 * index while the database is.
 */
final readonly class GeocoderStatus
{
    /**
     * @param string           $indexUid           the uid of the live index
     * @param string|null      $meilisearchVersion "1.35.1"; null when the search engine did not answer
     * @param IndexStatus|null $index              the live index; set exactly when the state is Ready
     * @param string|null      $error              why the search engine did not answer; set exactly when the state is Unreachable or NotConfigured
     * @param Build|null       $liveBuild          the live build; null when none was published, or it cannot be read ($buildError)
     * @param string|null      $buildError         why the live build cannot be read; null when it was read, or none was published
     *
     * @throws InvalidArgumentException when the values do not fit the state
     */
    public function __construct(
        public GeocoderState $state,
        public string $indexUid,
        public ?string $meilisearchVersion = null,
        public ?IndexStatus $index = null,
        public ?string $error = null,
        public ?Build $liveBuild = null,
        public ?string $buildError = null,
    ) {
        if ('' === $indexUid) {
            throw new InvalidArgumentException('The uid of the live index must not be empty.');
        }
        if ((GeocoderState::Ready === $state) !== ($index instanceof IndexStatus)) {
            throw new InvalidArgumentException('The status of the live index is given exactly when the geocoder is ready.');
        }
        $failed = GeocoderState::Unreachable === $state || GeocoderState::NotConfigured === $state;
        if ($failed !== (null !== $error && '' !== $error)) {
            throw new InvalidArgumentException('An error is given exactly when the search engine is unreachable or not configured.');
        }
        if ($failed && null !== $meilisearchVersion) {
            throw new InvalidArgumentException('A search engine that did not answer has no known version.');
        }
        if ($liveBuild instanceof Build && null !== $buildError) {
            throw new InvalidArgumentException('A live build that was read has no error.');
        }
    }
}
