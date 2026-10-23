<?php

namespace Tito10047\Geocoder\Import\Download;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * The layout of the Photon dumps GraphHopper publishes (and of the mirrors copying it):
 * "europe/slovakia" is "<base>/europe/slovakia/photon-dump-slovakia-1.0-latest.jsonl.zst".
 *
 * The file is saved under the last part of the region ("slovakia.jsonl.zst"): the import command
 * looks for "*.jsonl.zst" files.
 *
 * The regions are those of the server, not always those of Geofabrik: Switzerland is one dump with
 * Liechtenstein, "europe/switzerland-liechtenstein".
 */
final readonly class GraphHopperRegionUrlResolver implements RegionUrlResolverInterface
{
    public const DEFAULT_BASE_URL = 'https://download1.graphhopper.com/public/';
    public const DEFAULT_VERSION = '1.0';

    /** Lower-case parts of letters, digits, "-" and "_", separated by "/": no "..", no query. */
    private const REGION = '#^[a-z0-9]+(?:[-_][a-z0-9]+)*(?:/[a-z0-9]+(?:[-_][a-z0-9]+)*)*$#D';

    private string $baseUrl;

    /**
     * @param string $baseUrl the directory of the regions, http(s)
     * @param string $version the version of the dump format in the file names
     *
     * @throws InvalidArgumentException when the base URL is not an http(s) URL or the version is not like "1.0"
     */
    public function __construct(
        string $baseUrl = self::DEFAULT_BASE_URL,
        private string $version = self::DEFAULT_VERSION,
    ) {
        $scheme = parse_url($baseUrl, \PHP_URL_SCHEME);
        if (!\is_string($scheme) || !\in_array(strtolower($scheme), ['http', 'https'], true) || !\is_string(parse_url($baseUrl, \PHP_URL_HOST))
            || null !== parse_url($baseUrl, \PHP_URL_QUERY) || null !== parse_url($baseUrl, \PHP_URL_FRAGMENT)) {
            throw new InvalidArgumentException(\sprintf('The base URL of the dumps must be an http(s) URL without a query, "%s" given.', $baseUrl));
        }
        if (1 !== preg_match('/^[0-9A-Za-z]+(\.[0-9A-Za-z]+)*$/D', $version)) {
            throw new InvalidArgumentException(\sprintf('The version of the dumps must be like "1.0", "%s" given.', $version));
        }
        $this->baseUrl = rtrim($baseUrl, '/').'/';
    }

    public function url(string $region): string
    {
        return \sprintf('%s%s/photon-dump-%s-%s-latest.jsonl.zst', $this->baseUrl, $region, $this->name($region), $this->version);
    }

    public function fileName(string $region): string
    {
        return $this->name($region).'.jsonl.zst';
    }

    /**
     * The last part of the region: "slovakia" of "europe/slovakia".
     */
    private function name(string $region): string
    {
        if (1 !== preg_match(self::REGION, $region)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a region of the dumps: use the path of the download server in lower case, e.g. "europe/slovakia".', $region));
        }
        $slash = strrpos($region, '/');

        return false === $slash ? $region : substr($region, $slash + 1);
    }
}
