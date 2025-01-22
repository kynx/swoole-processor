<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor\Process;

use ArrayIterator;
use Kynx\Swoole\Processor\Job\CompletionHandlerInterface;
use Kynx\Swoole\Processor\Job\Job;
use Kynx\Swoole\Processor\Job\JobProviderInterface;
use Kynx\Swoole\Processor\Process\JobRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Swoole\Client;
use Swoole\Server;

use function array_map;
use function array_shift;
use function pack;
use function serialize;
use function strlen;
use function Swoole\Coroutine\run;

#[CoversClass(JobRunner::class)]
final class JobRunnerTest extends TestCase
{
    private const SOCK = '/tmp/server.sock';

    private Server&Stub $server;
    private Client&MockObject $client;
    private JobProviderInterface&Stub $jobProvider;
    private CompletionHandlerInterface&MockObject $completionHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server            = self::createStub(Server::class);
        $this->server->host      = self::SOCK;
        $this->client            = $this->createMock(Client::class);
        $this->jobProvider       = self::createStub(JobProviderInterface::class);
        $this->completionHandler = $this->createMock(CompletionHandlerInterface::class);
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
        $this->client->method('recv')
            ->willReturnCallback(static function () use (&$processed): string {
                $serialized = serialize(array_shift($processed));
                return pack('N', strlen($serialized)) . $serialized;
            });
        $this->completionHandler->method('complete')
            ->willReturnCallback(static function (Job $job) use (&$completed) {
                $completed[] = $job;
            });

        $runner = new JobRunner(
            $this->server,
            $this->jobProvider,
            $this->completionHandler,
            2,
            64 * 1024,
            fn(): Client => $this->client
        );

        run($runner);
    }
}
