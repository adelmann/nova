-- 026: Cloud-Ziele für die Datensicherung. Jedes Ziel ist unabhängig und nur
-- aktiv, wenn die nötigen Felder ausgefüllt sind. Das Backup-ZIP wird nach dem
-- Erstellen zusätzlich dorthin hochgeladen (rein per cURL, keine Abhängigkeiten).

-- WebDAV (Nextcloud/ownCloud u. a.)
ALTER TABLE company_settings ADD COLUMN backup_webdav_url  TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_webdav_user TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_webdav_pass TEXT NOT NULL DEFAULT '';

-- S3-kompatibel (Backblaze B2 / Wasabi / AWS / Hetzner)
ALTER TABLE company_settings ADD COLUMN backup_s3_endpoint TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_s3_region   TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_s3_bucket   TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_s3_key      TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_s3_secret   TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_s3_prefix   TEXT NOT NULL DEFAULT '';

-- FTP / FTPS
ALTER TABLE company_settings ADD COLUMN backup_ftp_host TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_ftp_port INTEGER NOT NULL DEFAULT 21;
ALTER TABLE company_settings ADD COLUMN backup_ftp_user TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_ftp_pass TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_ftp_path TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_ftp_tls  INTEGER NOT NULL DEFAULT 1;

-- Dropbox (Access-Token)
ALTER TABLE company_settings ADD COLUMN backup_dropbox_token TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_dropbox_path  TEXT NOT NULL DEFAULT '';
