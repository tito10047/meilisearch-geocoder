<?php

namespace Tito10047\GeoBundle\Database;

use Tito10047\Geocoder\Exception\GeocoderUnavailableException;

/**
 * Opens the connection the bundle builds from the configured DSN.
 *
 * The connection is lazy: it is opened by the first query of whichever service uses it, often
 * outside the code of that service catching its PDO errors. A database that cannot be reached
 * is then reported like by the geocoder itself, so that the search falls back and the commands
 * fail with a message instead of a stack trace.
 *
 * The directory of a file-based SQLite database is created first: SQLite creates the file but not
 * its directory, and a DSN under the "var/" of an application points to a directory nothing else
 * creates. A database in memory and an SQLite URI ("sqlite:file:…?mode=…") are left to PDO.
 */
final class PdoConnectionFactory
{
    /**
     * @throws GeocoderUnavailableException when the database cannot be connected to, or the directory of an SQLite
     *                                      database cannot be created
     */
    public static function connect(string $dsn, ?string $user = null, ?string $password = null): \PDO
    {
        self::createSqliteDirectory($dsn);

        try {
            return new \PDO($dsn, $user, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        } catch (\PDOException $exception) {
            throw new GeocoderUnavailableException(\sprintf('The database of the geocoder cannot be connected to: %s', $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * @throws GeocoderUnavailableException when the directory cannot be created
     */
    private static function createSqliteDirectory(string $dsn): void
    {
        if (!str_starts_with($dsn, 'sqlite:')) {
            return;
        }
        $path = substr($dsn, \strlen('sqlite:'));
        if ('' === $path || ':memory:' === $path || str_starts_with($path, 'file:')) {
            return;
        }

        $directory = \dirname($path);
        if (is_dir($directory)) {
            return;
        }
        // another process (a parallel worker) may create it meanwhile: only a directory still missing is a failure
        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new GeocoderUnavailableException(\sprintf('The directory "%s" of the SQLite database of the geocoder cannot be created: %s', $directory, error_get_last()['message'] ?? 'unknown error'));
        }
    }
}
