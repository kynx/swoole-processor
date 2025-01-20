<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor\Handler;

use Kynx\Swoole\Processor\Handler\WorkerStartHandler;
use Kynx\Swoole\Processor\Job\WorkerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swoole\Server;

#[CoversClass(WorkerStartHandler::class)]
final class WorkerStartHandlerTest extends TestCase
{
    public function testInvokeInitializesWorker(): void
    {
        $expected = 123;
        $worker   = $this->createMock(WorkerInterface::class);
        $worker->expects(self::once())
            ->method('init')
            ->with($expected);

        $handler = new WorkerStartHandler($worker);
        ($handler)(self::createStub(Server::class), $expected);
    }
}
