-- 009: Kunden archivieren (ausblenden) statt löschen – bewahrt die Historie,
-- blendet „Karteileichen" aber aus Listen und Auswahlfeldern aus.
ALTER TABLE customer ADD COLUMN archived_at TEXT;
