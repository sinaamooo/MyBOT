<?php

declare(strict_types=1);

namespace EmojiMaker\Database;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper. Every value goes through prepared statements; table and
 * column names used by helper methods are validated identifiers coming from
 * code, never from user input.
 */
final class Database
{
    private ?PDO $pdo = null;
    private int $transactionDepth = 0;

    /** @param array<string,mixed> $cfg */
    public function __construct(private array $cfg)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $charset = preg_match('/^[a-z0-9_]+$/i', (string) ($this->cfg['charset'] ?? '')) ? $this->cfg['charset'] : 'utf8mb4';
            if (!empty($this->cfg['socket'])) {
                $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $this->cfg['socket'], $this->cfg['name'], $charset);
            } else {
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $this->cfg['host'], (int) $this->cfg['port'], $this->cfg['name'], $charset);
            }
            $this->pdo = new PDO($dsn, (string) $this->cfg['user'], (string) $this->cfg['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_TIMEOUT => 10,
            ]);
            $this->pdo->exec("SET NAMES {$charset} COLLATE {$charset}_unicode_ci");
            $this->pdo->exec("SET time_zone = '" . date('P') . "'");
        }
        return $this->pdo;
    }

    public function setPdo(PDO $pdo): void
    {
        $this->pdo = $pdo;
    }

    /** @param array<int|string,mixed> $params */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * @param array<int|string,mixed> $params
     * @return array<string,mixed>|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<int|string,mixed> $params
     * @return list<array<string,mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** @param array<int|string,mixed> $params */
    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data, bool $ignore = false): int
    {
        $this->assertIdentifier($table);
        $cols = array_keys($data);
        array_walk($cols, fn ($c) => $this->assertIdentifier($c));
        $sql = sprintf(
            'INSERT %sINTO `%s` (`%s`) VALUES (%s)',
            $ignore ? 'IGNORE ' : '',
            $table,
            implode('`,`', $cols),
            implode(',', array_map(fn ($c) => ':' . $c, $cols))
        );
        $stmt = $this->query($sql, $this->prefix($data));
        if ($ignore && $stmt->rowCount() === 0) {
            return 0;
        }
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where column => value (AND)
     */
    public function update(string $table, array $data, array $where): int
    {
        $this->assertIdentifier($table);
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $this->assertIdentifier($col);
            $sets[] = "`{$col}` = :s_{$col}";
            $params['s_' . $col] = $val;
        }
        $conds = [];
        foreach ($where as $col => $val) {
            $this->assertIdentifier($col);
            if ($val === null) {
                $conds[] = "`{$col}` IS NULL";
            } else {
                $conds[] = "`{$col}` = :w_{$col}";
                $params['w_' . $col] = $val;
            }
        }
        if (!$conds) {
            throw new \LogicException('Refusing UPDATE without WHERE');
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), implode(' AND ', $conds));
        return $this->query($sql, $params)->rowCount();
    }

    /** @param array<string,mixed> $where */
    public function delete(string $table, array $where): int
    {
        $this->assertIdentifier($table);
        $conds = [];
        $params = [];
        foreach ($where as $col => $val) {
            $this->assertIdentifier($col);
            $conds[] = "`{$col}` = :{$col}";
            $params[$col] = $val;
        }
        if (!$conds) {
            throw new \LogicException('Refusing DELETE without WHERE');
        }
        return $this->query(sprintf('DELETE FROM `%s` WHERE %s', $table, implode(' AND ', $conds)), $params)->rowCount();
    }

    /**
     * @template T
     * @param callable(self):T $fn
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        $pdo = $this->pdo();
        if ($this->transactionDepth === 0) {
            $pdo->beginTransaction();
        }
        $this->transactionDepth++;
        try {
            $result = $fn($this);
            $this->transactionDepth--;
            if ($this->transactionDepth === 0) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            $this->transactionDepth--;
            if ($this->transactionDepth === 0 && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** MySQL named lock (serialises work per user / per resource). */
    public function acquireLock(string $name, int $timeout = 5): bool
    {
        return (int) $this->value('SELECT GET_LOCK(?, ?)', [$this->lockName($name), $timeout]) === 1;
    }

    public function releaseLock(string $name): void
    {
        $this->query('SELECT RELEASE_LOCK(?)', [$this->lockName($name)]);
    }

    private function lockName(string $name): string
    {
        $db = (string) ($this->cfg['name'] ?? 'db');
        $full = $db . ':' . $name;
        return strlen($full) > 60 ? substr($db, 0, 20) . ':' . sha1($name) : $full;
    }

    public function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function prefix(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $out[':' . $k] = $v;
        }
        return $out;
    }

    private function assertIdentifier(string $name): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]{0,63}$/i', $name)) {
            throw new \InvalidArgumentException('Invalid SQL identifier');
        }
    }
}
