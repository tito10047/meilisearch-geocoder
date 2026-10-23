<?php

namespace Tito10047\GeoBundle\Autocomplete;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\UX\Autocomplete\AutocompleteResults;
use Symfony\UX\Autocomplete\AutocompleterInterface;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\GeocoderInterface;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;

/**
 * The suggestions of the geocoder as an autocompleter of symfony/ux-autocomplete.
 *
 * Each result has the label as "text" and "latitude,longitude|label" as "value", so that a form
 * gets the chosen place without a second lookup. The query string may narrow the search:
 * "?locale=sk" for the labels in Slovak, "?country=SK,CZ" (or "country[]=SK&country[]=CZ") for
 * these countries only. There is a single page of results.
 *
 * Access control and rate limiting are the application's: protect the route "ux_autocomplete"
 * in the security configuration.
 */
final readonly class GeocoderAutocompleter implements AutocompleterInterface
{
    /**
     * @param SearchOptions $defaults the configured defaults (limit, preferred country)
     */
    public function __construct(
        private GeocoderInterface $geocoder,
        private RequestStack $requestStack,
        private SearchOptions $defaults,
    ) {
    }

    /**
     * @throws BadRequestHttpException      when the locale or a country of the request is invalid
     * @throws GeocoderUnavailableException when Meilisearch or the database cannot answer
     */
    public function fetchResults(string $query, int $page): AutocompleteResults
    {
        if ($page > 1) {
            return new AutocompleteResults([], false);
        }

        try {
            $suggestions = $this->geocoder->suggest($query, $this->options());
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        return new AutocompleteResults(array_map(static fn (Suggestion $suggestion): array => [
            'value' => self::value($suggestion),
            'text' => $suggestion->label,
        ], $suggestions), false);
    }

    /**
     * Everyone may search: restrict the route in the security configuration when needed.
     */
    // @phpstan-ignore class.notFound (symfony/security-bundle is optional, the method is only called when it is installed)
    public function isGranted(Security $security): bool
    {
        return true;
    }

    /**
     * "48.1485965,17.1077477|Bratislava, Slovensko".
     */
    private static function value(Suggestion $suggestion): string
    {
        return self::degrees($suggestion->coordinates->latitude).','.self::degrees($suggestion->coordinates->longitude).'|'.$suggestion->label;
    }

    /**
     * @throws InvalidArgumentException when the locale or a country is invalid
     */
    private function options(): SearchOptions
    {
        $options = $this->defaults;
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return $options;
        }

        $locale = $request->query->get('locale');
        if (\is_string($locale) && '' !== $locale) {
            $options = $options->withLocale($locale);
        }

        $countries = $request->query->all()['country'] ?? null;
        if (\is_string($countries)) {
            $countries = explode(',', $countries);
        }
        if (\is_array($countries)) {
            $codes = [];
            foreach ($countries as $country) {
                if (!\is_string($country)) {
                    throw new InvalidArgumentException('The countries must be ISO 3166-1 alpha-2 codes.');
                }
                if ('' !== $country = strtoupper(trim($country))) {
                    $codes[$country] = $country;
                }
            }
            $options = $options->withCountries(array_values($codes));
        }

        return $options;
    }

    /**
     * Degrees with 7 decimals at most (about a centimetre), without trailing zeros.
     */
    private static function degrees(float $degrees): string
    {
        return rtrim(rtrim(\sprintf('%.7F', $degrees), '0'), '.');
    }
}
