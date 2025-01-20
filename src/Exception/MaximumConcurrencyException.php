<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Exception;

use Kynx\Swoole\Processor\Config;
use RuntimeException;

use function sprintf;

final class MaximumConcurrencyException extends RuntimeException implements ExceptionInterface
{
    public static function fromConfig(Config $config): self
    {
        return new self(sprintf(
            "Concurrency %s exceeds maximum of %s",
            $config->getConcurrency(),
            $config->getMaximumConcurrency()
        ));
    }
}
