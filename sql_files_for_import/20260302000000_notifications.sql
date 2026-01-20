BEGIN;

CREATE TABLE IF NOT EXISTS notification_events (
    eventid UUID PRIMARY KEY,
    type VARCHAR(64) NOT NULL,
    actor_userid UUID DEFAULT NULL,
    content_type VARCHAR(32) DEFAULT NULL,
    content_id UUID DEFAULT NULL,
    payload JSONB NOT NULL DEFAULT '{}'::jsonb,
    createdat TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_events_actor
        FOREIGN KEY (actor_userid)
        REFERENCES users(uid)
        ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_notification_events_type
    ON notification_events(type);

CREATE INDEX IF NOT EXISTS idx_notification_events_actor_userid
    ON notification_events(actor_userid);

CREATE INDEX IF NOT EXISTS idx_notification_events_content_id
    ON notification_events(content_id);

CREATE TABLE IF NOT EXISTS notification_deliveries (
    deliveryid UUID PRIMARY KEY,
    eventid UUID NOT NULL,
    recipient_userid UUID NOT NULL,
    channel VARCHAR(16) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
    sentat TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL,
    readat TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL,
    createdat TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_deliveries_event
        FOREIGN KEY (eventid)
        REFERENCES notification_events(eventid)
        ON DELETE CASCADE,
    CONSTRAINT fk_notification_deliveries_recipient
        FOREIGN KEY (recipient_userid)
        REFERENCES users(uid)
        ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_notification_deliveries_eventid
    ON notification_deliveries(eventid);

CREATE INDEX IF NOT EXISTS idx_notification_deliveries_recipient
    ON notification_deliveries(recipient_userid);

CREATE INDEX IF NOT EXISTS idx_notification_deliveries_status
    ON notification_deliveries(status);

CREATE TABLE IF NOT EXISTS notification_preferences (
    userid UUID NOT NULL,
    type VARCHAR(64) NOT NULL,
    channel VARCHAR(16) NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    createdat TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_preferences_user
        FOREIGN KEY (userid)
        REFERENCES users(uid)
        ON DELETE CASCADE,
    CONSTRAINT pk_notification_preferences
        PRIMARY KEY (userid, type, channel)
);

CREATE TABLE IF NOT EXISTS notification_templates (
    templateid UUID PRIMARY KEY,
    type VARCHAR(64) NOT NULL,
    channel VARCHAR(16) NOT NULL,
    title_template TEXT DEFAULT NULL,
    body_template TEXT DEFAULT NULL,
    data_template JSONB NOT NULL DEFAULT '{}'::jsonb,
    createdat TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_notification_templates_type_channel
        UNIQUE (type, channel)
);

CREATE INDEX IF NOT EXISTS idx_notification_templates_type
    ON notification_templates(type);

COMMIT;
