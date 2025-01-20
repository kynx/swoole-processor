<?php

declare(strict_types=1);

namespace KynxTest\Swoole\Processor;

use Kynx\Swoole\Processor\Config;
use Kynx\Swoole\Processor\Job\Job;
use Kynx\Swoole\Processor\Processor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_map;
use function file_get_contents;
use function getmypid;
use function glob;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(Processor::class)]
final class ProcessorTest extends TestCase
{
    private string $tempDir;
    private string $outfile;
    private MockJobProvider $jobProvider;
    private Processor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/phpunit.' . getmypid();
        if (! is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0770, true);
        }
        $this->outfile     = tempnam($this->tempDir, 'out');
        $this->jobProvider = new MockJobProvider();

        $config          = new Config(3, 2, 10, $this->tempDir . '/processor.sock');
        $this->processor = new Processor(
            $config,
            $this->jobProvider,
            new MockWorker($this->outfile),
            new MockCompletor($this->outfile)
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (! is_dir($this->tempDir)) {
            return;
        }

        foreach (glob($this->tempDir . '/*') as $file) {
            @unlink($file);
        }
    }

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
        $this->processor->start();

        $actual = file_get_contents($this->outfile);
        foreach ($expected as $line) {
            self::assertStringContainsString($line, $actual);
        }
    }
}
