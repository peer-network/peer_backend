<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Capabilities;

interface HasWalletId
{
    public function getWalletId(): string;
}
