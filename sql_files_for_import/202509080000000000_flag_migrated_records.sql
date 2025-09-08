BEGIN;

-- Migrated column marked as 1 for records in logwins table
-- This means that these records are already present in the transactions table
-- and should not be migrated again
    UPDATE logwins l
    SET migrated = 1
    FROM transactions t
    WHERE t.senderid = l.fromid 
        AND to_char(t.createdat, 'YYYY-MM-DD HH24:MI') = to_char(l.createdat, 'YYYY-MM-DD HH24:MI')
        AND l.whereby = 18

COMMIT;
