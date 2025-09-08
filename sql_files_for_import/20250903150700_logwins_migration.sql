DO $$
BEGIN
    -- Prüfen, ob die Spalte 'migrated' bereits existiert
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns 
        WHERE table_name = 'logwins' 
          AND column_name = 'migrated'
    ) THEN
        -- Spalte hinzufügen
        ALTER TABLE logwins 
            ADD COLUMN migrated INT DEFAULT 1;

        -- Alle Zeilen auf unmigrated setzen
        UPDATE logwins 
        SET migrated = 0;

        -- Alpha User ausschließen
        UPDATE logwins 
        SET migrated = 1 
        WHERE fromid = '2736677b-57b8-4ee2-87fe-24ed975e55a6';
    END IF;
END$$;