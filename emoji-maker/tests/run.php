<?php

declare(strict_types=1);

/*
 * Test runner: php tests/run.php [filter]
 *
 * Runs every tests/*Test.php in its own PHP process (static state such as the
 * App instance or registered secrets cannot leak between files) and prints a
 * summary. Exit code 0 = all passed (skipped tests do not fail the run).
 * A test file returns ['name' => fn () => ..., ...] or declares test_*()
 * functions; helpers are in tests/TestCase.php.
 */

use EmojiMaker\Tests\AssertionFailed;
use EmojiMaker\Tests\SkipTest;

require dirname(__DIR__) . '/app/autoload.php';
require __DIR__ . '/TestCase.php';

const RESULT_MARK = '##TEST-RESULT ';

$args = array_slice($argv, 1);

if (($args[0] ?? '') === '--child') {
    exit(run_file((string) ($args[1] ?? '')));
}

$filter = $args[0] ?? '';
$files = glob(__DIR__ . '/*Test.php') ?: [];
sort($files);
if ($filter !== '') {
    $files = array_values(array_filter($files, static fn (string $f) => stripos(basename($f), $filter) !== false));
}
if ($files === []) {
    fwrite(STDERR, "No test files found\n");
    exit(1);
}

$totals = ['passed' => 0, 'failed' => 0, 'skipped' => 0];
$failedFiles = [];
$started = microtime(true);
foreach ($files as $file) {
    echo basename($file), "\n";
    $proc = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', __FILE__, '--child', $file], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (!is_resource($proc)) {
        echo "  FAIL could not start a PHP process\n";
        $totals['failed']++;
        continue;
    }
    stream_set_blocking($pipes[2], false);
    $stderr = '';
    $summary = null;
    while (($line = fgets($pipes[1])) !== false) {
        if (str_starts_with($line, RESULT_MARK)) {
            $summary = json_decode(substr($line, strlen(RESULT_MARK)), true);
            continue;
        }
        echo $line;
        $stderr .= (string) stream_get_contents($pipes[2]);
    }
    fclose($pipes[1]);
    stream_set_blocking($pipes[2], true);
    $stderr .= (string) stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if (!is_array($summary)) {
        echo "  FAIL test process crashed (exit code {$code})\n";
        if (trim($stderr) !== '') {
            echo '  ', str_replace("\n", "\n  ", trim($stderr)), "\n";
        }
        $totals['failed']++;
        $failedFiles[] = basename($file);
        continue;
    }
    foreach ($totals as $k => $v) {
        $totals[$k] = $v + (int) ($summary[$k] ?? 0);
    }
    if ((int) ($summary['failed'] ?? 0) > 0) {
        $failedFiles[] = basename($file);
    }
}

printf(
    "\n%d passed, %d failed, %d skipped in %.1f s%s\n",
    $totals['passed'],
    $totals['failed'],
    $totals['skipped'],
    microtime(true) - $started,
    $failedFiles !== [] ? ' (failures in: ' . implode(', ', $failedFiles) . ')' : ''
);
exit($totals['failed'] > 0 ? 1 : 0);

/** Child process: runs the tests of one file and prints one line per test + a summary line. */
function run_file(string $file): int
{
    if (!is_file($file)) {
        fwrite(STDERR, "Test file not found\n");
        return 1;
    }
    $before = get_defined_functions()['user'];
    $returned = (static fn () => require $file)();
    $tests = [];
    if (is_array($returned)) {
        foreach ($returned as $name => $fn) {
            if (is_callable($fn)) {
                $tests[(string) $name] = $fn;
            }
        }
    }
    foreach (array_diff(get_defined_functions()['user'], $before) as $fn) {
        $short = substr((string) strrchr('\\' . $fn, '\\'), 1);
        if (str_starts_with($short, 'test_')) {
            $tests[$short] = $fn;
        }
    }
    $result = ['passed' => 0, 'failed' => 0, 'skipped' => 0];
    foreach ($tests as $name => $fn) {
        $t = microtime(true);
        try {
            $fn();
            $result['passed']++;
            printf("  ok   %s (%d ms)\n", $name, (int) ((microtime(true) - $t) * 1000));
        } catch (SkipTest $e) {
            $result['skipped']++;
            printf("  skip %s: %s\n", $name, $e->getMessage());
        } catch (AssertionFailed $e) {
            $result['failed']++;
            printf("  FAIL %s: %s\n", $name, $e->getMessage());
            printf("       at %s\n", assertion_location($e));
        } catch (\Throwable $e) {
            $result['failed']++;
            printf("  FAIL %s: %s: %s (%s:%d)\n", $name, get_class($e), $e->getMessage(), basename($e->getFile()), $e->getLine());
        }
    }
    echo RESULT_MARK, json_encode($result), "\n";
    return $result['failed'] > 0 ? 1 : 0;
}

/** File:line of the test code that made the failing assertion. */
function assertion_location(\Throwable $e): string
{
    foreach ($e->getTrace() as $frame) {
        $f = (string) ($frame['file'] ?? '');
        if ($f !== '' && !str_ends_with($f, 'TestCase.php')) {
            return basename($f) . ':' . (int) ($frame['line'] ?? 0);
        }
    }
    return basename($e->getFile()) . ':' . $e->getLine();
}
