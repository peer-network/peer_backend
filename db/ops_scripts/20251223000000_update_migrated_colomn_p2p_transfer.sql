-- Spalte hinzufügen
	ALTER TABLE logwins 
		ADD COLUMN migrated INT DEFAULT 1;

	-- Alle Zeilen auf unmigrated setzen
	UPDATE logwins 
	SET migrated = 0;

	-- Alpha User ausschließen
	-- UPDATE logwins 
	-- SET migrated = 1 
	-- WHERE fromid = '{{ALPHA_USER_UUID}}';


	-- Migrated column marked as 1 for records in logwins table
	-- This means that these records are already present in the transactions table
	-- and should not be migrated again
	UPDATE logwins l
	SET migrated = 1
	FROM transactions t
	WHERE t.senderid = l.fromid 
		AND to_char(t.createdat, 'YYYY-MM-DD HH24:MI') = to_char(l.createdat, 'YYYY-MM-DD HH24:MI')
		AND l.whereby = 18;

  -- Do not migrate exisitng paid actions which were already there
	UPDATE logwins l
	SET migrated = 1
	FROM transactions t
	WHERE t.operationid = l.token;

	-- Set collected to 0 for gems that were not converted to logwins
	-- After this set to collected = 0, these gems should be minted again to create new logwins records
    UPDATE gems g
        SET collected = 0
        WHERE g.createdat <= '2025-04-02'
          AND NOT EXISTS (
              SELECT 1
              FROM logwins l
              WHERE g.postid = l.postid
                AND g.fromid = l.fromid
                AND g.whereby = l.whereby
                AND g.userid = l.userid
          );

    -- Update Burn Account
    -- UPDATE logwins l SET userid = '{{NEW_BURN_WALLET_UUID}}' WHERE userid = '{{OLD_BURN_WALLET_UUID}}';
