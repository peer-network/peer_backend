BEGIN;

-- Change column type
ALTER TABLE transactions 
    ALTER COLUMN tokenamount TYPE NUMERIC(30,10)
    USING tokenamount::NUMERIC(30,10);

-- Set default
ALTER TABLE transactions 
    ALTER COLUMN tokenamount SET DEFAULT 0.0;

-- Set NOT NULL
ALTER TABLE transactions 
    ALTER COLUMN tokenamount SET NOT NULL;

-- 4. Rename column if it exists and hasn't been renamed yet
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name='transactions' 
          AND column_name='operationid'
    ) THEN
        ALTER TABLE transactions RENAME COLUMN transuniqueid TO operationid;
    END IF;
END$$;

COMMIT;
