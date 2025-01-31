<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Process;

use Closure;
use Kynx\Swoole\Processor\Job\CompletionHandlerInterface;
use Kynx\Swoole\Processor\Job\Job;
use Kynx\Swoole\Processor\Job\JobProviderInterface;
use Swoole\Atomic;
use Swoole\Client;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Server;
use Throwable;

use function assert;
use function pack;
use function serialize;
use function socket_strerror;
use function sprintf;
use function strlen;
use function substr;
use function swoole_error_log;
use function unserialize;
use function usleep;

use const SOCKET_EAGAIN;
use const SWOOLE_LOG_ERROR;
use const SWOOLE_SOCK_SYNC;
use const SWOOLE_SOCK_UNIX_STREAM;

/**
 * @internal
 *
 * @psalm-internal \Kynx\Swoole\Processor
 * @psalm-internal \KynxTest\Swoole\Processor
 */
final readonly class JobRunner
{
    /** @var Closure():Client */
    private Closure $clientFactory;

    /**
     * @param Closure():Client|null $clientFactory
     */
    public function __construct(
        private Server $server,
        private Atomic $errorCount,
        private JobProviderInterface $jobProvider,
        private CompletionHandlerInterface $completionHandler,
        private int $concurrency = 10,
        private float $socketTimeout = 10.0,
        int $maxPacketLength = 2 * 1024 * 1024,
        ?Closure $clientFactory = null
    ) {
        $this->clientFactory = $clientFactory ?? self::getDefaultClientFactory($maxPacketLength);
    }

    /**
     * @return Closure():Client
     */
    public static function getDefaultClientFactory(int $maxPacketLength): Closure
    {
        return static function () use ($maxPacketLength): Client {
            $client = new Client(SWOOLE_SOCK_UNIX_STREAM, SWOOLE_SOCK_SYNC);
            $client->set([
                'open_length_check'     => true,
                'package_body_offset'   => 4,
                'package_length_offset' => 0,
                'package_length_type'   => 'N',
                'package_max_length'    => $maxPacketLength,
            ]);

            return $client;
        };
    }

    public function __invoke(): void
    {
        try {
            $this->iterateJobs();
        } catch (Throwable $throwable) {
            $this->errorCount->add();
            swoole_error_log(SWOOLE_LOG_ERROR, sprintf(
                "Exception thrown in JobRunner: %s %s\n%s",
                $throwable::class,
                $throwable->getMessage(),
                $throwable->getTraceAsString()
            ));
        } finally {
            $this->server->shutdown();
        }
    }

    public function iterateJobs(): void
    {
        $channel    = new Channel($this->concurrency);
        $processing = 0;

        $iterator = $this->jobProvider->getIterator();
        for ($i = 0; $i < $this->concurrency; $i++) {
            if (! $iterator->valid()) {
                break;
            }

            $current = $iterator->current();
            assert($current instanceof Job);
            Coroutine::create($this->getRunner($current, $channel));
            $iterator->next();
            $processing++;
        }

        if ($processing === 0) {
            $this->server->shutdown();
            return;
        }

        do {
            $job = $channel->pop();
            if (! $job instanceof Job) {
                break;
            }

            $this->completionHandler->complete($job);
            $processing--;

            if ($iterator->valid()) {
                $current = $iterator->current();
                assert($current instanceof Job);
                Coroutine::create($this->getRunner($current, $channel));
                $iterator->next();
                $processing++;
            }
        } while ($processing > 0);

        $this->server->shutdown();
    }

    private function getRunner(Job $job, Channel $channel): Closure
    {
        $socket  = $this->server->host;
        $timeout = $this->socketTimeout;
        $client  = ($this->clientFactory)();

        return static function () use ($client, $job, $channel, $socket, $timeout) {
            $client->connect($socket, 0, $timeout);
            $serialized = serialize($job);
            unset($job);

            $attempts = 0;
            while ($client->send(pack('N', strlen($serialized)) . $serialized) === false) {
                $attempts++;
                if ($attempts > 100) {
                    swoole_error_log(
                        SWOOLE_LOG_ERROR,
                        sprintf("Could not send to socket: %s", socket_strerror($client->errCode))
                    );
                    return;
                }
                usleep(1000);
            }

            $attempts    = 0;
            $maxAttempts = $timeout * 1000000 / 1000;
            while (($result = $client->recv()) === false) {
                $attempts++;
                if ($client->errCode === SOCKET_EAGAIN && $attempts < $maxAttempts) {
                    usleep(1000);
                    continue;
                }

                swoole_error_log(
                    SWOOLE_LOG_ERROR,
                    sprintf("Could not receive from socket: %s", socket_strerror($client->errCode))
                );
                return;
            }

            if ($result !== false) {
                $channel->push(unserialize(substr($result, 4)));
            }
        };
    }
}
