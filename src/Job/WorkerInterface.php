<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Job;

interface WorkerInterface
{
    public function init(int $workerId): void;

    public function run(Job $job): Job;
}
