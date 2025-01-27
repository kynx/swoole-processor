<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor;

use Kynx\Swoole\Processor\Handler\ReceiveHandler;
use Kynx\Swoole\Processor\Handler\WorkerErrorHandler;
use Kynx\Swoole\Processor\Handler\WorkerStartHandler;
use Kynx\Swoole\Processor\Job\CompletionHandlerInterface;
use Kynx\Swoole\Processor\Job\JobProviderInterface;
use Kynx\Swoole\Processor\Job\NoOpCompletionHandler;
use Kynx\Swoole\Processor\Job\WorkerInterface;
use Kynx\Swoole\Processor\Process\JobRunner;
use Swoole\Atomic;
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
        CompletionHandlerInterface $completionHandler = new NoOpCompletionHandler(),
        private Atomic $errorCount = new Atomic()
    ) {
        $this->server = new Server($config->getSocket(), 0, SWOOLE_PROCESS, SWOOLE_UNIX_STREAM);
        $this->server->set([
            'daemonize'             => false,
            'hook_flags'            => SWOOLE_HOOK_ALL,
            'max_coroutine'         => $config->getMaxCoroutines(),
            'open_length_check'     => true,
            'package_body_offset'   => 4,
            'package_length_offset' => 0,
            'package_length_type'   => 'N',
            'package_max_length'    => $config->getMaxPacketLength(),
            'worker_num'            => $config->getWorkers(),
        ]);

        $this->server->on('WorkerStart', new WorkerStartHandler($worker));
        $this->server->on('Receive', new ReceiveHandler($worker, $this->errorCount));
        $this->server->on('WorkerError', new WorkerErrorHandler($this->errorCount));

        $runner = new JobRunner(
            $this->server,
            $this->errorCount,
            $jobProvider,
            $completionHandler,
            $config->getConcurrency(),
            $config->getSocketTimeout(),
            $config->getMaxPacketLength()
        );
        $this->server->addProcess(new Process($runner, false, 0, true));
    }

    public function run(): bool
    {
        $this->server->start();
        return $this->errorCount->get() === 0;
    }
}
