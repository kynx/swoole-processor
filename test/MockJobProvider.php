<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor;

use ArrayIterator;
use Kynx\Swoole\Processor\Job\JobProviderInterface;

final class MockJobProvider implements JobProviderInterface
{
    public array $jobs = [];

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->jobs);
    }
}
