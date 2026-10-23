<?php

namespace Tito10047\Geocoder\Exception;

/**
 * Thrown when the geocoder is switched off by its configuration (no search engine is configured),
 * not because a configured one is down.
 *
 * It extends GeocoderUnavailableException, so a fallback catching that one still works; catch
 * this one first to tell a deliberately missing geocoder from an outage (e.g. not to alert).
 */
final class GeocoderNotConfiguredException extends GeocoderUnavailableException
{
}
