-- 007: Abrechenbare Leistungen je Projekt (Zeiten/Positionen). Werden in ein
-- Angebot oder eine Rechnung übernommen und dann als abgerechnet markiert.
CREATE TABLE project_item (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id       INTEGER NOT NULL REFERENCES project(id) ON DELETE CASCADE,
    item_date        TEXT NOT NULL DEFAULT (date('now')),
    description      TEXT NOT NULL DEFAULT '',
    quantity         REAL NOT NULL DEFAULT 1,
    unit             TEXT NOT NULL DEFAULT 'Std',
    unit_price_cents INTEGER NOT NULL DEFAULT 0,
    billed_doc_type  TEXT,            -- 'quote' | 'invoice' | NULL (noch offen)
    billed_doc_id    INTEGER,
    created_at       TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_project_item_project ON project_item (project_id);
