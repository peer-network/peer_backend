<?php

namespace Fawaz\Utils\TokenCalculations;

const PEERFEE=0.02;
const INVTFEE=0.01;
const POOLFEE=0.01;
const BURNFEE=0.01;

class SwapTokenCore {
    private float $numberoftokensToSwap;
    private float $btcPrice;
    private float $peerTokenBTCPrice;
    private float $btcConstInitialY;
    private float $numberoftokensDebitFromAccount;

    public function __construct(array $args) {
        $this->numberoftokensToSwap = $args['numberoftokensToSwap'];
        $this->btcPrice = $args['btcPrice'];
        $this->peerTokenBTCPrice = $args['peerTokenBTCPrice'];
        $this->btcConstInitialY = $args['btcConstInitialY'];
    }

    function swapTokensCalculation() {
        $peerTokenEURPrice = TokenHelper::calculatePeerTokenEURPrice($this->btcPrice, $this->peerTokenBTCPrice);

        $feeAmount = TokenHelper::roundUpFeeAmount($this->numberoftokensToSwap * POOLFEE);
        $peerAmount = TokenHelper::roundUpFeeAmount($this->numberoftokensToSwap * PEERFEE);
        $burnAmount = TokenHelper::roundUpFeeAmount($this->numberoftokensToSwap * BURNFEE);
        $inviterWin = TokenHelper::roundUpFeeAmount($this->numberoftokensToSwap * INVTFEE);

        $countAmount = TokenHelper::calculateSwapTokenSenderRequiredAmountIncludingFees(
            $feeAmount,
            $peerAmount,
            $burnAmount,
            $inviterWin
        );

        $requiredAmount = TokenHelper::calculateTokenRequiredAmount(
            $this->numberoftokensToSwap, 
            PEERFEE,
            POOLFEE,
            BURNFEE,
            INVTFEE
        );
    }
}