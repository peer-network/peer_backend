DO $$
BEGIN
    -- Add moderation_status_id column to user_reports table
	ALTER TABLE user_reports
	    ADD COLUMN IF NOT EXISTS moderation_status VARCHAR(25) DEFAULT NULL;

END$$;