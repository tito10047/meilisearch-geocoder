<?php

namespace Tito10047\Geocoder\Storage\Pdo;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\HouseNumber\HouseNumberWriterInterface;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * Writes the house numbers into the table created by PdoSchema, in multi-row inserts grouped in
 * transactions.
 *
 * When the connection is already in a transaction, the writer leaves it to the caller.
 */
final class PdoHouseNumberWriter implements HouseNumberWriterInterface
{
    private const COLUMNS = 8;

    private readonly PdoBatchInserter $inserter;
    private bool $closed = false;

    /**
     * @param int $batchSize       the rows of one INSERT
     * @param int $transactionSize the rows of one transaction
     *
     * @throws InvalidArgumentException when a size is not positive
     */
    public function __construct(
        \PDO $pdo,
        TableNames $tables,
        int $batchSize = 500,
        int $transactionSize = 50_000,
    ) {
        $this->inserter = new PdoBatchInserter(
            $pdo,
            \sprintf('INSERT INTO %s (street_key, number, orientation, postcode, lat, lon, suburb, reference) VALUES ', $tables->houseNumbers),
            self::COLUMNS,
            $batchSize,
            $transactionSize,
        );
    }

    public function write(HouseNumber $houseNumber): void
    {
        if ($this->closed) {
            throw new StorageException('The house number writer is closed.');
        }

        $this->inserter->add([
            $houseNumber->streetKey->value,
            $houseNumber->number,
            $houseNumber->orientation,
            $houseNumber->postcode,
            $houseNumber->coordinates->latitude,
            $houseNumber->coordinates->longitude,
            $houseNumber->suburb,
            $houseNumber->reference,
        ]);
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->inserter->finish();
    }
}
