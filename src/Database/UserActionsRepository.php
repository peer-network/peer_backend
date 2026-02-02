<?php

declare(strict_types=1);

namespace Fawaz\Database;

interface UserActionsRepository
{
    public function listTodaysInteractions(string $userId): array;
}
