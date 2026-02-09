<?php

declare(strict_types=1);

namespace Fawaz\App\DTO;

class MintLogItem
{
    public function __construct(
        public readonly string $gemid,
        public readonly string $transactionid,
        public readonly string $operationid,
        public readonly string $tokenamount,
        public readonly ?string $createdat = null,
    ) {
    }
}
