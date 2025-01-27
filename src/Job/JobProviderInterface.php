<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Job;

use Iterator;

interface JobProviderInterface
{
    /**
     * @return Iterator<array-key, Job>
     */
    public function getIterator(): Iterator;
}
