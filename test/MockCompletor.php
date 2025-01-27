<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor;

use Kynx\Swoole\Processor\Job\CompletionHandlerInterface;
use Kynx\Swoole\Processor\Job\Job;

use function assert;
use function fopen;
use function fwrite;
use function is_resource;

final class MockCompletor implements CompletionHandlerInterface
{
    public bool $triggerError = false;
    /** @var resource */
    private $handle;

    public function __construct(string $file)
    {
        $handle = fopen($file, 'a+');
        assert(is_resource($handle));
        $this->handle = $handle;
    }

    public function complete(Job $job): void
    {
        if ($this->triggerError) {
            die("Completor died");
        }

        $result = (string) $job->getResult();
        fwrite($this->handle, "complete result: $result\n");
    }
}
