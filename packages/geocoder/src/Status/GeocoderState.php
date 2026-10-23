<?php

namespace Tito10047\Geocoder\Status;

/**
 * Whether the geocoder can answer, as far as its search engine tells.
 */
enum GeocoderState: string
{
    /** The search engine answers and the live index exists. */
    case Ready = 'ready';

    /** The search engine answers, but the live index does not exist: nothing was published yet, or it was removed. */
    case NoIndex = 'no_index';

    /** The search engine cannot be reached, or answers with an error. */
    case Unreachable = 'unreachable';

    /** No search engine is configured: the geocoder is switched off (e.g. by an empty environment variable). */
    case NotConfigured = 'not_configured';

    /**
     * Whether searches can be answered.
     */
    public function isReady(): bool
    {
        return self::Ready === $this;
    }
}
