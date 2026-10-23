<?php

namespace Tito10047\Geocoder\Storage\Pdo;

use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\Translation;
use Tito10047\Geocoder\Storage\Pdo\Dialect\SqlDialectInterface;
use Tito10047\Geocoder\Storage\Pdo\Dialect\SqlDialects;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\StreetKey;

/**
 * Reads the rows of the tables of a build back, streamed: tens of millions of house numbers never
 * sit in the memory at once.
 */
final readonly class PdoTableReader
{
    private SqlDialectInterface $dialect;

    /**
     * @throws InvalidArgumentException when the connection does not throw its errors or its driver is not supported
     */
    public function __construct(
        private \PDO $pdo,
        ?SqlDialectInterface $dialect = null,
    ) {
        PdoErrorMode::assertExceptions($pdo);
        $this->dialect = $dialect ?? SqlDialects::forConnection($pdo);
    }

    /**
     * @return \Generator<int, HouseNumber>
     *
     * @throws StorageException when the rows cannot be read or are invalid
     */
    public function houseNumbers(TableNames $tables): \Generator
    {
        $sql = \sprintf('SELECT street_key, number, postcode, lat, lon, suburb, reference FROM %s', $tables->houseNumbers);
        foreach ($this->rows($sql) as $row) {
            try {
                yield new HouseNumber(
                    new StreetKey((int) $row[0]),
                    (string) $row[1],
                    null === $row[2] ? null : (string) $row[2],
                    new Coordinates((float) $row[3], (float) $row[4]),
                    null === $row[5] ? null : (string) $row[5],
                    null === $row[6] ? null : (string) $row[6],
                );
            } catch (InvalidArgumentException $exception) {
                throw new StorageException(\sprintf('A row of %s is not a house number: %s', $tables->houseNumbers, $exception->getMessage()), 0, $exception);
            }
        }
    }

    /**
     * @return \Generator<int, Translation>
     *
     * @throws StorageException when the rows cannot be read or are invalid
     */
    public function translations(TableNames $tables): \Generator
    {
        $sql = \sprintf('SELECT cc, kind, name, lang, translation FROM %s', $tables->translations);
        foreach ($this->rows($sql) as $row) {
            try {
                $kind = NameKind::tryFrom((string) $row[1]) ?? throw new InvalidArgumentException(\sprintf('unknown kind "%s"', (string) $row[1]));
                // CHAR(2) comes back padded from some databases
                yield new Translation(trim((string) $row[0]), $kind, (string) $row[2], (string) $row[3], (string) $row[4]);
            } catch (InvalidArgumentException $exception) {
                throw new StorageException(\sprintf('A row of %s is not a translation: %s', $tables->translations, $exception->getMessage()), 0, $exception);
            }
        }
    }

    /**
     * @return \Generator<int, list<scalar|null>>
     */
    private function rows(string $sql): \Generator
    {
        try {
            $statement = $this->pdo->prepare($sql, $this->dialect->streamingOptions());
            $statement->execute();
            while (false !== ($row = $statement->fetch(\PDO::FETCH_NUM))) {
                /* @var list<scalar|null> $row */
                yield $row;
            }
            $statement->closeCursor();
        } catch (\PDOException $exception) {
            throw new StorageException(\sprintf('Reading the rows of "%s" failed: %s', $sql, $exception->getMessage()), 0, $exception);
        }
    }
}
