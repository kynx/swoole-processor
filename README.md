# kynx/swoole-processor

[![Continuous Integration](https://github.com/kynx/swoole-processor/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/kynx/swoole-processor/actions/workflows/continuous-integration.yml)

Run batch jobs in coroutines across multiple processes.

Based on [Swoole], the processor is ideal for running a large number of IO-intensive operations.

## Install

```commandline
composer require kynx/swoole-processor
```

## Use

Create a `JobProvider` class that returns `Job` objects containing the payload you want to process:

```php
use Kynx\Swoole\Processor\Job\Job;
use Kynx\Swoole\Processor\Job\JobProviderInterface;

class JobProvider implements JobProviderInterface
{
    public function getIterator(): Generator
    {
        foreach (range(0, 10) as $payload) {
            // NOTE: your payload must be serializable!!
            yield new Job($payload);
        }
    }
}
```

Create a `Worker` class to handle the jobs:

```php
use Kynx\Swoole\Processor\Job\WorkerInterface;

class Worker implement WorkerInterface
{
    public function init(int $workerId): void
    {
        // perform any initialisation needed
    }

    public function run(Job $job): Job
    {
        // do work on payload
        echo "Got payload: " . $job->getPayload() . "\n";
        
        // return job with result of process
        return $job->withResult("Processed: " . $job->getPayload());
    }
}
```

If required, create a `CompletionHandler` to do any post-processing:

```php
use Kynx\Swoole\Processor\Job\CompletionHandlerInterface;

class CompletionHandler implements CompletionHandlerInterface
{
    public function complete(Job $job): void
    {
        // mark job as complete
        echo "Completed: " . $job->getResult() . "\n";
    }
}
```

Create a script to run the jobs in parallel:

```php
use Kynx\Swoole\Processor\Config;
use Kynx\Swoole\Processor\Processor;
use Throwable;

try {
    $processor = new Processor(
        new Config(),
        new JobProvider(),
        new Worker(),
        new CompletionHandler()
    );

    // this will block until all jobs are processed
    $processor->start();
} catch (Throwable $throwable) {
    fwrite(STDERR, sprintf("Failed to start processor: %s\n", $throwable->getMessage()));
    exit(1);
}

```

If you don't need to handle completion, omit the fourth parameter.

## How it works

The `JobProvider` runs in its own process, and the resulting jobs are fired at a Swoole server listening on a local
socket. Both it and the the `CompletionHandler` are **blocking**. If the `JobProvider` returns a large number of jobs or
performs time-consuming operations, return a Generator so jobs can be started as soon as possible. The
`CompletionHandler` should be fast.

The server spawns a number of processes to handle the jobs - by default one less than the number of CPU cores detected.
The process runs your `Worker` inside a [coroutine], ensuring IO operations do not block. Because of this, ensure all IO
uses a [Connection Pool], covered in more detail below.

You can control the number of simultaneous jobs the processor will spawn by setting the `concurrency` parameter on the
the `Config` you pass to the constructor. It defaults to 10, with a maximum of `workers` x `maxCoroutines`.

## Caveats

* `Job` payloads and results **must be serializable** and together should be less than 64K when serialized.
* A `Worker` should be **stateless**. If you need share data between workers, use a [Table].
* Uncaught exceptions will crash the process, causing it it re-spawn. For this reason your `Worker::run()` is called
  inside a `try ... catch` block. However the exception is discarded: if you care about where your program is going
  wrong, catch all exceptions inside your `Worker`  and handle them yourself.

[Swoole]: https://wiki.swoole.com
[coroutine]: https://wiki.swoole.com/en/#/start/coroutine
[Connection Pool]: https://wiki.swoole.com/en/#/coroutine/conn_pool
[Table]: https://wiki.swoole.com/en/#/memory/table
