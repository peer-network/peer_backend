BEGIN;

ALTER TABLE logwins 
    ADD COLUMN migrated INT DEFAULT 1;
    
-- Update all records to unmigrated
UPDATE logwins SET migrated = 0;

-- Exclude Alpha User
UPDATE logwins SET migrated = 1 WHERE fromid = '2736677b-57b8-4ee2-87fe-24ed975e55a6';

COMMIT;
