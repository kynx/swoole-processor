<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor;

use Kynx\Swoole\Processor\Config;
use Kynx\Swoole\Processor\Exception\MaximumConcurrencyException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function getmypid;
use function sprintf;
use function swoole_cpu_num;
use function sys_get_temp_dir;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $concurrency     = 123;
        $workers         = 12;
        $maxCoroutines   = 3_000;
        $maxPacketLength = 64 * 1024;
        $socket          = '/tmp/test.sock';

        $config = new Config($concurrency, $workers, $maxCoroutines, $maxPacketLength, $socket);

        self::assertSame($concurrency, $config->getConcurrency());
        self::assertSame($workers, $config->getWorkers());
        self::assertSame($maxCoroutines, $config->getMaxCoroutines());
        self::assertSame($maxPacketLength, $config->getMaxPacketLength());
        self::assertSame($socket, $config->getSocket());
    }

    public function testConstructorSetsDefaults(): void
    {
        $concurrency     = 10;
        $workers         = swoole_cpu_num() - 1;
        $maxCoroutines   = 1_000_000;
        $maxPacketLength = 2 * 1024 * 1024;
        $socket          = sprintf(
            '%s/swoole-processor.%s.sock',
            sys_get_temp_dir(),
            getmypid()
        );

        $config = new Config();

        self::assertSame($concurrency, $config->getConcurrency());
        self::assertSame($workers, $config->getWorkers());
        self::assertSame($maxCoroutines, $config->getMaxCoroutines());
        self::assertSame($maxPacketLength, $config->getMaxPacketLength());
        self::assertSame($socket, $config->getSocket());
    }

    public function testConstructorChecksMaximumConcurrency(): void
    {
        self::expectException(MaximumConcurrencyException::class);
        new Config(3, 1, 2);
    }
}
