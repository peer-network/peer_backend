-- creates list of users with balance difference for compensation transactions. excluding deleted and system accounts.

WITH balances AS (
	SELECT
		u.uid,
		u.username,
		u.email,
		w.liquidity,
		(
			SELECT
				sum(numbers)
			FROM
				logwins l
				
			WHERE
				l.userid = u.uid
		) AS logwin_total,
		(
			SELECT
				COALESCE(
					SUM(
						CASE
							WHEN t.recipientid = u.uid THEN t.tokenamount
						END
					),
					0
				) - COALESCE(
					SUM(
						CASE
							WHEN t.senderid = u.uid THEN ABS(t.tokenamount)
						END
					),
					0
				) AS net_balance
			FROM
				transactions t
		) AS transaction_total,
		(
			(
				SELECT
					sum(numbers)
				FROM
					logwins l
				WHERE
					l.userid = u.uid
			) - (
				SELECT
					COALESCE(
						SUM(
							CASE
								WHEN t.recipientid = u.uid THEN t.tokenamount
							END
						),
						0
					) - COALESCE(
						SUM(
							CASE
								WHEN t.senderid = u.uid THEN ABS(t.tokenamount)
							END
						),
						0
					) AS net_balance
				FROM
					transactions t
			)
		) AS logwind_tnx_diff,
		(
			(
				SELECT
					(sum(numbers))
				FROM
					logwins l
				WHERE
					l.userid = u.uid
			) - w.liquidity
		) AS logwins_balance_diff,
		(
			(
				SELECT
					COALESCE(
						SUM(
							CASE
								WHEN t.recipientid = u.uid THEN t.tokenamount
							END
						),
						0
					) - COALESCE(
						SUM(
							CASE
								WHEN t.senderid = u.uid THEN ABS(t.tokenamount)
							END
						),
						0
					) AS net_balance
				FROM
					transactions t
			) - w.liquidity
		) AS transaction_balance_diff,
		u.createdat
	FROM
		users u
		LEFT JOIN wallett w ON u.uid = w.userid
)
SELECT
	-- sum(transaction_balance_diff)
	b.uid,b.username,u.status,u.roles_mask,b.liquidity,b.transaction_balance_diff
FROM
	balances b
join users u on b.uid = u.uid
WHERE
	COALESCE(transaction_balance_diff, 0) <> 0
	and
	b.uid not in (
		'b9e94945-abd7-46a5-8c92-59037f1d73bf',
		'85d5f836-b1f5-4c4e-9381-1b058e13d000',
		'3f6d55c1-9731-4f28-8b85-5a30cd7c5cc4',	
		'2736677b-57b8-4ee2-87fe-24ed975e55a6',
		'82079cb8-0a3a-11ef-b7f2-e89c25791d89',
		'b9e94945-abd7-46a5-8c92-59037f1d73bf',
		'6520ac47-f262-4f7e-b643-9dc5ee4cfa82',
		'dbe72768-0d47-4d29-99e7-b6ec4eadfaa3'
	)
	and u.status != 6

-- group by 
-- 	transaction_balance_diff
-- ORDER BY
-- 	transaction_balance_diff asc;
	-- createdat ASC;