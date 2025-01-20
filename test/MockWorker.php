<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor;

use Kynx\Swoole\Processor\Job\Job;
use Kynx\Swoole\Processor\Job\WorkerInterface;

use function fopen;
use function fwrite;

final class MockWorker implements WorkerInterface
{
    /** @var resource */
    private $handle;

    public function __construct(string $file)
    {
        $this->handle = fopen($file, 'a+');
    }

    public function init(int $workerId): void
    {
        echo "worker $workerId init\n";
        fwrite($this->handle, "worker $workerId init\n");
    }

    public function run(Job $job): Job
    {
        $payload = (string) $job->getPayload();
        fwrite($this->handle, "worker run: $payload\n");
        return $job->withResult("$payload processed");
    }
}
