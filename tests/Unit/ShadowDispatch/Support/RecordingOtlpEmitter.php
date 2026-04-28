<?php

declare(strict_types=1);

namespace Tests\Unit\ShadowDispatch\Support;

use Fawaz\ShadowDispatch\SpanEmitter;

final class RecordingOtlpEmitter implements SpanEmitter
{
    /** @var list<array{name:string,attributes:array<string,mixed>,statusCode:int}> */
    public array $emitted = [];

    public bool $emitResult = true;

    public function emit(
        string $name,
        int $startUnixNano,
        int $endUnixNano,
        string $traceIdHex,
        string $spanIdHex,
        array $attributes,
        int $statusCode = 0
    ): bool {
        $this->emitted[] = [
            'name' => $name,
            'attributes' => $attributes,
            'statusCode' => $statusCode,
        ];
        return $this->emitResult;
    }
}
