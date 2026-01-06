-- Adjusts wallet and users_info liquidity when the transaction balance mismatch is within 1e-6.
WITH transaction_net AS (
    SELECT
        user_id,
        SUM(incoming) - SUM(outgoing) AS net_balance
    FROM (
        SELECT
            recipientid AS user_id,
            SUM(tokenamount) AS incoming,
            0::numeric AS outgoing
        FROM transactions
        GROUP BY recipientid
        UNION ALL
        SELECT
            senderid AS user_id,
            0::numeric AS incoming,
            SUM(ABS(tokenamount)) AS outgoing
        FROM transactions
        GROUP BY senderid
    ) t
    GROUP BY user_id
),
diffs AS (
    SELECT
        u.uid AS userid,
        COALESCE(tn.net_balance, 0) - COALESCE(w.liquidity, 0) AS transaction_balance_diff
    FROM users u
    LEFT JOIN transaction_net tn ON tn.user_id = u.uid
    LEFT JOIN wallett w ON w.userid = u.uid
    WHERE ABS(COALESCE(tn.net_balance, 0) - COALESCE(w.liquidity, 0)) <= 1e-6
),
updated_wallett AS (
    UPDATE wallett w
    SET
        liquidity = w.liquidity + d.transaction_balance_diff,
        updatedat = NOW()
    FROM diffs d
    WHERE w.userid = d.userid
    RETURNING d.userid, d.transaction_balance_diff
)
UPDATE users_info ui
SET
    liquidity = ui.liquidity + uw.transaction_balance_diff,
    updatedat = NOW()
FROM updated_wallett uw
WHERE ui.userid = uw.userid;
