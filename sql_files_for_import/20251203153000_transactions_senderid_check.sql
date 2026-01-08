BEGIN;

-- Allow transactions.senderid to reference either users.uid or mint_account.accountid
-- Replace FK with a trigger-based validation that accepts either source.

-- Drop old FK if it exists
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.table_constraints tc
        WHERE tc.table_name = 'transactions'
          AND tc.constraint_type = 'FOREIGN KEY'
          AND tc.constraint_name = 'fk_senderid'
    ) THEN
        ALTER TABLE transactions DROP CONSTRAINT fk_senderid;
    END IF;
END $$;

-- Create validation function
CREATE OR REPLACE FUNCTION validate_transactions_senderid()
RETURNS trigger AS $$
DECLARE
    v_exists BOOLEAN;
BEGIN
    IF NEW.senderid IS NULL THEN
        RETURN NEW;
    END IF;

    -- Check in users
    SELECT TRUE INTO v_exists FROM users u WHERE u.uid = NEW.senderid LIMIT 1;
    IF v_exists THEN
        RETURN NEW;
    END IF;

    -- Check in mint_account
    SELECT TRUE INTO v_exists FROM mint_account m WHERE m.accountid = NEW.senderid LIMIT 1;
    IF v_exists THEN
        RETURN NEW;
    END IF;

    RAISE EXCEPTION 'transactions.senderid % does not exist in users or mint_account', NEW.senderid
        USING ERRCODE = '23503';
END;
$$ LANGUAGE plpgsql;

-- Create constraint trigger to enforce the rule
DROP TRIGGER IF EXISTS trg_validate_transactions_senderid ON transactions;
CREATE CONSTRAINT TRIGGER trg_validate_transactions_senderid
AFTER INSERT OR UPDATE OF senderid ON transactions
FOR EACH ROW
EXECUTE FUNCTION validate_transactions_senderid();

COMMIT;

