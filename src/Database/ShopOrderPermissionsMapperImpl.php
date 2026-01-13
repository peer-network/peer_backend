<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\Database\Interfaces\ShopOrderPermissionsMapper;

class ShopOrderPermissionsMapperImpl implements ShopOrderPermissionsMapper
{
    public function __construct()
    {
    }

    public function canAccessShopOrder(string $currentUserId, array $allowedAccounts = []): bool
    {
        if ($currentUserId === '') {
            return false;
        }

        $isAllowedAccount = in_array($currentUserId, $allowedAccounts, true);

        if ($isAllowedAccount) {
            return true;
        }

        return false;
    }
}
