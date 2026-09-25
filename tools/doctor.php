<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Nikto\Core\Diagnostics;

echo "\n" . Diagnostics::renderText() . "\n";

exit(str_contains(Diagnostics::renderText(), '[ !! ]') ? 1 : 0);
