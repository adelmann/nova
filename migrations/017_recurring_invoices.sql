-- 017: Wiederkehrende Rechnungen (Abos/Retainer). Profile + Positionen; der
-- Cron erzeugt daraus zum Fälligkeitstag eine Rechnung (Entwurf oder direkt
-- finalisiert + versendet).
CREATE TABLE recurring_invoice (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id   INTEGER NOT NULL REFERENCES customer(id) ON DELETE RESTRICT,
    title         TEXT NOT NULL DEFAULT '',
    interval_unit TEXT NOT NULL DEFAULT 'month',   -- month | quarter | year
    next_date     TEXT NOT NULL,
    intro_text    TEXT NOT NULL DEFAULT '',
    footer_text   TEXT NOT NULL DEFAULT '',
    auto_send     INTEGER NOT NULL DEFAULT 0,       -- 1 = finalisieren + per E-Mail senden
    active        INTEGER NOT NULL DEFAULT 1,
    last_run      TEXT,
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE recurring_invoice_item (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    recurring_id     INTEGER NOT NULL REFERENCES recurring_invoice(id) ON DELETE CASCADE,
    position         INTEGER NOT NULL DEFAULT 0,
    description      TEXT NOT NULL DEFAULT '',
    quantity         REAL NOT NULL DEFAULT 1,
    unit             TEXT NOT NULL DEFAULT 'Stk',
    unit_price_cents INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_recurring_item ON recurring_invoice_item (recurring_id);
