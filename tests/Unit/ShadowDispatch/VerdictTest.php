<?php

declare(strict_types=1);

namespace Tests\Unit\ShadowDispatch;

use Fawaz\ShadowDispatch\Verdict;
use PHPUnit\Framework\TestCase;

final class VerdictTest extends TestCase
{
    public function testIdenticalJsonIsMatch(): void
    {
        self::assertSame(
            Verdict::MATCH_,
            Verdict::classify(200, '{"a":1,"b":2}', 200, '{"a":1,"b":2}')
        );
    }

    public function testKeyOrderIsIgnoredOnMatch(): void
    {
        self::assertSame(
            Verdict::MATCH_,
            Verdict::classify(200, '{"a":1,"b":2}', 200, '{"b":2,"a":1}')
        );
    }

    public function testWhitespaceIsIgnoredOnMatch(): void
    {
        self::assertSame(
            Verdict::MATCH_,
            Verdict::classify(200, '{"a":1,"b":2}', 200, "{\n  \"a\": 1,\n  \"b\": 2\n}")
        );
    }

    public function testNestedKeyOrderIsIgnoredOnMatch(): void
    {
        self::assertSame(
            Verdict::MATCH_,
            Verdict::classify(
                200,
                '{"outer":{"y":2,"x":1}}',
                200,
                '{"outer":{"x":1,"y":2}}'
            )
        );
    }

    public function testSameShapeDifferentValuesIsValue(): void
    {
        self::assertSame(
            Verdict::VALUE,
            Verdict::classify(200, '{"a":1,"b":2}', 200, '{"a":1,"b":99}')
        );
    }

    public function testDifferentTopLevelKeysIsStructural(): void
    {
        self::assertSame(
            Verdict::STRUCTURAL,
            Verdict::classify(200, '{"a":1,"b":2}', 200, '{"a":1,"c":2}')
        );
    }

    public function testRust404WhilePhpOkIsMissingEndpoint(): void
    {
        self::assertSame(
            Verdict::MISSING_ENDPOINT,
            Verdict::classify(200, '{"ok":true}', 404, '{"error":"not found"}')
        );
    }

    public function testRustNoResponseIsErrorOnlyRust(): void
    {
        // status=null means peergamma never responded (timeout/refused).
        // Should be classified as error-only-rust, not missing-endpoint,
        // because it isn't a deliberate 404.
        self::assertSame(
            Verdict::ERROR_ONLY_RUST,
            Verdict::classify(200, '{"ok":true}', null, null)
        );
    }

    public function testRust5xxWhilePhpOkIsErrorOnlyRust(): void
    {
        self::assertSame(
            Verdict::ERROR_ONLY_RUST,
            Verdict::classify(200, '{"ok":true}', 500, '{"error":"oops"}')
        );
    }

    public function testPhp5xxWhileRustOkIsErrorOnlyPhp(): void
    {
        self::assertSame(
            Verdict::ERROR_ONLY_PHP,
            Verdict::classify(500, '{"error":"oops"}', 200, '{"ok":true}')
        );
    }

    public function testBothErrorWithSameStatusIsMatch(): void
    {
        // When both legs error in the same way, the verdict is "match" —
        // they're consistent, just consistently broken.
        self::assertSame(
            Verdict::MATCH_,
            Verdict::classify(503, 'unavailable', 503, 'unavailable')
        );
    }

    public function testDifferentStatusBothNonErrorIsValue(): void
    {
        // 200 vs 201, both arrays with same keys -> value mismatch.
        self::assertSame(
            Verdict::VALUE,
            Verdict::classify(200, '{"ok":true}', 201, '{"ok":true}')
        );
    }

    public function testNonJsonDifferingIsStructural(): void
    {
        self::assertSame(
            Verdict::STRUCTURAL,
            Verdict::classify(200, 'plain text body', 200, '<html><body>nope</body></html>')
        );
    }

    public function testBodyHashStableAcrossKeyOrder(): void
    {
        $a = Verdict::bodyHash('{"a":1,"b":2}');
        $b = Verdict::bodyHash('{"b":2,"a":1}');
        self::assertSame($a, $b);
    }

    public function testBodyHashStableAcrossWhitespace(): void
    {
        $a = Verdict::bodyHash('{"a":1,"b":2}');
        $b = Verdict::bodyHash("{\n  \"a\": 1,\n  \"b\": 2\n}");
        self::assertSame($a, $b);
    }

    public function testBodyHashChangesOnValueDifference(): void
    {
        $a = Verdict::bodyHash('{"a":1}');
        $b = Verdict::bodyHash('{"a":2}');
        self::assertNotSame($a, $b);
    }

    public function testBodyHashOnNullEqualsBodyHashOnEmpty(): void
    {
        self::assertSame(Verdict::bodyHash(null), Verdict::bodyHash(''));
    }
}
