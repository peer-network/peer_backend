<?php

declare(strict_types=1);

namespace Tests\Unit\ShadowDispatch;

use Fawaz\ShadowDispatch\Dlq;
use Fawaz\ShadowDispatch\ShadowDispatcher;
use Fawaz\ShadowDispatch\ShadowRequest;
use Fawaz\ShadowDispatch\ShadowResponse;
use Fawaz\ShadowDispatch\Verdict;
use PHPUnit\Framework\TestCase;
use Tests\Unit\ShadowDispatch\Support\FakeHttpForker;
use Tests\Unit\ShadowDispatch\Support\RecordingOtlpEmitter;

final class ShadowDispatcherTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/shadow-dispatch-test-' . bin2hex(random_bytes(4));
        @mkdir($this->tmpDir);
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

    public function testFlagOffSkipsDispatchEntirely(): void
    {
        $forker = new FakeHttpForker();
        $emitter = new RecordingOtlpEmitter();
        $dlq = new Dlq($this->tmpDir);

        $disp = new ShadowDispatcher(
            $forker, $emitter, $dlq, 'http://peergamma',
            flagEnabled: false,
            percent: 100,
        );

        $disp->dispatch($this->reqStub(), $this->phpRespStub());

        self::assertSame([], $forker->calls);
        self::assertSame([], $emitter->emitted);
        self::assertSame(0, $dlq->countEntries());
    }

    public function testPercentZeroSkipsDispatch(): void
    {
        $forker = new FakeHttpForker();
        $emitter = new RecordingOtlpEmitter();
        $disp = new ShadowDispatcher(
            $forker, $emitter, new Dlq($this->tmpDir), 'http://peergamma',
            flagEnabled: true,
            percent: 0,
        );

        $disp->dispatch($this->reqStub(), $this->phpRespStub());

        self::assertSame([], $forker->calls);
    }

    public function testPercent100AlwaysDispatches(): void
    {
        $forker = new FakeHttpForker();
        $emitter = new RecordingOtlpEmitter();
        $disp = new ShadowDispatcher(
            $forker, $emitter, new Dlq($this->tmpDir), 'http://peergamma',
            flagEnabled: true,
            percent: 100,
        );

        $disp->dispatch($this->reqStub(), $this->phpRespStub());
        self::assertCount(1, $forker->calls);
        self::assertCount(1, $emitter->emitted);
    }

    public function testPercentGatingHonoursRng(): void
    {
        $forker = new FakeHttpForker();
        $emitter = new RecordingOtlpEmitter();

        // percent=5 → roll must be <= 500/10000.
        $rolls = [400, 600, 500, 501];
        $rngIdx = 0;
        $rng = static function () use (&$rngIdx, $rolls) {
            return $rolls[$rngIdx++];
        };

        $disp = new ShadowDispatcher(
            $forker, $emitter, new Dlq($this->tmpDir), 'http://peergamma',
            flagEnabled: true,
            percent: 5,
            rng: $rng,
        );

        $disp->dispatch($this->reqStub(), $this->phpRespStub()); // 400 -> dispatch
        $disp->dispatch($this->reqStub(), $this->phpRespStub()); // 600 -> skip
        $disp->dispatch($this->reqStub(), $this->phpRespStub()); // 500 -> dispatch (boundary)
        $disp->dispatch($this->reqStub(), $this->phpRespStub()); // 501 -> skip

        self::assertCount(2, $forker->calls);
        self::assertCount(2, $emitter->emitted);
    }

    public function testEmittedSpanCarriesPlanAttributeSchema(): void
    {
        $forker = new FakeHttpForker();
        $forker->response = [
            'status' => 200,
            'body' => '{"ok":true}',
            'latency_ms' => 9.5,
            'db_write_count' => 3,
        ];
        $emitter = new RecordingOtlpEmitter();

        $disp = new ShadowDispatcher(
            $forker, $emitter, new Dlq($this->tmpDir), 'http://peergamma:8890',
            flagEnabled: true,
            percent: 100,
        );

        $disp->dispatch(
            $this->reqStub(graphqlOperationName: 'GetFeed'),
            new ShadowResponse(status: 200, body: '{"ok":true}', latencyMs: 13.2, dbWriteCount: 1)
        );

        self::assertCount(1, $emitter->emitted);
        $attrs = $emitter->emitted[0]['attributes'];

        // Plan §Telemetry attribute schema.
        self::assertSame('req-abc', $attrs['request_id']);
        self::assertSame(13.2, $attrs['php_latency_ms']);
        self::assertSame(9.5, $attrs['rust_latency_ms']);
        self::assertSame(200, $attrs['php_status']);
        self::assertSame(200, $attrs['rust_status']);
        self::assertStringContainsString(':', $attrs['response_diff_hash']);
        self::assertSame(Verdict::MATCH_, $attrs['diff_verdict']);
        self::assertSame('GetFeed', $attrs['graphql_operation_name']);
        self::assertSame(3, $attrs['db_write_count']);
        self::assertSame(1, $attrs['php_db_write_count']);
    }

    public function testForkExceptionGoesToDlq(): void
    {
        $forker = new FakeHttpForker();
        $forker->throwOnFork = new \RuntimeException('boom');
        $emitter = new RecordingOtlpEmitter();
        $dlq = new Dlq($this->tmpDir);

        $disp = new ShadowDispatcher(
            $forker, $emitter, $dlq, 'http://peergamma',
            flagEnabled: true,
            percent: 100,
        );

        $disp->dispatch($this->reqStub(), $this->phpRespStub());

        self::assertSame([], $emitter->emitted);
        self::assertSame(1, $dlq->countEntries());
    }

    public function testOtlpEmitFailureGoesToDlq(): void
    {
        $forker = new FakeHttpForker();
        $emitter = new RecordingOtlpEmitter();
        $emitter->emitResult = false;
        $dlq = new Dlq($this->tmpDir);

        $disp = new ShadowDispatcher(
            $forker, $emitter, $dlq, 'http://peergamma',
            flagEnabled: true,
            percent: 100,
        );

        $disp->dispatch($this->reqStub(), $this->phpRespStub());

        self::assertCount(1, $emitter->emitted);
        self::assertSame(1, $dlq->countEntries());
    }

    public function testStampsCorrelationAndShadowHeaders(): void
    {
        $forker = new FakeHttpForker();
        $emitter = new RecordingOtlpEmitter();
        $disp = new ShadowDispatcher(
            $forker, $emitter, new Dlq($this->tmpDir), 'http://peergamma',
            flagEnabled: true,
            percent: 100,
        );

        $disp->dispatch($this->reqStub(), $this->phpRespStub());

        self::assertCount(1, $forker->calls);
        $sent = $forker->calls[0];
        self::assertSame('req-abc', $sent['headers']['x-request-id']);
        self::assertSame('1', $sent['headers']['x-shadow-dispatch']);
    }

    public function testBuildsUrlWithQueryString(): void
    {
        $forker = new FakeHttpForker();
        $emitter = new RecordingOtlpEmitter();
        $disp = new ShadowDispatcher(
            $forker, $emitter, new Dlq($this->tmpDir), 'http://peergamma:8890/',
            flagEnabled: true,
            percent: 100,
        );

        $req = new ShadowRequest(
            method: 'GET',
            path: '/graphql',
            query: 'page=2&size=10',
            headers: [],
            body: '',
            requestId: 'req-q',
            startUnixNano: 1,
        );
        $disp->dispatch($req, $this->phpRespStub());

        self::assertSame('http://peergamma:8890/graphql?page=2&size=10', $forker->calls[0]['url']);
    }

    private function reqStub(?string $graphqlOperationName = null): ShadowRequest
    {
        return new ShadowRequest(
            method: 'POST',
            path: '/graphql',
            query: '',
            headers: ['content-type' => 'application/json'],
            body: '{"query":"{ ping }"}',
            requestId: 'req-abc',
            startUnixNano: 1_000_000_000,
            graphqlOperationName: $graphqlOperationName,
        );
    }

    private function phpRespStub(): ShadowResponse
    {
        return new ShadowResponse(status: 200, body: '{"ok":true}', latencyMs: 11.0);
    }
}
