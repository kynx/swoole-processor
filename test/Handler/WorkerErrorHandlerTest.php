<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor\Handler;

use Kynx\Swoole\Processor\Handler\WorkerErrorHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swoole\Atomic;

#[CoversClass(WorkerErrorHandler::class)]
final class WorkerErrorHandlerTest extends TestCase
{
    public function testInvokeAddsErrorCount(): void
    {
        $errorCount = new Atomic();
        $handler    = new WorkerErrorHandler($errorCount);

        $handler();

        self::assertSame(1, $errorCount->get());
    }
}
