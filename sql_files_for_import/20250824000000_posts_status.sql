BEGIN;

-- Default ändern
ALTER TABLE posts 
    ALTER COLUMN status SET DEFAULT 0;

-- Nur die bisherigen Werte von 10 auf 0 setzen
UPDATE posts 
    SET status = 0
    WHERE status = 10;

COMMIT;
