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

ALTER TABLE transactions RENAME transuniqueid TO operationid;

COMMIT;
