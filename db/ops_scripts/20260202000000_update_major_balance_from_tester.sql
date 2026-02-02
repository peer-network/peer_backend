-- Total Users: 113 - For those users, we are adding transactions records.
-- Total Balances of 113: -36123.0592191489 (Excluding Tester 01, 02, 03, Alpha a/c, Jakob, LP)
-- IMPORTANT: make sure that tester02 - `6520ac47-f262-4f7e-b643-9dc5ee4cfa82` has more than `36123.0592191489` wallet balance

BEGIN;

-- CREATE EXTENSION IF NOT EXISTS "pgcrypto";

WITH
    balances AS (
        SELECT
            u.uid,
            u.username,
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
    ),
    recipients AS (
        SELECT
            b.uid AS recipientid,
            ABS(b.transaction_balance_diff) AS tokenamount,
            b.createdat,
            SUM(ABS(b.transaction_balance_diff)) OVER (ORDER BY b.createdat, b.uid) AS cumulative_amount
        FROM
            balances b
        WHERE
            b.transaction_balance_diff < 0
            AND b.uid NOT IN (
                '82079cb8-0a3a-11ef-b7f2-e89c25791d89', -- Jakob
                '3f6d55c1-9731-4f28-8b85-5a30cd7c5cc4', -- LP
                'b9e94945-abd7-46a5-8c92-59037f1d73bf', -- tester 01
                '6520ac47-f262-4f7e-b643-9dc5ee4cfa82', -- tester 02
                'dbe72768-0d47-4d29-99e7-b6ec4eadfaa3', -- tester 03
                '2736677b-57b8-4ee2-87fe-24ed975e55a6' -- Alpha a/c
            )
    ),
    total_allocated AS (
        SELECT
            COALESCE(SUM(tokenamount), 0) AS total_amount
        FROM
            recipients
    ),
    inserted AS (
        INSERT INTO transactions (
            transactionid,
            operationid,
            transactiontype,
            senderid,
            recipientid,
            tokenamount,
            transferaction,
            message,
            createdat,
            transactioncategory
        )
        SELECT
            gen_random_uuid(),
            gen_random_uuid(),
            'transferSenderToRecipient',
            '6520ac47-f262-4f7e-b643-9dc5ee4cfa82'::uuid,
            r.recipientid,
            r.tokenamount,
            'CREDIT',
            'balance adjustment',
            CURRENT_TIMESTAMP,
            'P2P_TRANSFER'
        FROM
            recipients r
        WHERE
            r.tokenamount > 0
        RETURNING
            tokenamount
    )
UPDATE wallett w
SET
    liquidity = w.liquidity - t.total_amount,
    updatedat = CURRENT_TIMESTAMP
FROM
    total_allocated t
WHERE
    w.userid = '6520ac47-f262-4f7e-b643-9dc5ee4cfa82'::uuid;

UPDATE users_info ui
SET
    liquidity = w.liquidity,
    updatedat = CURRENT_TIMESTAMP
FROM
    wallett w
WHERE
    ui.userid = w.userid
    AND w.userid = '6520ac47-f262-4f7e-b643-9dc5ee4cfa82'::uuid;

COMMIT;
