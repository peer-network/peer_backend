<?php

declare(strict_types=1);

namespace Fawaz\Database\Interfaces;

interface ShopOrderPermissionsMapper
{
    /**
     * @param array<string> $allowedAccounts Shop Order Owner and additional accounts (e.g., peer shop)
     */
    public function canAccessShopOrder(string $currentUserId, array $allowedAccounts = []): bool;
}
