<?php

use Fawaz\Utils\TokenCalculations\TokenHelper;
use Fawaz\Utils\TokenCalculations\SwapTokenData;

namespace Fawaz\Utils\TokenCalculations;

const PEERFEE=0.02;
const INVTFEE=0.01;
const POOLFEE=0.01;
const BURNFEE=0.01;

class SwapTokenData {
    public function __construct(
    ) {}
}

class SwapTokenCalculation {
    private float $numberoftokensToSwap;
    private float $btcPrice;
    private float $peerTokenBTCPrice;
    private float $btcConstInitialY;
    private float $numberoftokensDebitFromAccount;

    private float $peerTokenEURPrice;
    private float $countAmount;
    private float $requiredAmount;
    
    private SwapTokenData $result;

    public function __construct(array $args) {
        $this->numberoftokensToSwap = $args['numberoftokensToSwap'];
        $this->btcPrice = $args['btcPrice'];
        $this->peerTokenBTCPrice = $args['peerTokenBTCPrice'];
        $this->btcConstInitialY = $args['btcConstInitialY'];

        $this->execute();
    }

    private function execute() {
        $this->peerTokenEURPrice = TokenHelper::calculatePeerTokenEURPrice($this->btcPrice, $this->peerTokenBTCPrice);

        $feeAmount = TokenHelper::roundUpFeeAmount($this->numberoftokensToSwap * POOLFEE);
        $peerAmount = TokenHelper::roundUpFeeAmount($this->numberoftokensToSwap * PEERFEE);
        $burnAmount = TokenHelper::roundUpFeeAmount($this->numberoftokensToSwap * BURNFEE);
        $inviterWin = TokenHelper::roundUpFeeAmount($this->numberoftokensToSwap * INVTFEE);

        $this->countAmount = TokenHelper::calculateSwapTokenSenderRequiredAmountIncludingFees(
            $feeAmount,
            $peerAmount,
            $burnAmount,
            $inviterWin
        );

        $this->requiredAmount = TokenHelper::calculateTokenRequiredAmount(
            $this->numberoftokensToSwap, 
            PEERFEE,
            POOLFEE,
            BURNFEE,
            INVTFEE
        );

        $this->result = new SwapTokenData();
    }

    function data(): SwapTokenData {
        return $this->result;
    }
}