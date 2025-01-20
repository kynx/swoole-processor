<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor;

use Kynx\Swoole\Processor\Config;
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
        $concurrency   = 123;
        $workers       = 12;
        $maxCoroutines = 3_000;
        $socket        = '/tmp/test.sock';

        $config = new Config($concurrency, $workers, $maxCoroutines, $socket);

        self::assertSame($concurrency, $config->getConcurrency());
        self::assertSame($workers, $config->getWorkers());
        self::assertSame($maxCoroutines, $config->getMaxCoroutines());
        self::assertSame($socket, $config->getSocket());
    }

    public function testConstructorSetsDefaults(): void
    {
        $concurrency   = 10;
        $workers       = swoole_cpu_num() - 1;
        $maxCoroutines = 1_000_000;
        $socket        = sprintf(
            'unix:///%s/swoole-processor.%s.sock',
            sys_get_temp_dir(),
            getmypid()
        );

        $config = new Config();

        self::assertSame($concurrency, $config->getConcurrency());
        self::assertSame($workers, $config->getWorkers());
        self::assertSame($maxCoroutines, $config->getMaxCoroutines());
        self::assertSame($socket, $config->getSocket());
    }
}
