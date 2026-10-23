<?php

namespace Tito10047\Geocoder\Import\Download;

use Psr\Http\Message\ResponseInterface;
use Tito10047\Geocoder\Exception\IoException;
use Tito10047\Geocoder\Import\File\LocalFiles;

/**
 * What the server said about the downloaded dump, kept beside it for the next conditional download.
 *
 * @internal
 */
final readonly class DownloadState
{
    /**
     * @param string|null $etag         the ETag header, verbatim (with its quotes)
     * @param string|null $lastModified the Last-Modified header, verbatim (an HTTP date)
     */
    public function __construct(
        public string $url,
        public ?string $etag,
        public ?string $lastModified,
        public int $bytes,
    ) {
    }

    public static function fromResponse(string $url, ResponseInterface $response, int $bytes): self
    {
        $etag = trim($response->getHeaderLine('ETag'));
        $lastModified = trim($response->getHeaderLine('Last-Modified'));

        return new self($url, '' === $etag ? null : $etag, '' === $lastModified ? null : $lastModified, $bytes);
    }

    /**
     * The state in the file, or null when there is none or it cannot be read: the download is
     * then unconditional, which is always right.
     */
    public static function read(string $file): ?self
    {
        if (!is_file($file)) {
            return null;
        }
        try {
            $data = json_decode(LocalFiles::read($file), true, 4, \JSON_THROW_ON_ERROR);
        } catch (IoException|\JsonException) {
            return null;
        }
        if (!\is_array($data) || !\is_string($data['url'] ?? null) || !\is_int($data['bytes'] ?? null)) {
            return null;
        }
        $etag = $data['etag'] ?? null;
        $lastModified = $data['last_modified'] ?? null;

        return new self($data['url'], \is_string($etag) ? $etag : null, \is_string($lastModified) ? $lastModified : null, $data['bytes']);
    }

    /**
     * @throws IoException when the file cannot be written
     */
    public function write(string $file): void
    {
        LocalFiles::write($file, json_encode([
            'url' => $this->url,
            'etag' => $this->etag,
            'last_modified' => $this->lastModified,
            'bytes' => $this->bytes,
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * The Last-Modified date, or null when there is none or it is not a date.
     */
    public function lastModified(): ?\DateTimeImmutable
    {
        if (null === $this->lastModified) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!D, d M Y H:i:s \\G\\M\\T', $this->lastModified, new \DateTimeZone('UTC'));

        return false === $date ? null : $date;
    }
}
