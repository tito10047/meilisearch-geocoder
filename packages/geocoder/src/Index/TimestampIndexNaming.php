<?php

namespace Tito10047\Geocoder\Index;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Index\Meilisearch\MeilisearchApi;

/**
 * Builds named after the live uid and their UTC start time: "geo" and "geo_20261023120000".
 *
 * The timestamps sort chronologically and are the same suffix the tables of the build get.
 */
final readonly class TimestampIndexNaming implements IndexNamingInterface
{
    private const TIMESTAMP_FORMAT = 'YmdHis';

    /**
     * @throws InvalidArgumentException when the live uid is not a valid index uid or too long for a suffix
     */
    public function __construct(private string $liveUid = 'geo')
    {
        MeilisearchApi::assertIndexUid($liveUid);
        MeilisearchApi::assertIndexUid($this->buildUid(new \DateTimeImmutable('@0')));
    }

    public function liveUid(): string
    {
        return $this->liveUid;
    }

    public function buildUid(\DateTimeImmutable $startedAt): string
    {
        return $this->liveUid.'_'.$startedAt->setTimezone(new \DateTimeZone('UTC'))->format(self::TIMESTAMP_FORMAT);
    }

    public function isBuildUid(string $uid): bool
    {
        return 1 === preg_match('/^'.preg_quote($this->liveUid, '/').'_\d{14}$/D', $uid);
    }

    public function buildSuffix(string $buildUid): string
    {
        if (!$this->isBuildUid($buildUid)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not the uid of a build of the index %s.', $buildUid, $this->liveUid));
        }

        return substr($buildUid, \strlen($this->liveUid));
    }
}
