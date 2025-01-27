<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor\Process;

use ArrayIterator;
use Exception;
use Kynx\Swoole\Processor\Job\CompletionHandlerInterface;
use Kynx\Swoole\Processor\Job\Job;
use Kynx\Swoole\Processor\Job\JobProviderInterface;
use Kynx\Swoole\Processor\Process\JobRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Swoole\Atomic;
use Swoole\Client;
use Swoole\Process;
use Swoole\Server;

use function array_map;
use function array_shift;
use function pack;
use function serialize;
use function strlen;
use function Swoole\Coroutine\run;

use const SOCKET_EAGAIN;

#[CoversClass(JobRunner::class)]
#[RunTestsInSeparateProcesses]
final class JobRunnerTest extends TestCase
{
    private const SOCK = '/tmp/server.sock';

    private Server&MockObject $server;
    private Atomic $errorCount;
    private Client&MockObject $client;
    private JobProviderInterface&Stub $jobProvider;
    private CompletionHandlerInterface&MockObject $completionHandler;
    private JobRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server            = $this->createMock(Server::class);
        $this->server->host      = self::SOCK;
        $this->errorCount        = new Atomic();
        $this->client            = $this->createMock(Client::class);
        $this->jobProvider       = self::createStub(JobProviderInterface::class);
        $this->completionHandler = $this->createMock(CompletionHandlerInterface::class);

        $this->runner = new JobRunner(
            $this->server,
            $this->errorCount,
            $this->jobProvider,
            $this->completionHandler,
            2,
            0.5,
            64 * 1024,
            fn(): Client => $this->client
        );
    }

    public function testInvokeHandlesEmptyIterator(): void
    {
        $this->jobProvider->method('getIterator')
            ->willReturn(new ArrayIterator([]));
        $this->client->expects(self::never())
            ->method('connect');
        $this->completionHandler->expects(self::never())
            ->method('complete');

        run($this->runner);

        self::assertSame(0, $this->errorCount->get());
    }

    public function testInvokeRunsJobs(): void
    {
        $jobs      = [
            new Job('aaa'),
            new Job('bbb'),
            new Job('ccc'),
        ];
        $processed = array_map(
            static fn(Job $job): Job => $job->withResult($job->getPayload() . 'processed'),
            $jobs
        );
        $completed = [];

        $this->jobProvider->method('getIterator')
            ->willReturn(new ArrayIterator($jobs));
        $this->client->expects(self::exactly(3))
            ->method('connect')
            ->with(self::SOCK);
        $this->client->method('send')
            ->willReturn(123);
        $this->client->method('recv')
            ->willReturnCallback(static function () use (&$processed): string {
                $serialized = serialize(array_shift($processed));
                return pack('N', strlen($serialized)) . $serialized;
            });
        $this->completionHandler->method('complete')
            ->willReturnCallback(static function (Job $job) use (&$completed) {
                $completed[] = $job;
            });

        run($this->runner);

        self::assertSame(0, $this->errorCount->get());
    }

    public function testInvokeRetriesSocketSend(): void
    {
        $this->jobProvider->method('getIterator')
            ->willReturn(new ArrayIterator([new Job('a')]));
        $this->client->expects(self::exactly(2))
            ->method('send')
            ->willReturnOnConsecutiveCalls(false, 123);
        $serialized = serialize(new Job('b'));
        $this->client->expects(self::once())
            ->method('recv')
            ->willReturn(pack('N', strlen($serialized)) . $serialized);

        run($this->runner);

        self::assertSame(0, $this->errorCount->get());
    }

    public function testInvokeRetriesSocketReceive(): void
    {
        $this->jobProvider->method('getIterator')
            ->willReturn(new ArrayIterator([new Job('a')]));
        $this->client->method('send')
            ->willReturn(123);
        $serialized = serialize(new Job('b'));
        $this->client->expects(self::exactly(2))
            ->method('recv')
            ->willReturnOnConsecutiveCalls(false, pack('N', strlen($serialized)) . $serialized);
        $this->client->errCode = SOCKET_EAGAIN;

        run($this->runner);

        self::assertSame(0, $this->errorCount->get());
    }

    public function testInvokeWithExceptionIncrementsErrorsAndLogs(): void
    {
        $exception = new Exception("Iterator failed");
        $this->jobProvider->method('getIterator')
            ->willThrowException($exception);

        $process = new Process($this->runner, true, 0, true);
        $started = $process->start();
        self::assertGreaterThan(1, $started);
        Process::wait();
        $error = $process->read(128);
        self::assertStringContainsString("Exception thrown in JobRunner", (string) $error);

        self::assertSame(1, $this->errorCount->get());
    }
}
