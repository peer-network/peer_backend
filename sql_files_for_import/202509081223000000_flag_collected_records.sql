BEGIN;
-- Set collected to 0 for gems that were not converted to logwins
-- After this set to collected = 0, these gems should be minted again to create new logwins records
    UPDATE gems g SET collected = 0 
    WHERE g.createdat <= '2025-04-02 02:13:11.124195' AND NOT EXISTS (
        SELECT * FROM logwins l WHERE g.postid = l.postid AND g.fromid = l.fromid AND g.whereby = l.whereby AND l.createdat >= '2025-04-02 02:13:11.124195'
        AND l.token IS NULL AND g.createdat <= '2025-04-02 02:13:11.124195'
    );
COMMIT;
