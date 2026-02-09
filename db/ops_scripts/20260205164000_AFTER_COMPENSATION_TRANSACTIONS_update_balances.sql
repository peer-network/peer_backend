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
join users u on b.userid = u.uid
WHERE
    w.userid = b.userid and
	w.userid not in (
		'85d5f836-b1f5-4c4e-9381-1b058e13d000',
		'3f6d55c1-9731-4f28-8b85-5a30cd7c5cc4',	
		'2736677b-57b8-4ee2-87fe-24ed975e55a6',
		'82079cb8-0a3a-11ef-b7f2-e89c25791d89',
		'b9e94945-abd7-46a5-8c92-59037f1d73bf',
		'6520ac47-f262-4f7e-b643-9dc5ee4cfa82',
		'dbe72768-0d47-4d29-99e7-b6ec4eadfaa3'
	)
	and u.status != 6 -- EXCLUDE DELETED USERS
    AND b.transaction_balance_diff <> 0 RETURNING w.userid,
    b.old_liquidity,
    w.liquidity AS new_liquidity,
    b.transaction_balance_diff;

UPDATE public.users_info ui
SET
    liquidity = w.liquidity
FROM
    public.wallett w
WHERE
    ui.userid = w.userid;

COMMIT;