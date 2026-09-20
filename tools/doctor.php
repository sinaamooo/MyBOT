<?php
/**
 * بررسی سلامت نصب:  php tools/doctor.php
 *
 * همان بررسی‌هایی که در مرورگر با check.php دیده می‌شود.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Nikto\Core\Diagnostics;

echo "\n" . Diagnostics::renderText() . "\n";

exit(str_contains(Diagnostics::renderText(), '[ !! ]') ? 1 : 0);
