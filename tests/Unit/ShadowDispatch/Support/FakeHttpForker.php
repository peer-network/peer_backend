<?php

declare(strict_types=1);

namespace Tests\Unit\ShadowDispatch\Support;

use Fawaz\ShadowDispatch\HttpForker;

final class FakeHttpForker implements HttpForker
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:string,timeout:float}> */
    public array $calls = [];

    /** @var array{status:?int,body:?string,latency_ms:float,db_write_count:int} */
    public array $response = [
        'status' => 200,
        'body' => '{"ok":true}',
        'latency_ms' => 12.0,
        'db_write_count' => 0,
    ];

    public ?\Throwable $throwOnFork = null;

    public function fork(string $method, string $url, array $headers, string $body, float $timeoutSeconds): array
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body') + ['timeout' => $timeoutSeconds];
        if ($this->throwOnFork !== null) {
            throw $this->throwOnFork;
        }
        return $this->response;
    }
}
