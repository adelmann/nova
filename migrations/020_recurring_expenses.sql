-- 020: Wiederkehrende Ausgaben (Daueraufwendungen wie Miete, Tools, Abos).
-- Profil mit festem Betrag + Intervall; der Cron erzeugt daraus zum
-- Fälligkeitstag eine bezahlte Ausgabe und bucht sie ins Journal (EÜR).
CREATE TABLE recurring_expense (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    title         TEXT NOT NULL DEFAULT '',
    supplier      TEXT NOT NULL DEFAULT '',
    category      TEXT NOT NULL DEFAULT '',
    tax_category  TEXT NOT NULL DEFAULT '',
    amount_cents  INTEGER NOT NULL DEFAULT 0,
    vat_rate      INTEGER NOT NULL DEFAULT 0,
    method        TEXT NOT NULL DEFAULT '',
    interval_unit TEXT NOT NULL DEFAULT 'month',   -- month | quarter | year
    next_date     TEXT NOT NULL,
    note          TEXT NOT NULL DEFAULT '',
    active        INTEGER NOT NULL DEFAULT 1,
    last_run      TEXT,
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_recurring_expense_due ON recurring_expense (active, next_date);
