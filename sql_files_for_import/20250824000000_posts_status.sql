BEGIN;

-- Table: Posts
-- Update Posts Set the default value of column status
ALTER TABLE posts 
ALTER COLUMN status SET DEFAULT 0;
-- Update all posts has been set to 10 befor
UPDATE posts SET status = 0 WHERE status = 10;

COMMIT;