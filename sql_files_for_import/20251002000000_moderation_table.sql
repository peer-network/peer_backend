DO $$
BEGIN
	-- Table: moderation_tickets
	CREATE TABLE IF NOT EXISTS moderation_tickets (
		uid UUID PRIMARY KEY,
		status VARCHAR(25) DEFAULT 'open' NOT NULL,
		createdat TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL
	);

	-- Table: moderations
	CREATE TABLE IF NOT EXISTS moderations (
		uid UUID PRIMARY KEY,
		moderationticketid UUID NOT NULL,
		moderatorid UUID NOT NULL,
		status VARCHAR(25) DEFAULT 'waiting_for_review' NOT NULL,
		createdat TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
		CONSTRAINT fk_moderationticketid FOREIGN KEY (moderationticketid) REFERENCES moderation_tickets(uid) ON DELETE CASCADE,
		CONSTRAINT fk_moderatorid FOREIGN KEY (moderatorid) REFERENCES users(uid) ON DELETE CASCADE
	);

	-- Add columns to user_reports
	ALTER TABLE user_reports
		ADD COLUMN IF NOT EXISTS moderationticketid UUID DEFAULT NULL,
		ADD COLUMN IF NOT EXISTS moderationid UUID DEFAULT NULL,
		ADD COLUMN IF NOT EXISTS status VARCHAR(25) DEFAULT 'waiting_for_review' NOT NULL;

	-- Add constraints
	ALTER TABLE user_reports
		ADD CONSTRAINT fk_userreport_moderationticketid FOREIGN KEY (moderationticketid) REFERENCES moderation_tickets(uid) ON DELETE SET NULL,
		ADD CONSTRAINT fk_userreport_moderationid FOREIGN KEY (moderationid) REFERENCES moderations(uid) ON DELETE SET NULL;

	-- Add Total Reports column to posts table
	ALTER TABLE post_info
		ADD COLUMN IF NOT EXISTS totalreports INT DEFAULT 0 NOT NULL;
END$$;