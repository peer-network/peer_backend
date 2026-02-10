86c84qwfn-Backend-Tokenomics-use-transactions-for-user-balance-validation-during-payment.md

  1. Add WalletMapper.fetchUserWalletBalanceFromTransactions(string $userId): string that sums all rows in transactions:
      - SUM(CASE WHEN senderid = :userId THEN -tokenamount WHEN recipientid = :userId THEN tokenamount ELSE 0 END)
  2. Update balance checks in:
      - WalletService.performPayment
      - PeerShopService.performShopOrder
      - PeerTokenService.transferToken
        to use the new WalletMapper method.
  3. Keep wallett.liquidity updates as-is; new method is only for pre-check balance reads.
  4. Add/adjust tests (or a minimal integration test) to validate the new calculation, including sender/recipient roles and fee rows.