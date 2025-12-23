BEGIN;

-- Ensure UUID generator is available for transaction IDs.
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Replace the wallet UUIDs below before running.
DO $$
DECLARE
    burn_wallet UUID := '7e0b2d21-d2b0-4af5-8b73-5f8efc04b000';
    company_wallet UUID := '85d5f836-b1f5-4c4e-9381-1b058e13df93';
    total_burn NUMERIC(30,10);
BEGIN
    CREATE TEMP TABLE migrated_tokens (operationid UUID) ON COMMIT DROP;

    WITH candidates AS (
        SELECT *
        FROM logwins
        WHERE migrated = 0
          AND whereby IN (1, 2, 3, 4, 5)
    ),
    candidates_with_parties AS (
        SELECT
            l.*,
            CASE WHEN l.numbers < 0 THEN burn_wallet ELSE l.userid END AS computed_recipientid,
            CASE WHEN l.numbers < 0 THEN l.userid ELSE company_wallet END AS computed_senderid
        FROM candidates l
    ),
    invalid_parties AS (
        SELECT l.token
        FROM candidates_with_parties l
        LEFT JOIN users recipient ON recipient.uid = l.computed_recipientid
        LEFT JOIN users sender ON sender.uid = l.computed_senderid
        WHERE recipient.uid IS NULL
           OR sender.uid IS NULL
    ),
    updated_invalid AS (
        UPDATE logwins
        SET migrated = 2
        WHERE token IN (SELECT token FROM invalid_parties)
        RETURNING token
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
            createdat
        )
        SELECT
            gen_random_uuid(),
            l.token,
            CASE l.whereby
                WHEN 1 THEN 'postViewed'
                WHEN 2 THEN 'postLiked'
                WHEN 3 THEN 'postDisLiked'
                WHEN 4 THEN 'postComment'
                WHEN 5 THEN 'postCreated'
                ELSE ''
            END,
            l.computed_senderid,
            l.computed_recipientid,
            ABS(l.numbers),
            CASE WHEN l.numbers < 0 THEN 'BURN' ELSE 'MINT' END,
            l.createdat
        FROM candidates_with_parties l
        WHERE l.token NOT IN (SELECT token FROM invalid_parties)
        RETURNING operationid
    )
    INSERT INTO migrated_tokens (operationid)
    SELECT operationid FROM inserted;

    UPDATE logwins
    SET migrated = 1
    WHERE token IN (SELECT operationid FROM migrated_tokens);

    SELECT COALESCE(SUM(ABS(numbers)), 0)
    INTO total_burn
    FROM logwins
    WHERE migrated = 1
      AND whereby IN (1, 2, 3, 4, 5)
      AND numbers < 0
      AND token IN (SELECT operationid FROM migrated_tokens);

    IF total_burn > 0 THEN
        INSERT INTO wallett (userid, liquidity, liquiditq, updatedat, createdat)
        VALUES (
            burn_wallet,
            -total_burn,
            (-total_burn * power(2, 96)),
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )
        ON CONFLICT (userid) DO UPDATE
        SET liquidity = wallett.liquidity - total_burn,
            liquiditq = (wallett.liquidity - total_burn) * power(2, 96),
            updatedat = CURRENT_TIMESTAMP;

        UPDATE users_info
        SET liquidity = (SELECT liquidity FROM wallett WHERE userid = burn_wallet),
            updatedat = CURRENT_TIMESTAMP
        WHERE userid = burn_wallet;
    END IF;
END $$;

COMMIT;

