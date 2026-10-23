<?php

namespace Tito10047\Geocoder\Status;

/**
 * Tells whether the geocoder can answer, for health checks and status pages.
 */
interface GeocoderStatusProviderInterface
{
    /**
     * Asks the search engine and the database for the state of the geocoder.
     *
     * Never throws because of an outage: an unreachable search engine or database is part of
     * the status (GeocoderState::Unreachable, GeocoderStatus::$buildError).
     */
    public function status(): GeocoderStatus;
}
