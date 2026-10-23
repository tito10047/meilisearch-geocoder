<?php

namespace Tito10047\Geocoder\Photon;

use Tito10047\Geocoder\Search\GeocoderInterface;
use Tito10047\Geocoder\Search\SearchOptions;

/**
 * The Photon API over GeocoderInterface::suggest().
 *
 * The language, the limit, the countries and the kinds of the layers go to the geocoder. The
 * filters it cannot apply (the layers within a kind, the OSM tags, the categories, the bounding
 * box) are applied to the suggestions of a full window (SearchOptions::MAX_LIMIT): a filtered
 * search may return fewer features than its limit, never one outside its filters.
 */
final readonly class PhotonSearch implements PhotonSearchInterface
{
    /**
     * @param SearchOptions $defaults the limit when the query has none, the preferred country
     */
    public function __construct(
        private GeocoderInterface $geocoder,
        private FeatureFactoryInterface $features,
        private SearchOptions $defaults = new SearchOptions(),
    ) {
    }

    public function search(PhotonQuery $query): FeatureCollection
    {
        $kinds = $query->kinds();
        if (null === $kinds) {
            return new FeatureCollection();
        }
        $limit = $query->limit ?? $this->defaults->limit;
        $options = $this->defaults
            ->withLocale($query->locale)
            ->withLimit($query->filters() ? SearchOptions::MAX_LIMIT : $limit)
            ->withKinds($kinds);
        if ([] !== $query->countries) {
            $options = $options->withCountries($query->countries);
        }

        $language = $options->language();
        $features = [];
        foreach ($this->geocoder->suggest($query->text, $options) as $suggestion) {
            $feature = $this->features->create($suggestion, $language);
            if ($query->matches($feature)) {
                $features[] = $feature;
                if (\count($features) >= $limit) {
                    break;
                }
            }
        }

        return new FeatureCollection($features);
    }
}
