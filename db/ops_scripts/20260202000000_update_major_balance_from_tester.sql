-- Total Users: 21 - For those users, we are adding transactions records.
-- Total Balances of 21: -11767.4640530164 (Excluding Tester 01, 02, 03, Alpha a/c and Users from Excluding list)

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
            b.transaction_balance_diff < -10
            AND b.uid NOT IN (
                '82079cb8-0a3a-11ef-b7f2-e89c25791d89',
                'c30a8720-ceb0-447f-b9cf-43cad4fcb252',
                '6f2e3706-1700-4342-b9aa-5585a6c5eb4d',
                '727c6362-3222-4f00-bd3c-aba4044e1d3c',
                '8b55fc11-7530-401f-b06c-a7cd14ccb3d2',
                '0c333383-8cce-46c3-a1ed-248d335df738',
                '7fa05fca-c651-4699-90c3-1a21f74c9d16',
                '1ee366ba-0419-4709-a57a-334bbe459723',
                'bea8bcdd-eb19-4caf-9454-4dc13855772b',
                '78fa1d3d-fd25-4032-ab3b-cb7ebf6d6562',
                '736e34d4-f7b3-4c6e-b145-8278152a0381',
                '68bb8781-26f3-4008-a2de-6ce56a074bc0',
                '65e6b357-8b43-4433-85d0-bf1931f4e6ee',
                'caf6d024-1d5f-4495-aca1-c448f49bc8ec',
                'f59d869f-d36d-4c7f-b435-236015f8dbfd',
                'ebbbbb97-ec9d-4f2c-a164-0920c8cea0e1',
                '87e3bab7-ce26-4018-b554-724070cbb424',
                'fd61b870-1118-4fda-91f7-5426520482c1',
                '70880b3e-e558-4b31-8ce5-8b6d14d6d914',
                '5e6b30f9-76a9-4a8a-8a44-dfc6492fd9bf',
                '19d5c9cd-a176-459a-93e0-dd811c430498',
                'dc36d990-7363-410b-9e45-8355cb50ecac',
                'fb08b055-511a-4f92-8bb4-eb8da9ddf746',
                'de0f6f8a-9ea7-4e3f-8412-d8d78d84c422',
                '9e4d646f-8085-4032-b7ad-d667bbc0fa9b',
                '2d405e68-8d34-449e-8c5c-ddfb3746d800',
                '889c7aff-8660-49fa-97e5-a834b5b12b24',
                '92d313d0-2426-4ea8-807c-6d87eb325002',
                '91db70b2-fb58-4f45-acd5-bcbe006dbe77',
                '111597ef-dde6-4bea-a612-c1b6a7022f50',
                '1b7051bc-b447-4176-be1f-552607fcba6e',
                '58edf7a5-d5a7-4a2f-9ec4-ca2c8262688b',
                '84d2e2dd-6548-4a31-b0ac-f2a4763c1e18',
                '01c4badc-ac5a-489c-ba4f-f23966186c02',
                'f756cc1a-9ee7-42cb-b62d-64e6fed9107b',
                '2c3e5403-ff96-4ee3-baa9-64f5e9cd329c',
                '9f9cf3bb-8794-4240-a94d-0353bd263131',
                '27298b36-86cd-41d9-a67f-7a14d55a8965',
                '114554cf-08d3-4236-b3a3-2137804b96a8',
                '1782406d-bb14-4f8e-bd88-2ed7646fa3df',
                '7eadaa2a-04d0-4062-ae4a-13b6587f57a9',
                'f469bd2f-e04e-4557-92cc-c2e1dc79d9e1',
                '8220af0c-3995-43ac-81f3-5fddae8205c9',
                '455b477b-d198-4f6b-8985-2a3ae254bc9c',
                '9e4363d9-78a7-4e22-9d18-e85e8d609876',
                'e2169294-0d9f-4336-b94f-f7b4c3afa30a',
                '0fd6e331-e947-4bd1-9088-15d7b95196cc',
                'b0108127-8321-4ae3-ac36-d2fa7e40b0cc',
                '685dbc74-41e6-4c2d-b4bf-cb1ae3e34b2b',
                '64625c17-2570-45ca-918d-12cf41033389',
                '47dbca4c-c70d-4095-bf50-f01c94eb502d',
                'f3c4486e-50f2-4496-a534-aef22ec285f9',
                'e88d3f1d-3a19-4a0c-864c-0fd1a894e003',
                '30bbfb91-8da2-4e6f-82f5-1fb61915d883',
                '0bd15b3b-dfc3-4494-8756-b9fe80519cf6',
                'b4256fa3-5922-4e62-8b45-26dd0aa23b32',
                '82f6cb3b-af4f-4700-8d79-9e9a70379b02',
                'cd0a2fcb-0452-4cc8-ab0a-0b08de135e63',
                'ba171f3c-f184-4505-80e5-4c62ba8070e5',
                '0f5e47f8-753d-46c5-ae34-26f26243180c',
                '3f6d55c1-9731-4f28-8b85-5a30cd7c5cc4',
                '66721de8-46e1-4879-aa96-a5e20521fed4',
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
            ' balance compensation',
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
