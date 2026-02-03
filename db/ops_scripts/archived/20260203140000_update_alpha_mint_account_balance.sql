
BEGIN;

WITH
    txn_in AS (
        SELECT
            recipientid AS userid,
            SUM(tokenamount) AS amount_in
        FROM
            transactions
        GROUP BY
            recipientid
    ),
    txn_out AS (
        SELECT
            senderid AS userid,
            SUM(ABS(tokenamount)) AS amount_out
        FROM
            transactions
        GROUP BY
            senderid
    ),
    txn_balance AS (
        SELECT
            COALESCE(i.userid, o.userid) AS userid,
            COALESCE(i.amount_in, 0) - COALESCE(o.amount_out, 0) AS transaction_total
        FROM
            txn_in i
            FULL JOIN txn_out o ON o.userid = i.userid
    ),
    balances AS (
        SELECT
            u.uid AS userid,
            w.liquidity AS old_liquidity,
            COALESCE(t.transaction_total, 0) AS transaction_total,
            COALESCE(t.transaction_total, 0) - COALESCE(w.liquidity, 0) AS transaction_balance_diff
        FROM
            users u
            LEFT JOIN wallett w ON w.userid = u.uid
            LEFT JOIN txn_balance t ON t.userid = u.uid
    )
UPDATE wallett w
SET
    liquidity = b.transaction_total
FROM
    balances b
WHERE
    w.userid = b.userid and 
	w.userid in (
		'2736677b-57b8-4ee2-87fe-24ed975e55a6' -- alpha_mint_acc
	);

UPDATE public.users_info ui
SET
    liquidity = w.liquidity
FROM
    public.wallett w
WHERE
    ui.userid = w.userid and 
	ui.userid in (
		'2736677b-57b8-4ee2-87fe-24ed975e55a6' -- alpha_mint_acc
	);
COMMIT;