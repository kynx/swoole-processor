<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Handler;

use Kynx\Swoole\Processor\Job\Job;
use Kynx\Swoole\Processor\Job\WorkerError;
use Kynx\Swoole\Processor\Job\WorkerInterface;
use Swoole\Atomic;
use Swoole\Server;
use Throwable;

use function assert;
use function pack;
use function serialize;
use function strlen;
use function substr;
use function unserialize;

/**
 * @internal
 *
 * @psalm-internal \Kynx\Swoole\Processor
 * @psalm-internal \KynxTest\Swoole\Processor
 */
final readonly class ReceiveHandler
{
    public function __construct(private WorkerInterface $worker, private Atomic $errorCount)
    {
    }

    public function __invoke(Server $server, int $fd, int $rectorId, string $data): void
    {
        $job = unserialize(substr($data, 4));
        assert($job instanceof Job);
        unset($data);

        try {
            $this->send($server, $this->worker->run($job), $fd);
        } catch (Throwable $throwable) {
            $this->errorCount->add();
            $this->send($server, $job->withResult(WorkerError::fromThrowable($throwable)), $fd);
        }
    }

    private function send(Server $server, Job $job, int $fd): void
    {
        $serialized = serialize($job);
        unset($job);
        $server->send($fd, pack('N', strlen($serialized)) . $serialized);
    }
}
