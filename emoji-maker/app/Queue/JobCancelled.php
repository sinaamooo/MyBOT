<?php

declare(strict_types=1);

namespace EmojiMaker\Queue;

/**
 * Thrown by JobContext::checkCancelled() when the user / an admin cancelled
 * the job (or the worker is shutting down); the worker decides which.
 */
final class JobCancelled extends \RuntimeException
{
}
