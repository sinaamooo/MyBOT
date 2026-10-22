<?php

declare(strict_types=1);

namespace EmojiMaker\Tests;

use EmojiMaker\App;
use EmojiMaker\Database\Database;

/**
 * Tiny assertion + fixture helpers for tests/run.php (no PHPUnit, so the suite
 * also runs on a bare shared host).
 *
 * A test file (tests/<Name>Test.php) either returns an array
 * ['test name' => function (): void {...}, ...] or declares functions named
 * test_*(). Use `use EmojiMaker\Tests\TestCase as T;` and T::assertSame(...).
 *
 * Database tests read TEST_DB_HOST / TEST_DB_PORT / TEST_DB_NAME / TEST_DB_USER /
 * TEST_DB_PASS and are skipped when TEST_DB_NAME is not set.
 */
final class TestCase
{
    private static ?Database $db = null;
    private static bool $schemaLoaded = false;
    /** @var list<string> */
    private static array $tempDirs = [];
    /** @var list<resource> */
    private static array $servers = [];

    // ---------------------------------------------------------- assertions

    public static function assertTrue(mixed $value, string $message = ''): void
    {
        if ($value !== true) {
            self::fail($message !== '' ? $message : 'Expected true, got ' . self::export($value));
        }
    }

    public static function assertFalse(mixed $value, string $message = ''): void
    {
        if ($value !== false) {
            self::fail($message !== '' ? $message : 'Expected false, got ' . self::export($value));
        }
    }

    public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            self::fail(($message !== '' ? $message . ': ' : '') . 'expected ' . self::export($expected) . ', got ' . self::export($actual));
        }
    }

    public static function assertNotSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            self::fail(($message !== '' ? $message . ': ' : '') . 'did not expect ' . self::export($actual));
        }
    }

    public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            self::fail(($message !== '' ? $message . ': ' : '') . 'expected ' . self::export($expected) . ', got ' . self::export($actual));
        }
    }

    public static function assertNull(mixed $value, string $message = ''): void
    {
        self::assertSame(null, $value, $message);
    }

    public static function assertNotNull(mixed $value, string $message = ''): void
    {
        if ($value === null) {
            self::fail($message !== '' ? $message : 'Expected a value, got null');
        }
    }

    /** @param \Countable|array<mixed> $value */
    public static function assertCount(int $expected, \Countable|array $value, string $message = ''): void
    {
        self::assertSame($expected, count($value), $message !== '' ? $message : 'count');
    }

    public static function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            self::fail(($message !== '' ? $message . ': ' : '') . self::export($haystack) . ' does not contain ' . self::export($needle));
        }
    }

    public static function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        if (str_contains($haystack, $needle)) {
            self::fail(($message !== '' ? $message . ': ' : '') . 'unexpected ' . self::export($needle) . ' found');
        }
    }

    public static function assertMatches(string $pattern, string $value, string $message = ''): void
    {
        if (!preg_match($pattern, $value)) {
            self::fail(($message !== '' ? $message . ': ' : '') . self::export($value) . ' does not match ' . $pattern);
        }
    }

    /** @param array<mixed> $array */
    public static function assertArrayHasKey(int|string $key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            self::fail(($message !== '' ? $message . ': ' : '') . 'missing key ' . self::export($key));
        }
    }

    /**
     * Runs $fn and asserts that it throws $class (optionally with $contains in the message).
     * @param class-string<\Throwable> $class
     */
    public static function assertThrows(string $class, callable $fn, ?string $contains = null): \Throwable
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!$e instanceof $class) {
                self::fail('Expected ' . $class . ', got ' . get_class($e) . ': ' . $e->getMessage());
            }
            if ($contains !== null && !str_contains($e->getMessage(), $contains)) {
                self::fail('Exception message ' . self::export($e->getMessage()) . ' does not contain ' . self::export($contains));
            }
            return $e;
        }
        self::fail('Expected exception ' . $class . ' was not thrown');
    }

    public static function fail(string $message): never
    {
        throw new AssertionFailed($message);
    }

    public static function skip(string $reason): never
    {
        throw new SkipTest($reason);
    }

    // ------------------------------------------------------------ fixtures

    /** Private temporary directory, removed when the test process ends. */
    public static function tempDir(string $prefix = 'emoji-test'): string
    {
        $dir = sys_get_temp_dir() . '/' . preg_replace('/[^a-z0-9_-]/i', '', $prefix) . '-' . getmypid() . '-' . bin2hex(random_bytes(4));
        if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create temp dir');
        }
        if (self::$tempDirs === []) {
            register_shutdown_function([self::class, 'cleanup']);
        }
        self::$tempDirs[] = $dir;
        return $dir;
    }

    /** Test database (schema from database/schema.sql loaded once per process) or skip. */
    public static function db(): Database
    {
        if (self::$db === null) {
            $cfg = self::dbConfig();
            if ($cfg === null) {
                self::skip('TEST_DB_NAME not set (integration test needs MariaDB/MySQL)');
            }
            self::$db = new Database($cfg);
            try {
                self::$db->pdo();
            } catch (\Throwable $e) {
                self::$db = null;
                self::skip('test database unreachable: ' . $e->getMessage());
            }
        }
        if (!self::$schemaLoaded) {
            self::loadSchema(self::$db);
            self::$schemaLoaded = true;
        }
        return self::$db;
    }

    /** @return array<string,mixed>|null */
    public static function dbConfig(): ?array
    {
        $name = (string) getenv('TEST_DB_NAME');
        if ($name === '') {
            return null;
        }
        return [
            'host' => getenv('TEST_DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
            'name' => $name,
            'user' => getenv('TEST_DB_USER') ?: 'root',
            'pass' => (string) getenv('TEST_DB_PASS'),
            'socket' => getenv('TEST_DB_SOCKET') ?: '',
            'charset' => 'utf8mb4',
        ];
    }

    /** Runs database/schema.sql (CREATE TABLE IF NOT EXISTS ...) against $db. */
    public static function loadSchema(Database $db): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__) . '/database/schema.sql');
        foreach (self::splitSql($sql) as $statement) {
            $db->pdo()->exec($statement);
        }
    }

    /**
     * Fresh App on the test database with a private storage root.
     * @param array<string,mixed> $config dotted config overrides (e.g. ['telegram.webhook_mode' => 'queue'])
     */
    public static function app(array $config = [], bool $withDb = true): App
    {
        App::setInstance(null);
        $app = App::boot(dirname(__DIR__));
        $app->config()->set('storage.path', self::tempDir('storage'));
        if ($withDb) {
            self::db();
            $app->config()->set('db', self::dbConfig());
        } else {
            $app->config()->set('db.name', '');
        }
        foreach ($config as $key => $value) {
            $app->config()->set($key, $value);
        }
        return $app;
    }

    /**
     * Starts the mock Bot API (tests/Mock/telegram_mock.php) with php -S on a free port.
     * It is stopped when the test process ends. Returns base URL and the files it uses.
     * @param array<string,string> $env extra environment (MOCK_BOT_USERNAME, MOCK_TOKEN ...)
     * @return array{base:string,log:string,control:string,state:string,fixtures:string,token:string,username:string}
     */
    public static function telegramMock(array $env = []): array
    {
        $dir = self::tempDir('tgmock');
        $probe = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($probe === false) {
            self::skip('cannot open a local port: ' . $errstr);
        }
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);
        $info = [
            'base' => 'http://127.0.0.1:' . $port,
            'log' => $dir . '/calls.jsonl',
            'control' => $dir . '/control.json',
            'state' => $dir . '/state.json',
            'fixtures' => $env['MOCK_FIXTURES'] ?? __DIR__ . '/fixtures',
            'token' => '123456789:TEST_TOKEN_FOR_LOCAL_MOCK_SERVER_ONLY_xx',
            'username' => $env['MOCK_BOT_USERNAME'] ?? 'emoji_maker_dev_bot',
        ];
        $vars = array_merge(getenv(), [
            'MOCK_LOG' => $info['log'],
            'MOCK_CONTROL' => $info['control'],
            'MOCK_STATE' => $info['state'],
            'MOCK_FIXTURES' => $info['fixtures'],
            'MOCK_BOT_USERNAME' => $info['username'],
        ], $env);
        $cmd = [PHP_BINARY, '-d', 'upload_max_filesize=64M', '-d', 'post_max_size=80M', '-d', 'variables_order=EGPCS',
            '-S', '127.0.0.1:' . $port, __DIR__ . '/Mock/telegram_mock.php'];
        $proc = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $dir . '/server.log', 'a'], 2 => ['file', $dir . '/server.log', 'a']], $pipes, __DIR__, $vars);
        if (!is_resource($proc)) {
            self::skip('cannot start php -S');
        }
        if (self::$servers === []) {
            register_shutdown_function([self::class, 'stopServers']);
        }
        self::$servers[] = $proc;
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $s = @fsockopen('127.0.0.1', $port, $e1, $e2, 0.2);
            if ($s !== false) {
                fclose($s);
                return $info;
            }
            usleep(50000);
        }
        self::fail('mock Bot API server did not start');
    }

    /**
     * Calls recorded by the mock server, optionally only those of one method.
     * @return list<array<string,mixed>>
     */
    public static function mockCalls(string $log, ?string $method = null): array
    {
        if (!is_file($log)) {
            return [];
        }
        $out = [];
        foreach (file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $row = json_decode($line, true);
            if (is_array($row) && ($method === null || ($row['method'] ?? null) === $method)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $control */
    public static function mockControl(string $file, array $control): void
    {
        file_put_contents($file, json_encode($control, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /** @internal shutdown hook */
    public static function stopServers(): void
    {
        foreach (self::$servers as $proc) {
            if (is_resource($proc)) {
                @proc_terminate($proc);
                @proc_close($proc);
            }
        }
        self::$servers = [];
    }

    /** @internal shutdown hook */
    public static function cleanup(): void
    {
        self::stopServers();
        foreach (self::$tempDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) {
                /** @var \SplFileInfo $f */
                $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
            @rmdir($dir);
        }
        self::$tempDirs = [];
    }

    /**
     * Splits an SQL script into statements (quotes and -- / # comments aware).
     * @return list<string>
     */
    public static function splitSql(string $sql): array
    {
        $out = [];
        $buf = '';
        $len = strlen($sql);
        $quote = null;
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($quote !== null) {
                $buf .= $c;
                if ($c === '\\' && $quote !== '`' && $i + 1 < $len) {
                    $buf .= $sql[++$i];
                } elseif ($c === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($c === '-' && substr($sql, $i, 3) === '-- ' || $c === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end;
                $buf .= "\n";
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
            }
            if ($c === ';') {
                if (trim($buf) !== '') {
                    $out[] = trim($buf);
                }
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }
        return $out;
    }

    private static function export(mixed $value): string
    {
        $text = is_string($value) ? '"' . $value . '"' : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return mb_strlen($text) > 300 ? mb_substr($text, 0, 300) . '...' : $text;
    }
}

/** A failed assertion. */
final class AssertionFailed extends \RuntimeException
{
}

/** Thrown by TestCase::skip(); reported as skipped, not failed. */
final class SkipTest extends \RuntimeException
{
}
