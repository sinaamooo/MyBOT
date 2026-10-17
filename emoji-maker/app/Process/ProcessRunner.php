<?php

declare(strict_types=1);

namespace EmojiMaker\Process;

/**
 * Runs external programs (ffmpeg, ffprobe) safely:
 *  - the command is an argument array (no shell, no injection possible)
 *  - hard timeout with termination of the process
 *  - optional "nice" to lower CPU priority
 *  - minimal environment and bounded output buffers
 */
final class ProcessRunner
{
    private const MAX_OUTPUT = 2 * 1024 * 1024;

    public function __construct(private int $timeout = 120, private int $nice = 10)
    {
    }

    public static function available(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return !in_array('proc_open', $disabled, true);
    }

    /**
     * @param list<string> $cmd program followed by its arguments
     */
    public function run(array $cmd, ?string $cwd = null, ?int $timeout = null): ProcessResult
    {
        if (!self::available()) {
            throw new ProcessException('Running external programs is disabled on this server (proc_open)');
        }
        if (!$cmd) {
            throw new ProcessException('Empty command');
        }
        foreach ($cmd as $arg) {
            if (!is_string($arg) || str_contains($arg, "\0")) {
                throw new ProcessException('Invalid argument');
            }
        }
        $timeout ??= $this->timeout;
        if ($this->nice > 0 && ($nice = self::which('nice')) !== null) {
            array_unshift($cmd, $nice, '-n', (string) min(19, $this->nice));
        }
        $env = [
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'LANG' => 'C',
            'HOME' => sys_get_temp_dir(),
        ];
        $start = microtime(true);
        $proc = @proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
        if (!is_resource($proc)) {
            throw new ProcessException('Unable to start process: ' . basename($cmd[0]));
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $err = '';
        $timedOut = false;
        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $changed = @stream_select($read, $write, $except, 0, 200000);
            if ($changed === false) {
                break;
            }
            foreach ($read as $r) {
                $chunk = fread($r, 65536);
                if ($chunk === false || $chunk === '') {
                    continue;
                }
                if ($r === $pipes[1]) {
                    if (strlen($out) < self::MAX_OUTPUT) {
                        $out .= $chunk;
                    }
                } elseif (strlen($err) < self::MAX_OUTPUT) {
                    $err .= $chunk;
                }
            }
            $status = proc_get_status($proc);
            if (!$status['running']) {
                // drain
                $out .= (string) stream_get_contents($pipes[1]);
                $err .= (string) stream_get_contents($pipes[2]);
                $exit = $status['exitcode'];
                break;
            }
            if (microtime(true) - $start > $timeout) {
                $timedOut = true;
                proc_terminate($proc, 15);
                usleep(300000);
                $status = proc_get_status($proc);
                if ($status['running']) {
                    proc_terminate($proc, 9);
                }
                $exit = -1;
                break;
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closeCode = proc_close($proc);
        $exit ??= $closeCode;
        if ($exit === -1 && !$timedOut) {
            $exit = $closeCode;
        }
        return new ProcessResult((int) $exit, substr($out, 0, self::MAX_OUTPUT), substr($err, 0, self::MAX_OUTPUT), $timedOut, microtime(true) - $start);
    }

    /** Finds an executable in PATH (or validates an absolute path). */
    public static function which(string $name): ?string
    {
        if ($name === '') {
            return null;
        }
        if (str_contains($name, '/')) {
            return (is_file($name) && is_executable($name)) ? $name : null;
        }
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
            return null;
        }
        $paths = explode(PATH_SEPARATOR, getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin');
        foreach (array_merge($paths, ['/usr/local/bin', '/usr/bin', '/bin', '/opt/bin', '/snap/bin']) as $dir) {
            $candidate = rtrim($dir, '/') . '/' . $name;
            if (@is_file($candidate) && @is_executable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }
}
