<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\App\Models\ShopOrder;
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;
use RuntimeException;

class PeerShopMapper
{
    use ResponseHelper;
    private string $peerShop;

    public function __construct(protected LiquidityPool $pool, protected PeerLoggerInterface $logger)
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


    /**
     * Create Shop Order
     */
    public function createShopOrder(ShopOrder $shopOrder): bool
    {
        try{
            $shopOrder = $shopOrder->getArrayCopy();

            ShopOrder::insert([
                'shoporderid' => $shopOrder['shoporderid'],
                'userid' => $shopOrder['userid'],
                'transactionoperationid' => $shopOrder['transactionoperationid'],
                'shopitemid' => $shopOrder['shopitemid'],
                'name' => $shopOrder['orderDetails']['name'],
                'email' => $shopOrder['orderDetails']['email'],
                'addressline1' => $shopOrder['orderDetails']['addressline1'],
                'addressline2' => $shopOrder['orderDetails']['addressline2'],
                'city' => $shopOrder['orderDetails']['city'],
                'zipcode' => $shopOrder['orderDetails']['zipcode'],
                'country' => $shopOrder['orderDetails']['country'],
                'size' => $shopOrder['orderDetails']['shopItemSpecs']['size'],
                'createdat' => $shopOrder['orderDetails']['createdat']
            ]);

            return true;
        } catch (\Exception $e) {
            // $this->transactionManager->rollBack();
            $this->logger->error("Error performing shop order action at PeerShopMapper.createShopOrder: " . $e->getMessage());
            return false;
        }
    }


}
