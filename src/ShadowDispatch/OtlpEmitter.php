<?php

declare(strict_types=1);

namespace Fawaz\ShadowDispatch;

/**
 * Emits a single OTLP HTTP/JSON span describing one shadow dispatch.
 *
 * The cluster's OTel collector listens on 4317 (gRPC) and 4318 (HTTP).
 * The aelix Stack P compose exports OTEL_EXPORTER_OTLP_ENDPOINT pointing
 * at the gRPC port. PHP cannot easily speak gRPC without a binary
 * extension, so we use the HTTP/JSON path and expect the caller to set
 * SHADOW_OTLP_TRACES_ENDPOINT (default http://127.0.0.1:4318/v1/traces).
 *
 * The emitter is intentionally one-shot: build the protobuf-equivalent
 * JSON, POST it, ignore the result. We do not retry. If the collector
 * is down the span is dropped — exactly the same posture the OTel SDK
 * takes by default.
 *
 * Span shape conforms to the OTLP/JSON ResourceSpans schema
 * (opentelemetry.io/docs/specs/otlp/#otlphttp-request).
 */
final class OtlpEmitter implements SpanEmitter
{
    public function __construct(
        private string $endpoint,
        private string $serviceName,
        private string $serviceNamespace,
        private float $timeoutSeconds = 1.0
    ) {}

    /**
     * @param array<string,mixed> $attributes
     */
    public function emit(
        string $name,
        int $startUnixNano,
        int $endUnixNano,
        string $traceIdHex,
        string $spanIdHex,
        array $attributes,
        int $statusCode = 0
    ): bool {
        // OTLP/JSON encodes bytes fields as base64 (protobuf JSON canonical form).
        $traceIdB64 = base64_encode(hex2bin($traceIdHex) ?: '');
        $spanIdB64 = base64_encode(hex2bin($spanIdHex) ?: '');
        $payload = [
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => self::attrs([
                        'service.name' => $this->serviceName,
                        'service.namespace' => $this->serviceNamespace,
                    ]),
                ],
                'scopeSpans' => [[
                    'scope' => ['name' => 'peer-backend.shadow-dispatch', 'version' => '1'],
                    'spans' => [[
                        'traceId' => $traceIdB64,
                        'spanId' => $spanIdB64,
                        'name' => $name,
                        'kind' => 3, // SPAN_KIND_CLIENT
                        'startTimeUnixNano' => (string) $startUnixNano,
                        'endTimeUnixNano' => (string) $endUnixNano,
                        'attributes' => self::attrs($attributes),
                        'status' => ['code' => $statusCode],
                    ]],
                ]],
            ]],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        $ch = curl_init($this->endpoint);
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => (int) ($this->timeoutSeconds * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => 500,
            // Don't let collector failures slow the worker down — we
            // already promised the client a response via fastcgi_finish_request.
            CURLOPT_NOSIGNAL => 1,
        ]);
        $resp = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return $resp !== false && $httpStatus >= 200 && $httpStatus < 300;
    }

    /** Generate a fresh 16-byte trace id (OTel hex form). */
    public static function newTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** Generate a fresh 8-byte span id (OTel hex form). */
    public static function newSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Convert a flat assoc array into OTLP/JSON KeyValue list. Bool/int/
     * double get their typed slot; everything else becomes stringValue.
     *
     * @param array<string,mixed> $attrs
     * @return array<int,array{key:string,value:array<string,mixed>}>
     */
    private static function attrs(array $attrs): array
    {
        $out = [];
        foreach ($attrs as $k => $v) {
            if ($v === null) {
                continue;
            }
            $value = match (true) {
                is_bool($v) => ['boolValue' => $v],
                is_int($v) => ['intValue' => (string) $v],
                is_float($v) => ['doubleValue' => $v],
                is_string($v) => ['stringValue' => $v],
                default => ['stringValue' => (string) $v],
            };
            $out[] = ['key' => (string) $k, 'value' => $value];
        }
        return $out;
    }
}
