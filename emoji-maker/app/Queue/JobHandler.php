<?php

declare(strict_types=1);

namespace EmojiMaker\Queue;

/**
 * One handler per job type. handle() returns a JSON-able result summary that
 * is stored in generation_jobs.result; it throws JobFailed (user facing
 * message), JobCancelled or any other exception (logged, generic message).
 */
interface JobHandler
{
    /** @return array<string,mixed> */
    public function handle(JobContext $ctx): array;
}
