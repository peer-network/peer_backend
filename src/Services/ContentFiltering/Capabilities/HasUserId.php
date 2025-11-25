<?php

declare(strict_types=1);

namespace Fawaz\Services\ContentFiltering\Capabilities;

interface HasUserId
{
    public function getUserId(): string;
}
