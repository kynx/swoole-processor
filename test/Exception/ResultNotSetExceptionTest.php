<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor\Exception;

use Kynx\Swoole\Processor\Exception\ResultNotSetException;
use Kynx\Swoole\Processor\Job\Job;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResultNotSetException::class)]
final class ResultNotSetExceptionTest extends TestCase
{
    public function testFromJobReturnsException(): void
    {
        $expected = 'Result has not been set for job with payload: string';
        $job      = new Job('foo');
        $actual   = ResultNotSetException::fromJob($job);
        self::assertSame($expected, $actual->getMessage());
    }
}
