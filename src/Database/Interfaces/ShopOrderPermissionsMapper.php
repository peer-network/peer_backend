<?php

declare(strict_types=1);

namespace Fawaz\Database\Interfaces;

interface ShopOrderPermissionsMapper
{
    /**
     * @param array<string> $allowedAccounts additional accounts (e.g., peer shop, admins)
     */
    public function canAccessShopOrder(string $currentUserId, string $orderUserId, array $allowedAccounts = []): bool;
}
