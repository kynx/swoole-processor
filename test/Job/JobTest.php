<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor\Job;

use Kynx\Swoole\Processor\Exception\ResultNotSetException;
use Kynx\Swoole\Processor\Job\Job;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Job::class)]
final class JobTest extends TestCase
{
    public function testGetPayloadReturnsConstructorArg(): void
    {
        $expected = 'foo';
        $job      = new Job($expected);
        $actual   = $job->getPayload();
        self::assertSame($expected, $actual);
    }

    public function testGetResultThrowsResultNotSetException(): void
    {
        $job = new Job('foo');
        self::expectException(ResultNotSetException::class);
        $job->getResult();
    }

    public function testWithResultReturnsNewInstance(): void
    {
        $expected = 'bar';
        $job      = new Job('foo');
        $actual   = $job->withResult($expected);
        self::assertNotSame($job, $actual);
        self::assertSame($expected, $actual->getResult());
    }
}
