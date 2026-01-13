<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\Database\Interfaces\ShopOrderPermissionsMapper;

class ShopOrderPermissionsMapperImpl implements ShopOrderPermissionsMapper
{
    public function __construct()
    {
    }

    public function canAccessShopOrder(string $currentUserId, string $orderUserId, array $allowedAccounts = []): bool
    {
        if ($currentUserId === '' || $orderUserId === '') {
            return false;
        }

        $isOwner = $currentUserId === $orderUserId;
        $isAllowedAccount = in_array($currentUserId, $allowedAccounts, true);

        if ($isOwner || $isAllowedAccount) {
            return true;
        }

        return false;
    }
}
