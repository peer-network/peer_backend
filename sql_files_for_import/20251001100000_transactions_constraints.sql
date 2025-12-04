DO $$
BEGIN
  -- Ensure sender and recipient can’t be the same (idempotent)
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint c
    JOIN pg_class t ON t.oid = c.conrelid
    WHERE c.conname = 'chk_not_self_transfer'
      AND t.relname = 'transactions'
  ) THEN
    ALTER TABLE transactions
      ADD CONSTRAINT chk_not_self_transfer CHECK (senderid IS NULL OR senderid <> recipientid);
  END IF;

  -- Enforce amount > 0 (idempotent)
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint c
    JOIN pg_class t ON t.oid = c.conrelid
    WHERE c.conname = 'chk_positive_amount'
      AND t.relname = 'transactions'
  ) THEN
    ALTER TABLE transactions
      ADD CONSTRAINT chk_positive_amount CHECK (tokenamount > 0);
  END IF;
END$$;
