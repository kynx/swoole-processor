<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor;

use Kynx\Swoole\Processor\Job\Job;
use Kynx\Swoole\Processor\Job\WorkerInterface;

use function assert;
use function fopen;
use function fwrite;
use function is_resource;
use function rand;
use function usleep;

final class MockWorker implements WorkerInterface
{
    public bool $triggerError = false;
    /** @var resource */
    private $handle;

    public function __construct(string $file)
    {
        $handle = fopen($file, 'a+');
        assert(is_resource($handle));
        $this->handle = $handle;
    }

    public function init(int $workerId): void
    {
        fwrite($this->handle, "worker $workerId init\n");
    }

    public function run(Job $job): Job
    {
        if ($this->triggerError) {
            die("Worker died");
        }

        $payload = (string) $job->getPayload();
        usleep(rand(1000, 100000));
        fwrite($this->handle, "worker run: $payload\n");
        return $job->withResult("$payload processed");
    }
}
