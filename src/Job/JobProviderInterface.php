<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Job;

use Iterator;

interface JobProviderInterface
{
    /**
     * @return Iterator<Job>
     */
    public function getIterator(): Iterator;
}
