<?php

declare(strict_types=1);

namespace Fawaz\ShadowDispatch;

/**
 * Coordinates one shadow-dispatch fork.
 *
 * Owns:
 *  - the per-request feature-flag and percent-gating decision
 *  - the actual fork to peergamma (delegated to an HttpForker)
 *  - verdict classification + body hashing
 *  - OTLP span emission with the attribute schema documented in
 *    Plan_peer-hybrid-deployment.md §Telemetry
 *  - DLQ writes on fork or OTLP failure
 *
 * The dispatcher is **synchronous** within its own call. Fire-and-forget
 * is enforced by the caller (`public/host.php`) by invoking
 * fastcgi_finish_request() *before* dispatch(): the user-facing response
 * is already on the wire when this runs, so any time spent here only
 * keeps the FPM worker busy, never the client. The bounded queue
 * requirement from the plan is met by the percent-gating roll —
 * SHADOW_DISPATCH_PERCENT bounds the maximum rate at which workers
 * spend extra time on shadow work.
 */
final class ShadowDispatcher
{
    /** @var \Closure(): int */
    private \Closure $rng;

    public function __construct(
        private HttpForker $forker,
        private SpanEmitter $emitter,
        private Dlq $dlq,
        private string $peergammaBaseUrl,
        private bool $flagEnabled,
        private int $percent,
        private float $rustTimeoutSeconds = 5.0,
        ?\Closure $rng = null
    ) {
        // Gate is over [1..10000] so a percent of 0.5 is still expressible
        // in the future; today the range is integer 0..100.
        $this->rng = $rng ?? static fn(): int => random_int(1, 10000);
    }

    public function shouldDispatch(): bool
    {
        if (!$this->flagEnabled) {
            return false;
        }
        if ($this->percent <= 0) {
            return false;
        }
        if ($this->percent >= 100) {
            return true;
        }
        // Divide the [1..10000] roll into 100 bands; band size = percent.
        return ($this->rng)() <= $this->percent * 100;
    }

    public function dispatch(ShadowRequest $req, ShadowResponse $phpResp): void
    {
        if (!$this->shouldDispatch()) {
            return;
        }

        $url = $this->buildUrl($req);
        $forkHeaders = $req->headers;
        // Stamp the correlation id and a flag so peergamma can opt into
        // shadow-only behaviour (e.g. skip side effects that would
        // otherwise duplicate). peergamma-side handling is independent.
        $forkHeaders['x-request-id'] = $req->requestId;
        $forkHeaders['x-shadow-dispatch'] = '1';

        try {
            $rust = $this->forker->fork(
                $req->method,
                $url,
                $forkHeaders,
                $req->body,
                $this->rustTimeoutSeconds
            );
        } catch (\Throwable $e) {
            $this->dlq->write([
                'reason' => 'fork-exception',
                'error' => $e->getMessage(),
                'request_id' => $req->requestId,
                'method' => $req->method,
                'path' => $req->path,
                'graphql_operation_name' => $req->graphqlOperationName,
            ]);
            return;
        }

        $verdict = Verdict::classify(
            $phpResp->status,
            $phpResp->body,
            $rust['status'],
            $rust['body']
        );
        $diffHash = Verdict::bodyHash($phpResp->body) . ':' . Verdict::bodyHash($rust['body'] ?? '');

        $endNano = (int) (microtime(true) * 1e9);
        $emitOk = $this->emitter->emit(
            'shadow_dispatch',
            $req->startUnixNano,
            $endNano,
            OtlpEmitter::newTraceId(),
            OtlpEmitter::newSpanId(),
            [
                'request_id' => $req->requestId,
                'php_latency_ms' => $phpResp->latencyMs,
                'rust_latency_ms' => $rust['latency_ms'],
                'php_status' => $phpResp->status,
                'rust_status' => $rust['status'] ?? 0,
                'response_diff_hash' => $diffHash,
                'diff_verdict' => $verdict,
                'graphql_operation_name' => $req->graphqlOperationName ?? '',
                'db_write_count' => $rust['db_write_count'] ?? 0,
                'php_db_write_count' => $phpResp->dbWriteCount,
                'http.method' => $req->method,
                'http.target' => $req->path,
            ],
            $verdict === Verdict::MATCH_ ? 1 : 2 // OK vs ERROR span status
        );

        if (!$emitOk) {
            $this->dlq->write([
                'reason' => 'otlp-emit-failed',
                'request_id' => $req->requestId,
                'verdict' => $verdict,
                'php_status' => $phpResp->status,
                'rust_status' => $rust['status'] ?? 0,
            ]);
        }
    }

    private function buildUrl(ShadowRequest $req): string
    {
        $base = rtrim($this->peergammaBaseUrl, '/');
        $url = $base . $req->path;
        if ($req->query !== '') {
            $url .= '?' . $req->query;
        }
        return $url;
    }
}
