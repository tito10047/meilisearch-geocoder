<?php

namespace Tito10047\Geocoder\Import\Photon;

/**
 * Reads the parts of an address the geocoder needs: the part of the town, the town, the street,
 * the county, the region and their translations. Countries name their administrative levels differently, hence an interface.
 */
interface AddressInterpreterInterface
{
    /**
     * The town an object is in, in its local name ("" when the address has none).
     */
    public function town(Place $place): string;

    /**
     * The town a place (a town, a village, a suburb) belongs to ("" when none): never a village,
     * a village is a place of its own.
     */
    public function townOfPlace(Place $place): string;

    /**
     * The street of an address; an address without a street is named after its place ("" when neither).
     */
    public function street(Place $place): string;

    /**
     * The region (state) an object is in ("" when unknown).
     */
    public function region(Place $place): string;

    /**
     * The part of the town an object is in ("" when the address names none): "Medvedzie" of Tvrdošín.
     */
    public function suburb(Place $place): string;

    /**
     * The county (district) an object is in ("" when unknown): "okres Tvrdošín".
     */
    public function county(Place $place): string;

    /**
     * The translations of the given town of the address that differ from it, in the order of the languages.
     *
     * @param string $town the local name, as returned by town() or townOfPlace()
     *
     * @return array<string, string> language => translation
     */
    public function townTranslations(Place $place, string $town): array;

    /**
     * The translations of the region of the address that differ from it, in the order of the languages.
     *
     * @return array<string, string> language => translation
     */
    public function regionTranslations(Place $place): array;

    /**
     * The translations of the part of the town of the address that differ from it, in the order of the languages.
     *
     * @return array<string, string> language => translation
     */
    public function suburbTranslations(Place $place): array;

    /**
     * The translations of the county of the address that differ from it, in the order of the languages.
     *
     * @return array<string, string> language => translation
     */
    public function countyTranslations(Place $place): array;
}
