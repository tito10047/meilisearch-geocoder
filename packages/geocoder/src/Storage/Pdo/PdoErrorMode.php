<?php

namespace Tito10047\Geocoder\Storage\Pdo;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * The PDO classes of the geocoder rely on PDO throwing its errors (the default since PHP 8):
 * a connection that silences them would lose house numbers without a word.
 *
 * @internal
 */
final class PdoErrorMode
{
    /**
     * @throws InvalidArgumentException when the connection does not throw its errors
     */
    public static function assertExceptions(\PDO $pdo): void
    {
        if (\PDO::ERRMODE_EXCEPTION !== $pdo->getAttribute(\PDO::ATTR_ERRMODE)) {
            throw new InvalidArgumentException('The PDO connection must throw its errors: set PDO::ATTR_ERRMODE to PDO::ERRMODE_EXCEPTION.');
        }
    }
}
