<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Handler;

use Kynx\Swoole\Processor\Job\Job;
use Kynx\Swoole\Processor\Job\WorkerError;
use Kynx\Swoole\Processor\Job\WorkerInterface;
use Swoole\Server;
use Throwable;

use function assert;
use function serialize;
use function unserialize;

/**
 * @internal
 *
 * @psalm-internal \Kynx\Swoole\Processor
 * @psalm-internal \KynxTest\Swoole\Processor
 */
final readonly class ReceiveHandler
{
    public function __construct(private WorkerInterface $worker)
    {
    }

    public function __invoke(Server $server, int $fd, int $rectorId, string $data): void
    {
        $job = unserialize($data);
        assert($job instanceof Job);

        try {
            $result = $this->worker->run($job);
            $server->send($fd, serialize($result));
        } catch (Throwable $throwable) {
            $server->send($fd, serialize($job->withResult(WorkerError::fromThrowable($throwable))));
        }
    }
}
