-- 019: Online-Zahlung (opt-in). Anbieter-Zugangsdaten in den Einstellungen;
-- pro Rechnung ein Bezahl-Token für den öffentlichen Link; Zahlungen werden
-- über external_ref idempotent gebucht (Webhook-Wiederholungen schaden nicht).
ALTER TABLE company_settings ADD COLUMN stripe_secret_key     TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN stripe_webhook_secret TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN paypal_client_id      TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN paypal_secret         TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN paypal_mode           TEXT NOT NULL DEFAULT 'sandbox'; -- sandbox | live
ALTER TABLE company_settings ADD COLUMN payment_fee_category  TEXT NOT NULL DEFAULT 'Bankgebühren';

ALTER TABLE invoice ADD COLUMN pay_token TEXT NOT NULL DEFAULT '';
ALTER TABLE payment ADD COLUMN external_ref TEXT NOT NULL DEFAULT '';
CREATE INDEX idx_payment_extref ON payment (external_ref);
