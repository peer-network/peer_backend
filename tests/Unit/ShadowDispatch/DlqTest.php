<?php

declare(strict_types=1);

namespace Tests\Unit\ShadowDispatch;

use Fawaz\ShadowDispatch\Dlq;
use PHPUnit\Framework\TestCase;

final class DlqTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/dlq-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->tmpDir);
        }
    }

    public function testWriteCreatesDirectoryAndEntry(): void
    {
        $dlq = new Dlq($this->tmpDir);
        self::assertTrue($dlq->write(['reason' => 'fork-exception', 'request_id' => 'r1']));
        self::assertSame(1, $dlq->countEntries());
        self::assertDirectoryExists($this->tmpDir);
    }

    public function testEntryContainsEnqueuedAtAndPayload(): void
    {
        $dlq = new Dlq($this->tmpDir);
        $dlq->write(['reason' => 'otlp-emit-failed', 'request_id' => 'r2', 'verdict' => 'value']);

        $files = glob($this->tmpDir . '/dlq-*.json') ?: [];
        self::assertCount(1, $files);
        $decoded = json_decode((string) file_get_contents($files[0]), true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('enqueued_at', $decoded);
        self::assertSame('otlp-emit-failed', $decoded['reason']);
        self::assertSame('r2', $decoded['request_id']);
        self::assertSame('value', $decoded['verdict']);
    }

    public function testMaxEntriesIsEnforced(): void
    {
        $dlq = new Dlq($this->tmpDir, maxEntries: 3);
        for ($i = 0; $i < 5; $i++) {
            $dlq->write(['n' => $i]);
        }
        self::assertSame(3, $dlq->countEntries());
    }

    public function testCountEntriesIgnoresUnrelatedFiles(): void
    {
        $dlq = new Dlq($this->tmpDir);
        $dlq->write(['n' => 1]);

        @file_put_contents($this->tmpDir . '/README.txt', 'hi');
        @file_put_contents($this->tmpDir . '/random.json', '{}');

        self::assertSame(1, $dlq->countEntries());
    }
}
