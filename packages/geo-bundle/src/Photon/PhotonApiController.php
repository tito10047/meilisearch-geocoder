<?php

namespace Tito10047\GeoBundle\Photon;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Photon\PhotonQuery;
use Tito10047\Geocoder\Photon\PhotonSearchInterface;

/**
 * "GET /api?q=…": the search of Photon (tito10047/geocoder PhotonQuery and PhotonSearch).
 *
 * An invalid parameter is answered "400 {"message": …}" like Photon, an unavailable geocoder
 * "503 {"message": …}". The OpenStreetMap attribution goes in a "Link: <…>; rel="license""
 * header, the body keeps the shape of Photon. No CORS headers: the application adds them (e.g.
 * nelmio/cors-bundle) for the origins it serves; access control and rate limiting are the
 * application's too.
 */
final readonly class PhotonApiController
{
    // ASCII only: header values are not UTF-8
    public const LICENSE_LINK = '<https://www.openstreetmap.org/copyright>; rel="license"; title="OpenStreetMap contributors, ODbL"';

    private const JSON_OPTIONS = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION;

    /**
     * @param int $maxAge seconds the answers may be cached publicly; 0 for no caching headers
     */
    public function __construct(
        private PhotonSearchInterface $search,
        private int $maxAge = 0,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            // the raw query string: the filters repeat ("layer=city&layer=locality"), which the parsed query of PHP loses
            $queryString = $request->server->get('QUERY_STRING');
            $query = PhotonQuery::fromQueryString(\is_string($queryString) ? $queryString : (string) $request->getQueryString(), $request->getPreferredLanguage());
            $features = $this->search->search($query);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (GeocoderUnavailableException) {
            // the message may name the hosts of Meilisearch or of the database: not for the public
            return $this->error('The geocoder is unavailable, try again later.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $response = $this->json($features, Response::HTTP_OK);
        // without "lang" the language comes from the Accept-Language header
        $response->setVary('Accept-Language');
        if ($this->maxAge > 0) {
            $response->setPublic();
            $response->setMaxAge($this->maxAge);
        }

        return $response;
    }

    private function error(string $message, int $status): JsonResponse
    {
        $response = $this->json(['message' => $message], $status);
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    private function json(mixed $data, int $status): JsonResponse
    {
        $response = new JsonResponse(null, $status, ['Link' => self::LICENSE_LINK]);
        $response->setEncodingOptions(self::JSON_OPTIONS);

        return $response->setData($data);
    }
}
