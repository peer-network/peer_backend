<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\ResponseHelper;
use RuntimeException;

class PeerShopMapper
{
    use ResponseHelper;
    private string $peerShop;

    public function __construct(protected LiquidityPool $pool)
    {
    }


    /**
     * Loads Peer Shop's account.
     *
     * @throws RuntimeException if accounts are missing or invalid
     */
    public function initializeWalletAccounts(): string
    {
        $accounts = $this->pool->returnAccounts();
        if (($accounts['status'] ?? '') === 'error') {
            throw new RuntimeException("Failed to accounts");
        }

        $data = $accounts['response'] ?? [];
        if (!isset($data['peerShop'])) {
            throw new RuntimeException("No account found for Peer Shop");
        }

        $this->peerShop = $data['peerShop'];


        return $this->peerShop;
    }


}
