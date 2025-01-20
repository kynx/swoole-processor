<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Job;

final readonly class NoOpCompletionHandler implements CompletionHandlerInterface
{
    public function complete(Job $job): void
    {
    }
}
