<?php

namespace Tito10047\GeoBundle\Http;

use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tito10047\GeoBundle\Exception\LogicException;

/**
 * Builds the default PSR-18 clients of the bundle over Symfony HttpClient, with the options
 * that avoid the two problems met with Meilisearch and Valhalla.
 */
final class HttpClientFactory
{
    /**
     * The client of Meilisearch.
     *
     * Meilisearch (actix-web) gzips its answers when the client accepts it, and a compressed answer
     * of a few kilobytes (2-8 kB uncompressed: 3 to 10 suggestions) arrives about 40 ms late, a TCP
     * delayed-ACK stall, against 1 ms uncompressed. curl accepts gzip as soon as Symfony asks for
     * it, so the compression is refused by default ("Accept-Encoding: identity").
     *
     * @param float $timeout     seconds without data after which a request fails
     * @param bool  $compression whether to accept gzip answers
     */
    public static function meilisearch(float $timeout, bool $compression = false): ClientInterface
    {
        $options = ['timeout' => $timeout];
        if (!$compression) {
            $options['headers'] = ['Accept-Encoding' => 'identity'];
        }

        return new Psr18Client(HttpClient::create($options));
    }

    /**
     * The client of the downloads of the dumps: hundreds of megabytes, so no limit on the whole
     * request, only on the silence of the server.
     *
     * @param float $timeout seconds without data after which a download fails
     */
    public static function download(float $timeout = 60.0): ClientInterface
    {
        return new Psr18Client(HttpClient::create(['timeout' => $timeout, 'max_duration' => 0]));
    }

    /**
     * The client of Valhalla, over curl or amphp.
     *
     * Valhalla keeps the connection open after an answer: a client reading the body until the
     * socket closes (a stream wrapper, e.g. file_get_contents()) hangs until it times out. curl
     * and amphp read the body by its length and reuse the connection.
     *
     * @param float $timeout seconds the whole request may take, connecting included
     *
     * @throws LogicException when neither ext-curl nor amphp/http-client is installed
     */
    public static function valhalla(float $timeout): ClientInterface
    {
        return new Psr18Client(self::persistentClient(['timeout' => $timeout, 'max_duration' => $timeout]));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws LogicException when neither ext-curl nor amphp/http-client is installed
     */
    private static function persistentClient(array $options): HttpClientInterface
    {
        if (\extension_loaded('curl')) {
            return new CurlHttpClient($options);
        }
        // AmpHttpClient throws on loading when amphp is missing: check before touching it
        if (class_exists('Amp\Http\Client\Request')) {
            return new AmpHttpClient($options);
        }

        throw new LogicException('The Valhalla client needs ext-curl or amphp/http-client ("composer require amphp/http-client:^5"): Valhalla keeps connections open and a client based on PHP streams would hang until its timeout. Or configure "geo.routing.http_client".');
    }
}
