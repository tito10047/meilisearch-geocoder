<?php

namespace Tito10047\Geocoder\Import\Download;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Tito10047\Geocoder\Exception\ExceptionInterface;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\IoException;
use Tito10047\Geocoder\Import\Download\Exception\DownloadException;
use Tito10047\Geocoder\Import\File\LocalFiles;
use Tito10047\Geocoder\Import\Photon\DumpHeader;
use Tito10047\Geocoder\Import\Photon\DumpReaderInterface;
use Tito10047\Geocoder\Import\Photon\PhotonDumpReader;

/**
 * Downloads the dumps over any PSR-18 client, streamed to a file beside the dump and renamed over
 * it once complete and checked: a failed or interrupted download leaves the previous copy as it was.
 *
 * The ETag and Last-Modified of each download are kept in a small file beside the dump
 * ("slovakia.jsonl.zst.download.json"); the next download asks the server for the dump only if
 * it changed (If-None-Match, If-Modified-Since) and does nothing on "304 Not Modified".
 */
final readonly class HttpDumpDownloader implements DumpDownloaderInterface
{
    public const STATE_SUFFIX = '.download.json';
    private const PART_PREFIX = '.download-';

    /**
     * @param int $chunkSize        the bytes read from the response at once
     * @param int $progressInterval the progress is reported every that many bytes
     *
     * @throws InvalidArgumentException when a size is not positive
     */
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requests,
        private RegionUrlResolverInterface $resolver = new GraphHopperRegionUrlResolver(),
        private DumpReaderInterface $reader = new PhotonDumpReader(),
        private int $chunkSize = 1 << 20,
        private int $progressInterval = 8 << 20,
    ) {
        if ($chunkSize < 1 || $progressInterval < 1) {
            throw new InvalidArgumentException('The chunk size and the progress interval must be positive.');
        }
    }

    public function download(string $region, string $directory, bool $force = false, ?DownloadProgressInterface $progress = null): DownloadResult
    {
        $url = $this->resolver->url($region);
        $fileName = $this->resolver->fileName($region);
        if ('' === $directory) {
            throw new InvalidArgumentException('The directory of the dumps must not be empty.');
        }
        if (file_exists($directory) && !is_dir($directory)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a directory.', $directory));
        }
        $directory = rtrim($directory, '/');
        $path = $directory.'/'.$fileName;
        $statePath = $path.self::STATE_SUFFIX;
        // the name ends like the dump's, so that it is read (decompressed) the same way
        $partPath = $directory.'/'.self::PART_PREFIX.$fileName;

        try {
            if (!is_dir($directory)) {
                LocalFiles::call(static fn (): bool => mkdir($directory, 0o777, true) || is_dir($directory), \sprintf('Cannot create the directory "%s"', $directory));
            }
            $state = $force || !is_file($path) ? null : DownloadState::read($statePath);
            if (null !== $state && $state->url !== $url) {
                $state = null;
            }

            $response = $this->send($url, $state);
            $status = $response->getStatusCode();
            if (304 === $status && null !== $state) {
                return new DownloadResult($region, $url, $path, false, LocalFiles::size($path), $state->lastModified());
            }
            if (200 !== $status) {
                throw new DownloadException(\sprintf('The server answered %d %s to the download of %s.', $status, $response->getReasonPhrase(), $url));
            }

            $bytes = $this->save($region, $response, $partPath, $progress);
            $this->assertDump($partPath, $url);
            LocalFiles::call(static fn (): bool => rename($partPath, $path), \sprintf('Cannot move the dump to "%s"', $path));

            $state = DownloadState::fromResponse($url, $response, $bytes);
            try {
                $state->write($statePath);
            } catch (IoException) {
                // without the state the next download is not conditional: slower, still right
            }

            return new DownloadResult($region, $url, $path, true, $bytes, $state->lastModified());
        } catch (\Throwable $exception) {
            LocalFiles::removeQuietly([$partPath]);
            if ($exception instanceof DownloadException || $exception instanceof InvalidArgumentException || !$exception instanceof \Exception) {
                throw $exception;
            }

            throw new DownloadException(\sprintf('The download of %s into "%s" failed: %s', $url, $path, $exception->getMessage()), 0, $exception);
        }
    }

    private function send(string $url, ?DownloadState $state): ResponseInterface
    {
        $request = $this->requests->createRequest('GET', $url);
        if (null !== $state?->etag) {
            $request = $request->withHeader('If-None-Match', $state->etag);
        }
        if (null !== $state?->lastModified) {
            $request = $request->withHeader('If-Modified-Since', $state->lastModified);
        }

        try {
            return $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new DownloadException(\sprintf('The download of %s failed: %s', $url, $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * Streams the body into the file.
     *
     * @return int the bytes written
     */
    private function save(string $region, ResponseInterface $response, string $partPath, ?DownloadProgressInterface $progress): int
    {
        $length = $response->getHeaderLine('Content-Length');
        $total = 1 === preg_match('/^\d+$/D', $length) ? (int) $length : null;
        $body = $response->getBody();
        $handle = LocalFiles::call(static fn () => fopen($partPath, 'w'), \sprintf('Cannot create the file "%s"', $partPath));
        $bytes = 0;
        $reported = 0;
        try {
            $progress?->advance($region, 0, $total);
            while (!$body->eof()) {
                $chunk = $body->read($this->chunkSize);
                if ('' === $chunk) {
                    continue;
                }
                LocalFiles::call(static fn (): int|false => fwrite($handle, $chunk), \sprintf('Cannot write the file "%s"', $partPath));
                $bytes += \strlen($chunk);
                if ($bytes - $reported >= $this->progressInterval) {
                    $reported = $bytes;
                    $progress?->advance($region, $bytes, $total);
                }
            }
        } finally {
            fclose($handle);
        }
        if (null !== $total && $bytes !== $total) {
            throw new DownloadException(\sprintf('The download of %s broke off after %d of %d bytes.', $region, $bytes, $total));
        }
        $progress?->advance($region, $bytes, $total ?? $bytes);

        return $bytes;
    }

    /**
     * Reads the header of the downloaded file: an error page or a truncated download is no dump.
     */
    private function assertDump(string $partPath, string $url): void
    {
        try {
            foreach ($this->reader->read($partPath) as $record) {
                if ($record instanceof DumpHeader) {
                    return;
                }

                break;
            }
        } catch (ExceptionInterface $exception) {
            throw new DownloadException(\sprintf('What %s sent is not a Photon dump: %s', $url, $exception->getMessage()), 0, $exception);
        }

        throw new DownloadException(\sprintf('What %s sent is not a Photon dump: it has no header.', $url));
    }
}
