<?php

namespace Tito10047\Geocoder\Document;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\IoException;

/**
 * Writes the documents to a file, one JSON object per line: the format Meilisearch takes as
 * "application/x-ndjson".
 */
final class NdjsonDocumentWriter implements DocumentWriterInterface
{
    /** @var resource|null */
    private $handle;

    /**
     * @param string $path   the file, created or truncated (or appended to)
     * @param bool   $append whether to append to an existing file instead of truncating it
     *
     * @throws InvalidArgumentException when the path is empty
     * @throws IoException              when the file cannot be opened
     */
    public function __construct(
        private readonly string $path,
        bool $append = false,
    ) {
        if ('' === $path) {
            throw new InvalidArgumentException('The path of the NDJSON file must not be empty.');
        }

        $error = 'unknown error';
        set_error_handler(static function (int $type, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });
        try {
            $handle = fopen($path, $append ? 'a' : 'w');
        } finally {
            restore_error_handler();
        }
        if (false === $handle) {
            throw new IoException(\sprintf('Cannot open the NDJSON file "%s": %s', $path, $error));
        }
        $this->handle = $handle;
    }

    public function __destruct()
    {
        if (null !== $this->handle) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function write(Document $document): void
    {
        if (null === $this->handle) {
            throw new IoException(\sprintf('The NDJSON file "%s" is closed.', $this->path));
        }

        try {
            $line = json_encode($document->toArray(), \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
        } catch (\JsonException $exception) {
            throw new IoException(\sprintf('The document "%s" cannot be encoded as JSON: %s', $document->id, $exception->getMessage()), 0, $exception);
        }

        if (\strlen($line) !== fwrite($this->handle, $line)) {
            throw new IoException(\sprintf('Writing to the NDJSON file "%s" failed.', $this->path));
        }
    }

    public function close(): void
    {
        if (null === $this->handle) {
            return;
        }
        $handle = $this->handle;
        $this->handle = null;
        if (!fclose($handle)) {
            throw new IoException(\sprintf('Closing the NDJSON file "%s" failed.', $this->path));
        }
    }
}
