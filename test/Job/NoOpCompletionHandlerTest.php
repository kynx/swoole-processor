<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor\Job;

use Kynx\Swoole\Processor\Job\Job;
use Kynx\Swoole\Processor\Job\NoOpCompletionHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoOpCompletionHandler::class)]
final class NoOpCompletionHandlerTest extends TestCase
{
    public function testCompleteDoesNotAlterJob(): void
    {
        $job     = new Job('foo');
        $handler = new NoOpCompletionHandler();
        $handler->complete($job);
        self::assertSame('foo', $job->getPayload());
    }
}
