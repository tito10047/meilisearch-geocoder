<?php

namespace Tito10047\Geocoder\Import;

/**
 * What one Photon dump contained.
 */
final readonly class DumpSummary
{
    /**
     * @param string             $dump          the name of the dump (see nameOf()): its file name, not its path
     * @param \DateTimeImmutable $dataTimestamp the "data_timestamp" of its header: the age of the OpenStreetMap data
     * @param list<string>       $countryCodes  ISO 3166-1 alpha-2, upper case: the countries of its documents
     */
    public function __construct(
        public string $dump,
        public \DateTimeImmutable $dataTimestamp,
        public array $countryCodes,
        public ImportCounts $counts,
    ) {
    }

    /**
     * The portable name of a dump given to a converter: the file name of a path or a URL
     * ("slovakia.jsonl.zst" of "/srv/geo/slovakia.jsonl.zst"), a "php://" stream as it is.
     *
     * The directories of the machine that converted a dump mean nothing where its build is
     * published, and should not be disclosed there.
     */
    public static function nameOf(string $dump): string
    {
        if (str_starts_with(strtolower($dump), 'php://')) {
            return $dump;
        }
        $path = $dump;
        if (1 === preg_match('#^[a-z][a-z0-9+.-]*://([^?\#]*)#i', $dump, $url)) {
            // a URL: its path, without the query (it may hold a token)
            $path = $url[1];
        }
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $slash = strrpos($path, '/');
        $name = false === $slash ? $path : substr($path, $slash + 1);

        return '' === $name ? $dump : $name;
    }
}
