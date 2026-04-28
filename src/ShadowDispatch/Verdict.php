<?php

declare(strict_types=1);

namespace Fawaz\ShadowDispatch;

/**
 * Classifies a (PHP, peergamma) response pair into one of the verdicts
 * defined in Plan_peer-hybrid-deployment.md §Telemetry.
 *
 * Verdict semantics:
 *
 *  - match            both sides returned the same status and the same
 *                     body once normalised (whitespace-only differences
 *                     and JSON key-order are tolerated).
 *  - structural       both sides returned non-error responses but the
 *                     JSON shape differs at the top level (different keys).
 *  - value            both sides returned non-error responses with the
 *                     same shape but differing values somewhere inside.
 *  - missing-endpoint peergamma returned 404 while PHP did not (or
 *                     peergamma did not respond at all — connection
 *                     refused, timeout, dns).
 *  - error-only-rust  peergamma 5xx (or no response) while PHP succeeded.
 *  - error-only-php   PHP 5xx while peergamma succeeded.
 *
 * The verdict drives the kiosk dashboard and the rollout-gate threshold
 * in D3, so the precedence order is significant — error-only beats
 * missing-endpoint beats value/structural beats match.
 */
final class Verdict
{
    public const MATCH_ = 'match';
    public const STRUCTURAL = 'structural';
    public const VALUE = 'value';
    public const MISSING_ENDPOINT = 'missing-endpoint';
    public const ERROR_ONLY_RUST = 'error-only-rust';
    public const ERROR_ONLY_PHP = 'error-only-php';

    public static function classify(
        int $phpStatus,
        ?string $phpBody,
        ?int $rustStatus,
        ?string $rustBody
    ): string {
        $phpErr = $phpStatus === 0 || $phpStatus >= 500;
        $rustErr = $rustStatus === null || $rustStatus === 0 || $rustStatus >= 500;

        if ($rustErr && !$phpErr) {
            return self::ERROR_ONLY_RUST;
        }
        if ($phpErr && !$rustErr) {
            return self::ERROR_ONLY_PHP;
        }
        // Both errored or neither errored. If both errored we still want
        // a verdict; treat as match if statuses agree, else structural.
        if ($phpErr && $rustErr) {
            return $phpStatus === $rustStatus ? self::MATCH_ : self::STRUCTURAL;
        }

        if ($rustStatus === 404 && $phpStatus !== 404) {
            return self::MISSING_ENDPOINT;
        }

        if ($phpStatus === $rustStatus
            && self::semanticEquals($phpBody, $rustBody)
        ) {
            return self::MATCH_;
        }

        return self::shapeOrValue($phpBody, $rustBody);
    }

    /**
     * SHA-256 of a normalised body. JSON bodies are decoded, sorted by
     * key recursively, and re-encoded so that whitespace and key-order
     * do not produce spurious diffs.
     */
    public static function bodyHash(?string $body): string
    {
        return hash('sha256', self::normalise($body ?? ''));
    }

    private static function semanticEquals(?string $a, ?string $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }
        if ($a === null || $b === null) {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        $aJ = self::tryDecode($a);
        $bJ = self::tryDecode($b);
        if ($aJ['ok'] && $bJ['ok']) {
            return self::canonicalise($aJ['value']) === self::canonicalise($bJ['value']);
        }
        return false;
    }

    private static function shapeOrValue(?string $a, ?string $b): string
    {
        $aJ = self::tryDecode($a);
        $bJ = self::tryDecode($b);
        if (!$aJ['ok'] || !$bJ['ok']) {
            return self::STRUCTURAL;
        }
        $av = $aJ['value'];
        $bv = $bJ['value'];
        if (!is_array($av) || !is_array($bv)) {
            return self::VALUE;
        }
        $aKeys = array_is_list($av) ? ['__list__'] : array_keys($av);
        $bKeys = array_is_list($bv) ? ['__list__'] : array_keys($bv);
        sort($aKeys);
        sort($bKeys);
        if ($aKeys !== $bKeys) {
            return self::STRUCTURAL;
        }
        return self::VALUE;
    }

    private static function normalise(string $body): string
    {
        $decoded = self::tryDecode($body);
        if (!$decoded['ok']) {
            return $body;
        }
        return json_encode(
            self::canonicalise($decoded['value']),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: $body;
    }

    /** Recursively sort object keys so structurally-equal JSON hashes the same. */
    private static function canonicalise(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'canonicalise'], $value);
        }
        ksort($value);
        foreach ($value as $k => $v) {
            $value[$k] = self::canonicalise($v);
        }
        return $value;
    }

    /**
     * @return array{ok: bool, value: mixed}
     */
    private static function tryDecode(?string $s): array
    {
        if ($s === null || $s === '') {
            return ['ok' => false, 'value' => null];
        }
        $value = json_decode($s, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['ok' => false, 'value' => null];
        }
        return ['ok' => true, 'value' => $value];
    }
}
