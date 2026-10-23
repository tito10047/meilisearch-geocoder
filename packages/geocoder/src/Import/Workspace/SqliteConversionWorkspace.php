<?php

namespace Tito10047\Geocoder\Import\Workspace;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\Translation;

/**
 * A workspace in an SQLite database file, deleted when the workspace is closed.
 *
 * The database is a scratch file: no journal, no fsync, one transaction for the whole conversion.
 */
final class SqliteConversionWorkspace implements ConversionWorkspaceInterface
{
    /** Keys seen lately are answered from memory: an address repeats the town of the previous one. */
    private const RECENT_KEYS = 4096;

    /** Votes are summed in memory first: the addresses of a town follow each other. */
    private const VOTE_BUFFER = 8192;

    private ?\PDO $pdo;
    private ?\PDOStatement $insertSeen = null;
    private ?\PDOStatement $insertPlace = null;
    private ?\PDOStatement $upsertStreetOfWay = null;
    private ?\PDOStatement $insertStreetOfAddress = null;
    private ?\PDOStatement $upsertVote = null;
    /** @var array<string, array{Translation, int, int}> */
    private array $votes = [];
    /** @var array<string, true> */
    private array $recentKeys = [];
    /** @var array<string, true> */
    private array $recentStreetsOfAddress = [];
    private int $sequence = 0;

    /**
     * @param string $path a file that does not exist or is empty; it is deleted by close()
     *
     * @throws StorageException when the database cannot be created
     */
    public function __construct(
        private readonly string $path,
    ) {
        if ('' === $path || str_contains($path, "\0")) {
            throw new InvalidArgumentException('The path of the workspace database must not be empty.');
        }

        try {
            $this->pdo = new \PDO('sqlite:'.$path, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $this->pdo->exec('PRAGMA journal_mode = OFF');
            $this->pdo->exec('PRAGMA synchronous = OFF');
            $this->pdo->exec('PRAGMA cache_size = -32768');
            $this->pdo->exec('CREATE TABLE seen (seen_key TEXT NOT NULL PRIMARY KEY) WITHOUT ROWID');
            $this->pdo->exec('CREATE TABLE place (seq INTEGER NOT NULL PRIMARY KEY, grp TEXT NOT NULL, doc TEXT NOT NULL)');
            $this->pdo->exec('CREATE TABLE street (id TEXT NOT NULL PRIMARY KEY, seq INTEGER NOT NULL, names INTEGER NOT NULL, doc TEXT NOT NULL)');
            $this->pdo->exec('CREATE TABLE vote (cc TEXT NOT NULL, kind TEXT NOT NULL, name TEXT NOT NULL, lang TEXT NOT NULL, translation TEXT NOT NULL,
                addresses INTEGER NOT NULL, own_names INTEGER NOT NULL, PRIMARY KEY (cc, kind, name, lang, translation)) WITHOUT ROWID');
            $this->pdo->beginTransaction();
        } catch (\PDOException $exception) {
            $this->pdo = null;
            $this->deleteFile();
            throw new StorageException(\sprintf('Cannot create the workspace database "%s": %s', $path, $exception->getMessage()), 0, $exception);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function firstOccurrence(string $key): bool
    {
        if (isset($this->recentKeys[$key])) {
            return false;
        }
        $this->remember($this->recentKeys, $key);

        try {
            $statement = $this->insertSeen ??= $this->pdo()->prepare('INSERT OR IGNORE INTO seen (seen_key) VALUES (?)');
            $statement->execute([$key]);

            return 1 === $statement->rowCount();
        } catch (\PDOException $exception) {
            throw $this->failure($exception);
        }
    }

    public function addPlace(string $group, Document $place): void
    {
        try {
            $statement = $this->insertPlace ??= $this->pdo()->prepare('INSERT INTO place (seq, grp, doc) VALUES (?, ?, ?)');
            $statement->execute([++$this->sequence, $group, $this->encode($place)]);
        } catch (\PDOException $exception) {
            throw $this->failure($exception);
        }
    }

    public function placeGroups(): iterable
    {
        try {
            $statement = $this->pdo()->prepare('SELECT grp, doc FROM place ORDER BY grp, seq');
            $statement->execute();
        } catch (\PDOException $exception) {
            throw $this->failure($exception);
        }

        return $this->groups($statement);
    }

    public function addStreetOfWay(Document $street): void
    {
        try {
            $statement = $this->upsertStreetOfWay ??= $this->pdo()->prepare(
                'INSERT INTO street (id, seq, names, doc) VALUES (?, ?, ?, ?)
                ON CONFLICT (id) DO UPDATE SET names = excluded.names, doc = excluded.doc WHERE street.names < 2'
            );
            $statement->execute([$street->id, ++$this->sequence, \count($street->names), $this->encode($street)]);
        } catch (\PDOException $exception) {
            throw $this->failure($exception);
        }
    }

    public function addStreetOfAddress(Document $street): void
    {
        // inserting is idempotent: a street inserted (or ignored) lately needs no query
        if (isset($this->recentStreetsOfAddress[$street->id])) {
            return;
        }
        $this->remember($this->recentStreetsOfAddress, $street->id);

        try {
            $statement = $this->insertStreetOfAddress ??= $this->pdo()->prepare(
                'INSERT INTO street (id, seq, names, doc) VALUES (?, ?, ?, ?) ON CONFLICT (id) DO NOTHING'
            );
            $statement->execute([$street->id, ++$this->sequence, \count($street->names), $this->encode($street)]);
        } catch (\PDOException $exception) {
            throw $this->failure($exception);
        }
    }

    public function streets(): iterable
    {
        try {
            $statement = $this->pdo()->prepare('SELECT doc FROM street ORDER BY seq');
            $statement->execute();
        } catch (\PDOException $exception) {
            throw $this->failure($exception);
        }

        return $this->documents($statement);
    }

    public function voteForTranslation(Translation $translation, TranslationSource $source, int $votes = 1): void
    {
        if ($votes < 1) {
            throw new InvalidArgumentException(\sprintf('The votes must be positive, %d given.', $votes));
        }
        $this->pdo();

        $key = $translation->countryCode."\0".$translation->kind->value."\0".$translation->name."\0".$translation->language."\0".$translation->translation;
        $this->votes[$key] ??= [$translation, 0, 0];
        $this->votes[$key][TranslationSource::Address === $source ? 1 : 2] += $votes;
        if (\count($this->votes) >= self::VOTE_BUFFER) {
            $this->flushVotes();
        }
    }

    public function translationCandidates(): iterable
    {
        $this->flushVotes();
        try {
            $statement = $this->pdo()->prepare('SELECT cc, kind, name, lang, translation, addresses, own_names FROM vote ORDER BY cc, kind, name, lang, translation');
            $statement->execute();
        } catch (\PDOException $exception) {
            throw $this->failure($exception);
        }

        return $this->candidates($statement);
    }

    public function close(): void
    {
        if (!$this->pdo instanceof \PDO) {
            return;
        }
        $this->insertSeen = $this->insertPlace = $this->upsertStreetOfWay = $this->insertStreetOfAddress = $this->upsertVote = null;
        $this->pdo = null;
        $this->recentKeys = $this->recentStreetsOfAddress = $this->votes = [];
        $this->deleteFile();
    }

    /**
     * @return \Generator<int, list<Document>>
     */
    private function groups(\PDOStatement $statement): \Generator
    {
        $group = null;
        $places = [];
        try {
            while (false !== ($row = $statement->fetch(\PDO::FETCH_NUM))) {
                if ($row[0] !== $group && [] !== $places) {
                    yield $places;
                    $places = [];
                }
                $group = $row[0];
                $places[] = $this->decode($row[1]);
            }
        } catch (\PDOException $exception) {
            throw $this->failure($exception);
        } finally {
            $statement->closeCursor();
        }
        if ([] !== $places) {
            yield $places;
        }
    }

    private function flushVotes(): void
    {
        if ([] === $this->votes) {
            return;
        }
        $votes = $this->votes;
        $this->votes = [];

        try {
            $statement = $this->upsertVote ??= $this->pdo()->prepare(
                'INSERT INTO vote (cc, kind, name, lang, translation, addresses, own_names) VALUES (?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (cc, kind, name, lang, translation) DO UPDATE SET addresses = addresses + excluded.addresses, own_names = own_names + excluded.own_names'
            );
            foreach ($votes as [$translation, $addresses, $ownNames]) {
                $statement->execute([$translation->countryCode, $translation->kind->value, $translation->name, $translation->language, $translation->translation, $addresses, $ownNames]);
            }
        } catch (\PDOException $exception) {
            throw $this->failure($exception);
        }
    }

    /**
     * @return \Generator<int, non-empty-list<TranslationCandidate>>
     */
    private function candidates(\PDOStatement $statement): \Generator
    {
        $group = null;
        $candidates = [];
        try {
            while (false !== ($row = $statement->fetch(\PDO::FETCH_NUM))) {
                $candidate = $this->candidate($row);
                $key = [$row[0], $row[1], $row[2], $row[3]];
                if ($key !== $group && [] !== $candidates) {
                    yield $candidates;
                    $candidates = [];
                }
                $group = $key;
                $candidates[] = $candidate;
            }
        } catch (\PDOException $exception) {
            throw $this->failure($exception);
        } finally {
            $statement->closeCursor();
        }
        if ([] !== $candidates) {
            yield $candidates;
        }
    }

    /**
     * @param array<int, mixed> $row
     */
    private function candidate(array $row): TranslationCandidate
    {
        try {
            $kind = \is_string($row[1]) ? NameKind::tryFrom($row[1]) : null;
            if (!$kind instanceof NameKind || !\is_string($row[0]) || !\is_string($row[2]) || !\is_string($row[3]) || !\is_string($row[4])) {
                throw new InvalidArgumentException('a column is not a valid string');
            }

            return new TranslationCandidate(new Translation($row[0], $kind, $row[2], $row[3], $row[4]), (int) $row[5], (int) $row[6]);
        } catch (InvalidArgumentException $exception) {
            throw new StorageException(\sprintf('The workspace database holds a damaged translation: %s', $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * @return \Generator<int, Document>
     */
    private function documents(\PDOStatement $statement): \Generator
    {
        try {
            while (false !== ($document = $statement->fetchColumn())) {
                yield $this->decode($document);
            }
        } catch (\PDOException $exception) {
            throw $this->failure($exception);
        } finally {
            $statement->closeCursor();
        }
    }

    /**
     * @param array<string, true> $keys
     */
    private function remember(array &$keys, string $key): void
    {
        if (\count($keys) >= self::RECENT_KEYS) {
            $keys = [];
        }
        $keys[$key] = true;
    }

    private function encode(Document $document): string
    {
        try {
            return json_encode($document->toArray(), \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new StorageException(\sprintf('The document "%s" cannot be stored in the workspace: %s', $document->id, $exception->getMessage()), 0, $exception);
        }
    }

    private function decode(mixed $document): Document
    {
        try {
            $data = \is_string($document) ? json_decode($document, true, 8, \JSON_THROW_ON_ERROR) : null;
            if (!\is_array($data)) {
                throw new StorageException('The workspace database holds a damaged document.');
            }

            return Document::fromArray($data);
        } catch (\JsonException|InvalidArgumentException $exception) {
            throw new StorageException(\sprintf('The workspace database holds a damaged document: %s', $exception->getMessage()), 0, $exception);
        }
    }

    private function pdo(): \PDO
    {
        return $this->pdo ?? throw new StorageException('The workspace is closed.');
    }

    private function failure(\PDOException $exception): StorageException
    {
        return new StorageException(\sprintf('The workspace database "%s" failed: %s', $this->path, $exception->getMessage()), 0, $exception);
    }

    private function deleteFile(): void
    {
        foreach ([$this->path, $this->path.'-journal'] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
