<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor\Handler;

use Exception;
use Kynx\Swoole\Processor\Handler\ReceiveHandler;
use Kynx\Swoole\Processor\Job\Job;
use Kynx\Swoole\Processor\Job\WorkerError;
use Kynx\Swoole\Processor\Job\WorkerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swoole\Server;

use function serialize;

#[CoversClass(ReceiveHandler::class)]
final class ReceiveHandlerTest extends TestCase
{
    public function testInvokeRunsJob(): void
    {
        $job      = new Job('aaa');
        $expected = $job->withResult('processed');
        $worker   = self::createStub(WorkerInterface::class);
        $worker->method('run')
            ->willReturn($expected);

        $server = $this->createMock(Server::class);
        $server->expects(self::once())
            ->method('send')
            ->with(123, serialize($expected));

        $handler = new ReceiveHandler($worker);
        ($handler)($server, 123, 1, serialize($job));
    }

    public function testInvokeHandlesWorkerException(): void
    {
        $job       = new Job('foo');
        $exception = new Exception('Test message', 404);
        $expected  = $job->withResult(WorkerError::fromThrowable($exception));
        $worker    = self::createStub(WorkerInterface::class);
        $worker->method('run')
            ->willThrowException($exception);

        $server = $this->createMock(Server::class);
        $server->expects(self::once())
            ->method('send')
            ->with(123, serialize($expected));

        $handler = new ReceiveHandler($worker);
        ($handler)($server, 123, 1, serialize($job));
    }
}
