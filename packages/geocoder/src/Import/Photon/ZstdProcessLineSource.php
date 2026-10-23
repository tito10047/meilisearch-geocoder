<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\IoException;

/**
 * Reads a zstd-compressed file through the "zstd -dc" command, for PHP without ext-zstd.
 */
final readonly class ZstdProcessLineSource implements LineSourceInterface
{
    /** The exit code of a shell for a command that cannot be executed. */
    private const EXIT_NOT_FOUND = 127;

    /**
     * @param string $binary the zstd command, a name looked up in the PATH or a path
     */
    public function __construct(
        private string $binary = 'zstd',
    ) {
        if ('' === $binary) {
            throw new InvalidArgumentException('The zstd command must not be empty.');
        }
    }

    public function lines(string $dump): iterable
    {
        if (!is_file($dump) || !is_readable($dump)) {
            throw new InvalidArgumentException(\sprintf('The dump "%s" is not a readable file.', $dump));
        }

        // stderr goes to a file: a pipe nobody reads could fill up and block zstd
        $stderr = tmpfile();
        if (false === $stderr) {
            throw new IoException('Cannot create a temporary file for the errors of zstd.');
        }

        $error = 'unknown error';
        set_error_handler(static function (int $type, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });
        try {
            $process = proc_open([$this->binary, '-d', '-c', '-q', '--', $dump], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => $stderr], $pipes);
        } finally {
            restore_error_handler();
        }
        if (false === $process) {
            fclose($stderr);
            throw new IoException(\sprintf('Cannot run "%s" to decompress the dump "%s": %s', $this->binary, $dump, $error));
        }
        fclose($pipes[0]);

        return $this->read($dump, $process, $pipes[1], $stderr);
    }

    /**
     * @param resource $process
     * @param resource $stdout
     * @param resource $stderr
     *
     * @return \Generator<int, string>
     */
    private function read(string $dump, $process, $stdout, $stderr): \Generator
    {
        $finished = false;
        try {
            $number = 0;
            while (false !== ($line = fgets($stdout))) {
                ++$number;
                $line = rtrim($line, "\r\n");
                if ('' !== $line) {
                    yield $number => $line;
                }
            }
            fclose($stdout);
            $finished = true;
            $exitCode = proc_close($process);
            if (0 !== $exitCode) {
                rewind($stderr);
                $message = trim((string) stream_get_contents($stderr));
                if ('' === $message) {
                    $message = self::EXIT_NOT_FOUND === $exitCode ? 'is zstd installed?' : 'no error message';
                }

                throw new IoException(\sprintf('Decompressing the dump "%s" with "%s" failed after line %d (exit code %d): %s', $dump, $this->binary, $number, $exitCode, $message));
            }
        } finally {
            if (!$finished) {
                // the iteration was abandoned: stop zstd instead of waiting for it to decompress everything
                fclose($stdout);
                proc_terminate($process);
                proc_close($process);
            }
            fclose($stderr);
        }
    }
}
