<?php

namespace Tito10047\Geocoder\Storage\Pdo;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Name\Translation;
use Tito10047\Geocoder\Name\TranslationWriterInterface;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * Writes the translations into the table created by PdoSchema, each once.
 *
 * The translations are buffered; before a batch is inserted, the rows already in the table are
 * looked up and left out. The memory stays that of one batch however many translations come,
 * and rows written by an earlier import into the same table are respected. (A case- or
 * accent-insensitive collation of the table, as MariaDB's default, would make two spellings of a
 * name one row: PdoSchema gives the tables a binary collation there.)
 */
final class PdoTranslationWriter implements TranslationWriterInterface
{
    private const COLUMNS = 5;

    private readonly PdoBatchInserter $inserter;
    /** @var array<string, Translation> */
    private array $buffer = [];
    private bool $closed = false;

    /**
     * @param int $batchSize the translations looked up and inserted at once
     *
     * @throws InvalidArgumentException when the batch size is not positive or the connection does not throw its errors
     */
    public function __construct(
        private readonly \PDO $pdo,
        private readonly TableNames $tables,
        private readonly int $batchSize = 200,
    ) {
        if ($batchSize < 1) {
            throw new InvalidArgumentException('The batch size must be positive.');
        }
        $this->inserter = new PdoBatchInserter(
            $pdo,
            \sprintf('INSERT INTO %s (cc, kind, name, lang, translation) VALUES ', $tables->translations),
            self::COLUMNS,
            $batchSize,
            \PHP_INT_MAX,
        );
    }

    public function write(Translation $translation): void
    {
        if ($this->closed) {
            throw new StorageException('The translation writer is closed.');
        }

        $this->buffer[$this->key($translation->countryCode, $translation->kind->value, $translation->name, $translation->language)] ??= $translation;
        if (\count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->flush();
        $this->inserter->finish();
    }

    private function flush(): void
    {
        if ([] === $this->buffer) {
            return;
        }
        $translations = $this->buffer;
        $this->buffer = [];

        foreach ($this->existingKeys($translations) as $key) {
            unset($translations[$key]);
        }
        foreach ($translations as $translation) {
            $this->inserter->add([$translation->countryCode, $translation->kind->value, $translation->name, $translation->language, $translation->translation]);
        }
        // the next lookup must see these rows
        $this->inserter->flush();
    }

    /**
     * @param array<string, Translation> $translations
     *
     * @return list<string>
     */
    private function existingKeys(array $translations): array
    {
        $conditions = [];
        $parameters = [];
        foreach ($translations as $translation) {
            $conditions[] = '(cc = ? AND kind = ? AND name = ? AND lang = ?)';
            $parameters[] = $translation->countryCode;
            $parameters[] = $translation->kind->value;
            $parameters[] = $translation->name;
            $parameters[] = $translation->language;
        }

        try {
            $statement = $this->pdo->prepare(\sprintf('SELECT cc, kind, name, lang FROM %s WHERE %s', $this->tables->translations, implode(' OR ', $conditions)));
            $statement->execute($parameters);
            $keys = [];
            while (false !== ($row = $statement->fetch(\PDO::FETCH_NUM))) {
                $keys[] = $this->key(trim($row[0]), $row[1], $row[2], $row[3]);
            }

            return $keys;
        } catch (\PDOException $exception) {
            throw new StorageException(\sprintf('Looking up the translations in "%s" failed: %s', $this->tables->translations, $exception->getMessage()), 0, $exception);
        }
    }

    private function key(string $countryCode, string $kind, string $name, string $language): string
    {
        return $countryCode."\0".$kind."\0".$name."\0".$language;
    }
}
