<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Job;

interface CompletionHandlerInterface
{
    public function complete(Job $job): void;
}
