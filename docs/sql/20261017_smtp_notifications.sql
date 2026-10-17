-- Back up the database first. Run once using a deployment/admin database account.
-- This additive change preserves all existing inquiries. Old records are not emailed automatically.
ALTER TABLE app_inquiries
    ADD notification_status VARCHAR(16) DEFAULT 'not_sent' NOT NULL,
    ADD notification_attempted_at DATETIME DEFAULT NULL,
    ADD notification_sent_at DATETIME DEFAULT NULL,
    ADD notification_recipients VARCHAR(1400) DEFAULT NULL;
