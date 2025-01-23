<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Handler;

use Swoole\Atomic;

/**
 * @internal
 *
 * @psalm-internal \Kynx\Swoole\Processor
 * @psalm-internal \KynxTest\Swoole\Processor
 */
final readonly class WorkerErrorHandler
{
    public function __construct(private Atomic $errorCount)
    {
    }

    public function __invoke(): void
    {
        $this->errorCount->add();
    }
}
