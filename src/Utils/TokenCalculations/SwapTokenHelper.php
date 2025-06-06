<?php

namespace Fawaz\Utils\TokenCalculations;

const POOLFEE=0.01;

class SwapTokenHelper
{

    /**
     * Calculates Peer token to BTC.
     *
     * @param float $btcEURPrice Current BTC to EUR price.
     * @param float $peerTokenBTCPrice Current PeerToken to BTC price.
     * @return float|null Calculated PeerToken price in EUR.
     */
    public static function calculateBtc(float $btcConstInitialY, float $lpInitialState, float $numberoftokensToSwap)
    {
        
        $feeAmount = TokenHelper::roundUpFeeAmount($numberoftokensToSwap * POOLFEE);

        // Count LP after Fees calculation
        $lpAccountTokenAfterLPFeeX = TokenHelper::roundUpFeeAmount($lpInitialState + $feeAmount);
        $contsAfterFeesK = TokenHelper::roundUpBTCAmount($lpAccountTokenAfterLPFeeX * $btcConstInitialY);   

        
        // Count LP swap tokens Fees calculation
        $lpAccountTokenAfterSwapX = TokenHelper::roundUpFeeAmount($lpAccountTokenAfterLPFeeX + $numberoftokensToSwap);
        $btcConstNewY = TokenHelper::roundUpBTCAmount($contsAfterFeesK / $lpAccountTokenAfterSwapX);  

        
        // Store BTC Swap transactions in btc_swap_transactions
        // count BTC amount
        $btcAmountToUser = TokenHelper::roundUpBTCAmount($btcConstInitialY - $btcConstNewY);


        return $btcAmountToUser;
    }
    
}
