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


-- group by 
-- 	transaction_balance_diff
-- ORDER BY
-- 	transaction_balance_diff asc;
-- createdat ASC;