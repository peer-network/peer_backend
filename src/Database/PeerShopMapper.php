<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\App\Models\Transaction;
use Fawaz\App\Models\TransactionCategory;
use Fawaz\App\Models\TransactionHistoryItem;
use Fawaz\App\Repositories\TransactionRepository;
use Fawaz\App\User;
use PDO;
// Profile enrichment moved to service; no repository needed here
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\TokenCalculations\TokenHelper;
use Fawaz\Utils\PeerLoggerInterface;
use PDOException;
use RuntimeException;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\TokenTransfer\Strategies\TransferStrategy;
use Fawaz\Services\TokenTransfer\Fees\FeePolicyMode;

class PeerShopMapper
{
    use ResponseHelper;
    private string $peerShop;

    public function __construct(protected LiquidityPool $pool)
    {
    }


    /**
     * Loads and validates the liquidity pool and FEE's wallets.
     *
     * @throws RuntimeException if accounts are missing or invalid
     */
    public function initializeWalletAccounts(): string
    {
        $accounts = $this->pool->returnAccounts();
        if (($accounts['status'] ?? '') === 'error') {
            throw new RuntimeException("Failed to load pool accounts");
        }

        $data = $accounts['response'] ?? [];
        if (!isset($data['peerShop'])) {
            throw new RuntimeException("Wallets incomplete");
        }

        $this->peerShop = $data['peerShop'];


        return $this->peerShop;
    }


}
