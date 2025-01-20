<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor;

use Kynx\Swoole\Processor\Handler\ReceiveHandler;
use Kynx\Swoole\Processor\Handler\WorkerStartHandler;
use Kynx\Swoole\Processor\Job\CompletionHandlerInterface;
use Kynx\Swoole\Processor\Job\JobProviderInterface;
use Kynx\Swoole\Processor\Job\NoOpCompletionHandler;
use Kynx\Swoole\Processor\Job\WorkerInterface;
use Kynx\Swoole\Processor\Process\JobRunner;
use Swoole\Process;
use Swoole\Server;

use const SWOOLE_HOOK_ALL;
use const SWOOLE_PROCESS;
use const SWOOLE_UNIX_STREAM;

final readonly class Processor
{
    private Server $server;

    public function __construct(
        Config $config,
        JobProviderInterface $jobProvider,
        WorkerInterface $worker,
        CompletionHandlerInterface $completionHandler = new NoOpCompletionHandler()
    ) {
        $this->server = new Server($config->getSocket(), 0, SWOOLE_PROCESS, SWOOLE_UNIX_STREAM);
        $this->server->set([
            'daemonize'     => false,
            'worker_num'    => $config->getWorkers(),
            'max_coroutine' => $config->getMaxCoroutines(),
            'hook_flags'    => SWOOLE_HOOK_ALL,
        ]);

        $this->server->on('WorkerStart', new WorkerStartHandler($worker));
        $this->server->on('Receive', new ReceiveHandler($worker));

        $runner = new JobRunner($this->server, $jobProvider, $completionHandler, $config->getConcurrency());
        $this->server->addProcess(new Process($runner, false, 0, true));
    }

    public function start(): void
    {
        $this->server->start();
    }
}
