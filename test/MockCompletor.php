<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor;

use Kynx\Swoole\Processor\Job\CompletionHandlerInterface;
use Kynx\Swoole\Processor\Job\Job;

use function fopen;
use function fwrite;

final class MockCompletor implements CompletionHandlerInterface
{
    /** @var resource */
    private $handle;

    public function __construct(string $file)
    {
        $this->handle = fopen($file, 'a+');
    }

    public function complete(Job $job): void
    {
        $result = (string) $job->getResult();
        fwrite($this->handle, "complete result: $result\n");
    }
}
