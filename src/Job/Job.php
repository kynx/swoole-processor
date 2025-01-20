<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Job;

use Kynx\Swoole\Processor\Exception\ResultNotSetException;

/**
 * @template TPayload
 * @template TResult
 */
final class Job
{
    /**
     * @var TResult|WorkerError
     * @psalm-readonly-allow-private-mutation
     */
    private mixed $result;

    /**
     * @param TPayload $payload
     */
    public function __construct(private readonly mixed $payload)
    {
    }

    /**
     * @return TPayload
     */
    public function getPayload(): mixed
    {
        return $this->payload;
    }

    /**
     * @return TResult|WorkerError
     */
    public function getResult(): mixed
    {
        if (! isset($this->result)) {
            throw ResultNotSetException::fromJob($this);
        }

        return $this->result;
    }

    /**
     * @param TResult|WorkerError $result
     */
    public function withResult(mixed $result): static
    {
        $new         = clone $this;
        $new->result = $result;

        return $new;
    }
}
