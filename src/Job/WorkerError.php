<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Job;

use Throwable;

final readonly class WorkerError
{
    private function __construct(public string $message, public int $code)
    {
    }

    public static function fromThrowable(Throwable $throwable): self
    {
        return new self($throwable->getMessage(), (int) $throwable->getCode());
    }
}
