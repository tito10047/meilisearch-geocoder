<?php

namespace Tito10047\Geocoder\Storage\Pdo;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;

/**
 * Inserts rows in multi-row INSERT statements ("VALUES (…), (…), …"), grouped in transactions.
 *
 * A transaction the connection is already in belongs to the caller: the inserter neither commits
 * nor rolls it back.
 *
 * @internal
 */
final class PdoBatchInserter
{
    /** @var list<int|string|null> */
    private array $values = [];
    private int $rows = 0;
    private int $rowsInTransaction = 0;
    private bool $ownsTransaction = false;
    private ?\PDOStatement $batchStatement = null;

    /**
     * @param string $insert          "INSERT INTO t (a, b) VALUES " with identifiers that are safe
     * @param int    $columns         the values of a row
     * @param int    $batchSize       the rows of one statement
     * @param int    $transactionSize the rows of one transaction
     *
     * @throws InvalidArgumentException when a number is not positive
     */
    public function __construct(
        private readonly \PDO $pdo,
        private readonly string $insert,
        private readonly int $columns,
        private readonly int $batchSize,
        private readonly int $transactionSize,
    ) {
        if ($columns < 1 || $batchSize < 1 || $transactionSize < 1) {
            throw new InvalidArgumentException('The number of columns, the batch size and the transaction size must be positive.');
        }
        PdoErrorMode::assertExceptions($pdo);
    }

    /**
     * @param list<int|float|string|null> $row
     *
     * @throws StorageException when the insert fails
     */
    public function add(array $row): void
    {
        foreach ($row as $value) {
            // a float cast to a string keeps only 14 digits ("precision"): keep the shortest exact form
            $this->values[] = \is_float($value) ? json_encode($value, \JSON_THROW_ON_ERROR) : $value;
        }
        if (++$this->rows >= $this->batchSize) {
            $this->flush();
        }
    }

    /**
     * Inserts the remaining rows and commits.
     *
     * @throws StorageException when the insert fails
     */
    public function finish(): void
    {
        $this->flush();
        if ($this->ownsTransaction) {
            $this->ownsTransaction = false;
            try {
                $this->pdo->commit();
            } catch (\PDOException $exception) {
                throw new StorageException(\sprintf('Committing the rows failed: %s', $exception->getMessage()), 0, $exception);
            }
        }
    }

    /**
     * Inserts the buffered rows now.
     *
     * @throws StorageException when the insert fails
     */
    public function flush(): void
    {
        if (0 === $this->rows) {
            return;
        }

        try {
            if (!$this->ownsTransaction && !$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $this->ownsTransaction = true;
            }

            $statement = $this->rows === $this->batchSize
                ? $this->batchStatement ??= $this->pdo->prepare($this->sql($this->batchSize))
                : $this->pdo->prepare($this->sql($this->rows));
            foreach ($this->values as $index => $value) {
                $statement->bindValue($index + 1, $value, match (true) {
                    null === $value => \PDO::PARAM_NULL,
                    \is_int($value) => \PDO::PARAM_INT,
                    default => \PDO::PARAM_STR,
                });
            }
            $statement->execute();

            $this->rowsInTransaction += $this->rows;
            $this->values = [];
            $this->rows = 0;

            if ($this->ownsTransaction && $this->rowsInTransaction >= $this->transactionSize) {
                $this->pdo->commit();
                $this->ownsTransaction = false;
                $this->rowsInTransaction = 0;
            }
        } catch (\PDOException $exception) {
            $this->values = [];
            $this->rows = 0;
            if ($this->ownsTransaction) {
                $this->ownsTransaction = false;
                try {
                    $this->pdo->rollBack();
                } catch (\PDOException) {
                    // the original failure matters more
                }
            }

            throw new StorageException(\sprintf('Inserting the rows failed: %s', $exception->getMessage()), 0, $exception);
        }
    }

    private function sql(int $rows): string
    {
        $row = '('.implode(', ', array_fill(0, $this->columns, '?')).')';

        return $this->insert.implode(', ', array_fill(0, $rows, $row));
    }
}
