-- 013: Lieferanten & Dienstleister (Stammdaten für Ausgaben – Hoster, Händler,
-- Vermieter, Dienstleister …). Ausgaben referenzieren sie weiterhin per Name.
CREATE TABLE vendor (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,
    contact_name  TEXT NOT NULL DEFAULT '',
    email         TEXT NOT NULL DEFAULT '',
    phone         TEXT NOT NULL DEFAULT '',
    website       TEXT NOT NULL DEFAULT '',
    address_line1 TEXT NOT NULL DEFAULT '',
    zip           TEXT NOT NULL DEFAULT '',
    city          TEXT NOT NULL DEFAULT '',
    vat_id        TEXT NOT NULL DEFAULT '',
    note          TEXT NOT NULL DEFAULT '',
    archived_at   TEXT,
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_vendor_name ON vendor (name);
