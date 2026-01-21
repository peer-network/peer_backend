BEGIN;

CREATE TABLE IF NOT EXISTS notifications (
    notificationid UUID PRIMARY KEY,
    action VARCHAR(64) NOT NULL,
    initiator UUID DEFAULT NULL,
    content_type VARCHAR(32) DEFAULT NULL,
    content_id UUID DEFAULT NULL,
    payload JSONB NOT NULL DEFAULT '{}'::jsonb,
    createdat TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notification_events_actor FOREIGN KEY (initiator) REFERENCES users(uid) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_notification_events_type ON notifications(action);
CREATE INDEX IF NOT EXISTS idx_notification_events_initiator ON notifications(initiator);
CREATE INDEX IF NOT EXISTS idx_notification_events_content_id ON notifications(content_id);

CREATE TABLE IF NOT EXISTS notification_deliveries (
    notificationid UUID NOT NULL,
    receiver UUID NOT NULL,
    channel VARCHAR(16) DEFAULT NULL,
    status INT NOT NULL DEFAULT 0,
    createdat TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notification_deliveries_event FOREIGN KEY (notificationid) REFERENCES notifications(notificationid) ON DELETE CASCADE,
    CONSTRAINT fk_notification_deliveries_receiver FOREIGN KEY (receiver) REFERENCES users(uid) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_notification_deliveries_notificationid ON notification_deliveries(notificationid);
CREATE INDEX IF NOT EXISTS idx_notification_deliveries_receiver ON notification_deliveries(receiver);
CREATE INDEX IF NOT EXISTS idx_notification_deliveries_status ON notification_deliveries(status);

COMMIT;
