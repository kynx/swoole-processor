<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor\Job;

use Exception;
use Kynx\Swoole\Processor\Job\WorkerError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkerError::class)]
final class WorkerErrorTest extends TestCase
{
    public function testFromThrowableSetsMessageAndCode(): void
    {
        $exception = new Exception('Test message', 404);
        $actual    = WorkerError::fromThrowable($exception);
        self::assertSame($exception->getMessage(), $actual->message);
        self::assertSame($exception->getCode(), $actual->code);
    }
}
