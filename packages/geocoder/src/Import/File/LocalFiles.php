<?php

namespace Tito10047\Geocoder\Import\File;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\IoException;

/**
 * The file operations of the exports and artifacts, failing with an IoException that tells why
 * instead of a warning and false.
 *
 * @internal
 */
final class LocalFiles
{
    /**
     * Creates the directory, or checks that it is empty.
     *
     * @return bool whether the directory was created
     *
     * @throws InvalidArgumentException when the path is empty, not a directory or a directory that is not empty
     * @throws IoException              when the directory cannot be created or read
     */
    public static function prepareEmptyDirectory(string $directory): bool
    {
        if ('' === $directory) {
            throw new InvalidArgumentException('The directory must not be empty.');
        }
        if (!file_exists($directory)) {
            self::call(static fn (): bool => mkdir($directory, 0o777, true), \sprintf('Cannot create the directory "%s"', $directory));

            return true;
        }
        if (!is_dir($directory)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a directory.', $directory));
        }
        $entries = self::call(static fn (): array|false => scandir($directory), \sprintf('Cannot read the directory "%s"', $directory));
        if ([] !== array_diff($entries, ['.', '..'])) {
            throw new InvalidArgumentException(\sprintf('The directory "%s" is not empty.', $directory));
        }

        return false;
    }

    /**
     * Removes the files that exist, and the directory when asked; failures are ignored (this is
     * the cleanup after another failure).
     *
     * @param list<string> $files
     */
    public static function removeQuietly(array $files, ?string $directory = null): void
    {
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        if (null !== $directory && is_dir($directory)) {
            @rmdir($directory);
        }
    }

    /**
     * Copies a file, returning the SHA-256 of what was copied (hex).
     *
     * @throws IoException when the file cannot be read or written
     */
    public static function copy(string $from, string $to): string
    {
        $source = self::call(static fn () => fopen($from, 'r'), \sprintf('Cannot open the file "%s"', $from));
        try {
            $target = self::call(static fn () => fopen($to, 'x'), \sprintf('Cannot create the file "%s"', $to));
            try {
                $hash = hash_init('sha256');
                while (!feof($source)) {
                    $chunk = self::call(static fn (): string|false => fread($source, 1 << 20), \sprintf('Cannot read the file "%s"', $from));
                    hash_update($hash, $chunk);
                    self::call(static fn (): int|false => fwrite($target, $chunk), \sprintf('Cannot write the file "%s"', $to));
                }

                return hash_final($hash);
            } finally {
                fclose($target);
            }
        } finally {
            fclose($source);
        }
    }

    /**
     * @throws IoException when the file cannot be read
     */
    public static function sha256(string $file): string
    {
        return self::call(static fn (): string|false => hash_file('sha256', $file), \sprintf('Cannot read the file "%s"', $file));
    }

    /**
     * @throws IoException when the file cannot be read
     */
    public static function size(string $file): int
    {
        return self::call(static fn (): int|false => filesize($file), \sprintf('Cannot read the size of the file "%s"', $file));
    }

    /**
     * @throws IoException when the file cannot be read
     */
    public static function read(string $file): string
    {
        return self::call(static fn (): string|false => file_get_contents($file), \sprintf('Cannot read the file "%s"', $file));
    }

    /**
     * @throws IoException when the file cannot be written
     */
    public static function write(string $file, string $contents): void
    {
        self::call(static fn (): int|false => file_put_contents($file, $contents, \LOCK_EX), \sprintf('Cannot write the file "%s"', $file));
    }

    /**
     * Runs a filesystem function, turning its warning and false into an exception.
     *
     * @template T
     *
     * @param callable(): (T|false) $operation
     *
     * @return T
     *
     * @throws IoException when the operation fails
     */
    public static function call(callable $operation, string $failure): mixed
    {
        $error = 'unknown error';
        set_error_handler(static function (int $type, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });
        try {
            $result = $operation();
        } finally {
            restore_error_handler();
        }
        if (false === $result) {
            throw new IoException(\sprintf('%s: %s', $failure, $error));
        }

        return $result;
    }
}
