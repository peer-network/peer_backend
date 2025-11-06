
BEGIN;

-- Table: advertisements_log
ALTER TABLE advertisements_log 
    ADD COLUMN operationid UUID NULL;

COMMIT;