<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor;

use Kynx\Swoole\Processor\Config;
use Kynx\Swoole\Processor\Job\Job;
use Kynx\Swoole\Processor\Processor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_map;
use function assert;
use function file_get_contents;
use function getmypid;
use function glob;
use function is_dir;
use function mkdir;
use function str_repeat;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(Processor::class)]
final class ProcessorTest extends TestCase
{
    private string $tempDir;
    private string $outfile;
    private MockWorker $worker;
    private MockCompletor $completor;
    private MockJobProvider $jobProvider;
    private Processor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/phpunit.' . (string) getmypid();
        if (! is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0770, true);
        }
        $outfile = tempnam($this->tempDir, 'out');
        assert($outfile !== false);
        $this->outfile     = $outfile;
        $this->jobProvider = new MockJobProvider();
        $this->worker      = new MockWorker($this->outfile);
        $this->completor   = new MockCompletor($this->outfile);

        $config          = new Config(3, 2, 0.5, 10, 2 * 1024 * 1024, $this->tempDir . '/processor.sock');
        $this->processor = new Processor(
            $config,
            $this->jobProvider,
            $this->worker,
            $this->completor
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (! is_dir($this->tempDir)) {
            return;
        }

        $files = glob($this->tempDir . '/*');
        if ($files === false) {
            return;
        }
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    #[Group('processor')]
    public function testProcessorRunsJobs(): void
    {
        $expected = [
            'worker 0 init',
            'worker 1 init',
            'worker run: a',
            'worker run: b',
            'worker run: c',
            'worker run: d',
            'worker run: e',
            'worker run: f',
            'complete result: a processed',
            'complete result: b processed',
            'complete result: c processed',
            'complete result: d processed',
            'complete result: e processed',
            'complete result: f processed',
        ];

        $payloads                = ['a', 'b', 'c', 'd', 'e', 'f'];
        $this->jobProvider->jobs = array_map(static fn (string $p): Job => new Job($p), $payloads);
        $result                  = $this->processor->run();
        self::assertTrue($result);

        $actual = (string) file_get_contents($this->outfile);
        foreach ($expected as $line) {
            self::assertStringContainsString($line, $actual);
        }
    }

    #[Group('processor')]
    public function testProcessHandlesLargePayload(): void
    {
        $payloads                = [
            str_repeat('a', 1024 * 796),
            str_repeat('b', 1024 * 796),
            str_repeat('c', 1024 * 796),
            str_repeat('d', 1024 * 796),
            str_repeat('e', 1024 * 796),
        ];
        $expected                = "complete result: " . $payloads[4] . " processed";
        $this->jobProvider->jobs = array_map(static fn (string $p): Job => new Job($p), $payloads);
        $result                  = $this->processor->run();
        self::assertTrue($result);

        $actual = (string) file_get_contents($this->outfile);
        self::assertStringContainsString($expected, $actual);
    }

    #[Group('processor')]
    public function testRunWithCompletionExceptionReturnsFalse(): void
    {
        $this->completor->triggerError = true;

        $this->jobProvider->jobs = [new Job('a')];
        $result                  = $this->processor->run();

        self::assertFalse($result);
    }
}
