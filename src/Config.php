<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor;

use Kynx\Swoole\Processor\Exception\MaximumConcurrencyException;

use function getmypid;
use function sprintf;
use function swoole_cpu_num;
use function sys_get_temp_dir;

final readonly class Config
{
    public function __construct(
        private int $concurrency = 10,
        private ?int $workers = null,
        private int $maxCoroutines = 1_000_000,
        private int $maxPacketLength = 2 * 1024 * 1024,
        private ?string $socket = null,
    ) {
        if ($this->concurrency > $this->getMaximumConcurrency()) {
            throw MaximumConcurrencyException::fromConfig($this);
        }
    }

    public function getConcurrency(): int
    {
        return $this->concurrency;
    }

    public function getWorkers(): int
    {
        return $this->workers ?? swoole_cpu_num() - 1;
    }

    public function getMaxCoroutines(): int
    {
        return $this->maxCoroutines;
    }

    public function getMaxPacketLength(): int
    {
        return $this->maxPacketLength;
    }

    public function getSocket(): string
    {
        return $this->socket ?? sprintf(
            '%s/swoole-processor.%s.sock',
            sys_get_temp_dir(),
            getmypid()
        );
    }

    public function getMaximumConcurrency(): int
    {
        return $this->getWorkers() * $this->getMaxCoroutines();
    }
}
