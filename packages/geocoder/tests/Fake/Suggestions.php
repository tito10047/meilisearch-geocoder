<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\Suggestion;

/**
 * Suggestions of the geocoder like those of the Slovak data, for the tests of the Photon API.
 */
final class Suggestions
{
    private function __construct()
    {
    }

    public static function city(): Suggestion
    {
        return new Suggestion('N530544342', Kind::Place, 'city', 'Bratislava', 'Bratislava, Bratislavský kraj, SK',
            new Coordinates(48.1435026, 17.1082867), 'SK', 'Bratislava', 'Bratislavský kraj', '815 18');
    }

    public static function village(): Suggestion
    {
        return new Suggestion('N26177645', Kind::Place, 'village', 'Viničky', 'Viničky, Košický kraj, SK',
            new Coordinates(48.3959, 21.7335), 'SK', 'Viničky', 'Košický kraj', '076 31', suburb: 'Viničky', county: 'okres Trebišov');
    }

    public static function street(): Suggestion
    {
        return new Suggestion('sSK|Košice|Hlavná', Kind::Street, 'street', 'Hlavná', 'Hlavná, Košice, SK',
            new Coordinates(48.7189389, 21.2580166), 'SK', 'Košice', 'Košický kraj', '040 01', suburb: 'Staré Mesto', county: 'okres Košice I');
    }

    public static function address(): Suggestion
    {
        return new Suggestion('sSK|Košice|Hlavná#12/18', Kind::Address, 'house', 'Hlavná 12/18', 'Hlavná 12/18, Košice, SK',
            new Coordinates(48.7189389, 21.2580166), 'SK', 'Košice', 'Košický kraj', '040 01', 'Hlavná', '12/18', 'Staré Mesto', 'okres Košice I', '7101722639');
    }

    public static function hotel(): Suggestion
    {
        return new Suggestion('R2256517', Kind::Poi, 'hotel', 'Hotel Devín', 'Hotel Devín, Bratislava, SK',
            new Coordinates(48.140187, 17.1072709), 'SK', 'Bratislava', 'Bratislavský kraj', '811 02');
    }

    public static function station(): Suggestion
    {
        return new Suggestion('N7687131573', Kind::Poi, 'station', 'Bratislava hlavná stanica', 'Bratislava hlavná stanica, Bratislava, SK',
            new Coordinates(48.1587988, 17.1060217), 'SK', 'Bratislava', 'Bratislavský kraj');
    }

    /**
     * Viedeň, outside Slovakia.
     */
    public static function vienna(): Suggestion
    {
        return new Suggestion('R109166', Kind::Place, 'city', 'Wien', 'Wien, AT', new Coordinates(48.2083537, 16.3725042), 'AT', null, 'Wien');
    }
}
