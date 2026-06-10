-- 016: Leistungskatalog – wiederverwendbare Positionen (Name/Einheit/Preis)
-- zum schnellen Einfügen in Angebote und Rechnungen.
CREATE TABLE catalog_item (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    name             TEXT NOT NULL,
    unit             TEXT NOT NULL DEFAULT 'Stk',
    unit_price_cents INTEGER NOT NULL DEFAULT 0,
    archived_at      TEXT,
    created_at       TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at       TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_catalog_name ON catalog_item (name);
