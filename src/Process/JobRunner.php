<?php

declare(strict_types=1);

namespace Kynx\Swoole\Processor\Process;

use Closure;
use Kynx\Swoole\Processor\Job\CompletionHandlerInterface;
use Kynx\Swoole\Processor\Job\Job;
use Kynx\Swoole\Processor\Job\JobProviderInterface;
use Swoole\Client;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Server;

use function pack;
use function serialize;
use function strlen;
use function substr;
use function unserialize;

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
        private JobProviderInterface $jobProvider,
        private CompletionHandlerInterface $completionHandler,
        private int $concurrency = 10,
        ?Closure $clientFactory = null
    ) {
        $this->clientFactory = $clientFactory ?? self::getDefaultClientFactory();
    }

    /**
     * @return Closure():Client
     */
    public static function getDefaultClientFactory(): Closure
    {
        return static function (): Client {
            $client = new Client(SWOOLE_SOCK_UNIX_STREAM, SWOOLE_SOCK_SYNC);
            $client->set([
                'open_length_check'     => true,
                'package_length_type'   => 'N',
                'package_length_offset' => 0,
                'package_body_offset'   => 4,
                'package_max_length'    => 2 * 1024 * 1024,
            ]);

            return $client;
        };
    }

    public function __invoke(): void
    {
        $channel    = new Channel($this->concurrency);
        $processing = 0;

        $iterator = $this->jobProvider->getIterator();
        for ($i = 0; $i < $this->concurrency; $i++) {
            if (! $iterator->valid()) {
                break;
            }

            Coroutine::create($this->getRunner($iterator->current(), $channel));
            $iterator->next();
            $processing++;
        }

        do {
            $job = $channel->pop();
            if (! $job instanceof Job) {
                break;
            }

            $this->completionHandler->complete($job);
            $processing--;

            if ($iterator->valid()) {
                Coroutine::create($this->getRunner($iterator->current(), $channel));
                $iterator->next();
                $processing++;
            }
        } while ($processing > 0);

        $this->server->shutdown();
    }

    private function getRunner(Job $job, Channel $channel): Closure
    {
        $socket = $this->server->host;
        $client = ($this->clientFactory)();

        return static function () use ($client, $job, $channel, $socket) {
            $client->connect($socket);
            $serialized = serialize($job);
            unset($job);
            $client->send(pack('N', strlen($serialized)) . $serialized);

            $result = $client->recv();
            /** @var mixed $data */
            $data = $result === false ? null : unserialize(substr($result, 4));
            $channel->push($data);
        };
    }
}
