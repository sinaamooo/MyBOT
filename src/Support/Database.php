<?php

declare(strict_types=1);

namespace MyBot\Support;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

final class Database
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        if ($path !== ':memory:') {
            $dir = \dirname($path);
            if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
                throw new RuntimeException("امکان ساخت پوشه دیتابیس نبود: {$dir}");
            }
        }

        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA synchronous = NORMAL');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 10000');
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function migrate(string $schemaFile): void
    {
        if (!is_file($schemaFile)) {
            throw new RuntimeException("فایل schema پیدا نشد: {$schemaFile}");
        }

        $sql = file_get_contents($schemaFile);
        if ($sql === false) {
            throw new RuntimeException("خواندن فایل schema ناموفق بود: {$schemaFile}");
        }

        $this->pdo->exec($sql);
    }

    /** @param array<string, mixed>|list<mixed> $params */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($this->normalize($params));

        return $statement;
    }

    /**
     * @param array<string, mixed>|list<mixed> $params
     * @return array<string, mixed>|null
     */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed>|list<mixed> $params
     * @return list<array<string, mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @param array<string, mixed>|list<mixed> $params */
    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string, mixed>|list<mixed> $params */
    public function count(string $sql, array $params = []): int
    {
        return (int) $this->scalar($sql, $params);
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns      = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $this->run(
            sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $table,
                implode(', ', $columns),
                implode(', ', $placeholders),
            ),
            $data,
        );

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $params
     */
    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $assignments = [];
        $bind        = [];

        foreach ($data as $column => $value) {
            $assignments[]        = sprintf('%s = :set_%s', $column, $column);
            $bind['set_' . $column] = $value;
        }

        $statement = $this->run(
            sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $assignments), $where),
            $bind + $params,
        );

        return $statement->rowCount();
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $callback($this);
        }

        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * PDO cannot bind booleans/nulls into SQLite reliably through named params
     * when emulation is off, so normalise them up front.
     *
     * @param array<string, mixed>|list<mixed> $params
     * @return array<string, mixed>|list<mixed>
     */
    private function normalize(array $params): array
    {
        foreach ($params as $key => $value) {
            if (\is_bool($value)) {
                $params[$key] = $value ? 1 : 0;
            } elseif (\is_array($value)) {
                $params[$key] = json_encode($value, \JSON_UNESCAPED_UNICODE);
            }
        }

        return $params;
    }
}
