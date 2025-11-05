
BEGIN;

-- Table: advertisements_info
ALTER TABLE advertisements_info 
    ADD COLUMN operationid UUID NULL;

-- Table: advertisements_log
ALTER TABLE advertisements_log 
    ADD COLUMN operationid UUID NULL;

COMMIT;