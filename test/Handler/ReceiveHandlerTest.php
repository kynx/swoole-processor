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
use Swoole\Atomic;
use Swoole\Server;

use function pack;
use function serialize;
use function strlen;

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

        $server     = $this->createMock(Server::class);
        $serialized = serialize($expected);
        $server->expects(self::once())
            ->method('send')
            ->with(123, pack('N', strlen($serialized)) . $serialized);
        $errorCount = new Atomic();

        $handler = new ReceiveHandler($worker, $errorCount);
        $data    = serialize($job);
        ($handler)($server, 123, 1, pack('N', $data) . $data);

        self::assertSame(0, $errorCount->get());
    }

    public function testInvokeHandlesWorkerException(): void
    {
        $job       = new Job('foo');
        $exception = new Exception('Test message', 404);
        $expected  = $job->withResult(WorkerError::fromThrowable($exception));
        $worker    = self::createStub(WorkerInterface::class);
        $worker->method('run')
            ->willThrowException($exception);

        $server     = $this->createMock(Server::class);
        $serialized = serialize($expected);
        $server->expects(self::once())
            ->method('send')
            ->with(123, pack('N', strlen($serialized)) . $serialized);
        $errorCount = new Atomic();

        $handler = new ReceiveHandler($worker, $errorCount);
        $data    = serialize($job);
        ($handler)($server, 123, 1, pack('N', $data) . $data);

        self::assertSame(1, $errorCount->get());
    }
}
