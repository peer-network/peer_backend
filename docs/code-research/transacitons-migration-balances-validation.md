codex resume 019b9286-ebdf-7270-bff7-bc995aa7479a

# Migration Validation: Bet Wallet Transactions

Source: `db/ops_scripts/20251222000000_migration_validation_bet_wallet_tnx.sql`

## Findings

1. **Uncorrelated transaction subquery** – The `transaction_total` subquery aggregates across the entire `transactions` table because it never references `u.uid`. Every user therefore receives the same net transaction balance, which also contaminates the `logwind_tnx_diff` and `transaction_balance_diff` columns. The subquery must be correlated with `u.uid` (e.g., filtering sums by `recipientid` / `senderid`).
2. **Missing `COALESCE` around aggregates** – `logwin_total` and other sums can return `NULL` when no rows exist, making downstream differences `NULL`. Wrap each aggregate in `COALESCE(..., 0)` so users with no activity still get meaningful values. This applies to the `logwins` sum as well as both transaction sums.
3. **Duplicated net-balance logic** – The same transaction netting logic is duplicated three times. The repetition increases the chance of divergence the next time a change is made. Consider computing the net balance once (e.g., via a CTE or lateral join) and reusing it across projections.
4. **Wallet join yields NULL differences** – Users without wallet rows get `NULL` liquidity, causing `logwins_balance_diff` (and similar expressions) to return `NULL`. Depending on how the result is consumed, it may be clearer to `COALESCE(w.liquidity, 0)` or flag missing wallet rows explicitly.

## Suggested next steps

- Correlate the transaction subquery to `users` and rerun the check to verify balances.
- Normalize the repeated expressions (CTE, lateral, or cross apply) to simplify future maintenance.
- Decide on a convention for handling missing wallet/logwin rows and ensure all balances adhere to it.
