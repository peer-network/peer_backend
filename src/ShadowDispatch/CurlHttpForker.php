<?php

declare(strict_types=1);

namespace Fawaz\ShadowDispatch;

final class CurlHttpForker implements HttpForker
{
    public function fork(
        string $method,
        string $url,
        array $headers,
        string $body,
        float $timeoutSeconds
    ): array {
        $start = microtime(true);

        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'status' => null,
                'body' => null,
                'latency_ms' => (microtime(true) - $start) * 1000.0,
                'db_write_count' => 0,
            ];
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $responseBody = '';
        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => (int) ($timeoutSeconds * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => 1000,
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_HEADERFUNCTION => static function ($_curl, $headerLine) use (&$responseHeaders) {
                $len = strlen($headerLine);
                $colon = strpos($headerLine, ':');
                if ($colon !== false) {
                    $name = strtolower(trim(substr($headerLine, 0, $colon)));
                    $value = trim(substr($headerLine, $colon + 1));
                    $responseHeaders[$name] = $value;
                }
                return $len;
            },
        ]);

        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $latencyMs = (microtime(true) - $start) * 1000.0;

        if ($resp === false || $errno !== 0) {
            return [
                'status' => null,
                'body' => null,
                'latency_ms' => $latencyMs,
                'db_write_count' => 0,
            ];
        }

        $responseBody = is_string($resp) ? $resp : '';
        $dbWrites = isset($responseHeaders['x-db-write-count'])
            ? max(0, (int) $responseHeaders['x-db-write-count'])
            : 0;

        return [
            'status' => $status,
            'body' => $responseBody,
            'latency_ms' => $latencyMs,
            'db_write_count' => $dbWrites,
        ];
    }
}
