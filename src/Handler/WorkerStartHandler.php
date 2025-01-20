<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Handler;

use Kynx\Swoole\Processor\Job\WorkerInterface;
use Swoole\Server;

/**
 * @internal
 *
 * @psalm-internal \Kynx\Swoole\Processor
 * @psalm-internal \KynxTest\Swoole\Processor
 */
final readonly class WorkerStartHandler
{
    public function __construct(private WorkerInterface $worker)
    {
    }

    public function __invoke(Server $server, int $workerId): void
    {
        $this->worker->init($workerId);
    }
}
