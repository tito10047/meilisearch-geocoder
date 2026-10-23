<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\IoException;

/**
 * Reads a file or any stream PHP can open: "php://stdin", "compress.zstd://dump.jsonl.zst" (ext-zstd),
 * "compress.zlib://dump.jsonl.gz", ….
 */
final class StreamLineSource implements LineSourceInterface
{
    public function lines(string $dump): iterable
    {
        if (is_dir($dump)) {
            throw new InvalidArgumentException(\sprintf('The dump "%s" is a directory.', $dump));
        }

        $error = 'unknown error';
        set_error_handler(static function (int $type, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });
        try {
            $handle = fopen($dump, 'r');
        } finally {
            restore_error_handler();
        }
        if (false === $handle) {
            throw new InvalidArgumentException(\sprintf('The dump "%s" cannot be opened: %s', $dump, $error));
        }

        return $this->read($dump, $handle);
    }

    /**
     * @param resource $handle
     *
     * @return \Generator<int, string>
     */
    private function read(string $dump, $handle): \Generator
    {
        try {
            $number = 0;
            while (false !== ($line = fgets($handle))) {
                ++$number;
                $line = rtrim($line, "\r\n");
                if ('' !== $line) {
                    yield $number => $line;
                }
            }
            if (!feof($handle)) {
                throw new IoException(\sprintf('Reading the dump "%s" failed after line %d.', $dump, $number));
            }
        } finally {
            fclose($handle);
        }
    }
}
