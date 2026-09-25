<?php
declare(strict_types=1);

namespace Nikto\Core;

final class Log
{
    private const LEVELS = ['debug' => 10, 'info' => 20, 'warn' => 30, 'error' => 40];

    private static bool $guarded = false;

    public static function guardFatals(): void
    {
        if (self::$guarded) {
            return;
        }
        self::$guarded = true;

        set_exception_handler(static function (\Throwable $e): void {
            self::error('Uncaught exception', [
                'message' => $e->getMessage(),
                'where'   => basename($e->getFile()) . ':' . $e->getLine(),
            ]);
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            self::error('Fatal error', [
                'message' => $error['message'],
                'where'   => basename((string) $error['file']) . ':' . $error['line'],
            ]);
        });
    }

    public static function recent(int $limit = 8): array
    {
        try {
            $rows = Db::all(
                "SELECT level, message, context, created_at FROM logs
                 WHERE level IN ('error','warn') ORDER BY created_at DESC LIMIT :n",
                [':n' => $limit]
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map(static fn (array $r): array => [
            'level'      => (string) $r['level'],
            'message'    => (string) $r['message'],
            'context'    => (string) $r['context'],
            'created_at' => (int) $r['created_at'],
        ], $rows);
    }

    public static function debug(string $msg, array $ctx = []): void { self::write('debug', $msg, $ctx); }
    public static function info(string $msg, array $ctx = []): void  { self::write('info', $msg, $ctx); }
    public static function warn(string $msg, array $ctx = []): void  { self::write('warn', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void { self::write('error', $msg, $ctx); }

    private static function write(string $level, string $msg, array $ctx): void
    {
        $min = self::LEVELS[strtolower((string) Config::get('log_level', 'info'))] ?? 20;
        if ((self::LEVELS[$level] ?? 20) < $min) {
            return;
        }
        $ctxJson = $ctx ? json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $line = sprintf('[%s] %-5s %s %s', date('Y-m-d H:i:s'), strtoupper($level), $msg, $ctxJson);

        if (PHP_SAPI === 'cli') {
            fwrite($level === 'error' ? STDERR : STDOUT, $line . PHP_EOL);
        }
        @file_put_contents(APP_STORAGE . '/logs/bot-' . date('Y-m-d') . '.log', $line . PHP_EOL, FILE_APPEND);

        try {
            Db::exec(
                'INSERT INTO logs(level, message, context, created_at) VALUES(:l, :m, :c, :t)',
                [':l' => $level, ':m' => $msg, ':c' => $ctxJson, ':t' => time()]
            );
            if (random_int(1, 50) === 1) {
                Db::exec('DELETE FROM logs WHERE created_at < :t', [':t' => time() - 7 * 86400]);
                foreach (glob(APP_STORAGE . '/logs/bot-*.log') ?: [] as $file) {
                    if (filemtime($file) < time() - 14 * 86400) {
                        @unlink($file);
                    }
                }
            }
        } catch (\Throwable) {
        }
    }
}
