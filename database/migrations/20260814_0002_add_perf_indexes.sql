ALTER TABLE content
    ADD INDEX idx_content_status_type (status, type);

ALTER TABLE payment_transactions
    ADD INDEX idx_payment_transactions_created_at (created_at);

ALTER TABLE audit_log
    ADD INDEX idx_audit_log_created_at (created_at);
