<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Exception;

use DomainException;
use Kynx\Swoole\Processor\Job\Job;

use function get_debug_type;
use function sprintf;

final class ResultNotSetException extends DomainException implements ExceptionInterface
{
    public static function fromJob(Job $job): self
    {
        return new self(sprintf(
            "Result has not been set for job with payload: %s",
            get_debug_type($job->getPayload())
        ));
    }
}
