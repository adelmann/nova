-- 027: Google Drive als Cloud-Backup-Ziel. OAuth2 per Refresh-Token: der Server
-- tauscht das Refresh-Token gegen ein kurzlebiges Access-Token und lädt das ZIP
-- per Drive-API hoch. Optional in einen bestimmten Ordner (folder_id).
ALTER TABLE company_settings ADD COLUMN backup_gdrive_client_id     TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_gdrive_client_secret TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_gdrive_refresh_token TEXT NOT NULL DEFAULT '';
ALTER TABLE company_settings ADD COLUMN backup_gdrive_folder_id     TEXT NOT NULL DEFAULT '';
