-- Nova – Initiales Schema (SQLite)
-- Geldbeträge werden grundsätzlich als INTEGER in Cent gespeichert
-- (z.B. 95,00 EUR => 9500), um Rundungsfehler zu vermeiden.

-- ---------------------------------------------------------------------------
-- Benutzer
-- ---------------------------------------------------------------------------
CREATE TABLE user (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    email         TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    name          TEXT    NOT NULL DEFAULT '',
    role          TEXT    NOT NULL DEFAULT 'admin',
    created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ---------------------------------------------------------------------------
-- Unternehmensdaten (genau eine Zeile, id = 1)
-- ---------------------------------------------------------------------------
CREATE TABLE company_settings (
    id                   INTEGER PRIMARY KEY CHECK (id = 1),
    company_name         TEXT NOT NULL DEFAULT '',
    owner_name           TEXT NOT NULL DEFAULT '',
    address_line1        TEXT NOT NULL DEFAULT '',
    address_line2        TEXT NOT NULL DEFAULT '',
    zip                  TEXT NOT NULL DEFAULT '',
    city                 TEXT NOT NULL DEFAULT '',
    country              TEXT NOT NULL DEFAULT 'Deutschland',
    email                TEXT NOT NULL DEFAULT '',
    phone                TEXT NOT NULL DEFAULT '',
    tax_number           TEXT NOT NULL DEFAULT '',
    vat_id               TEXT NOT NULL DEFAULT '',
    bank_name            TEXT NOT NULL DEFAULT '',
    iban                 TEXT NOT NULL DEFAULT '',
    bic                  TEXT NOT NULL DEFAULT '',
    logo_path            TEXT NOT NULL DEFAULT '',
    is_kleinunternehmer  INTEGER NOT NULL DEFAULT 1,   -- §19 UStG
    default_vat_rate     INTEGER NOT NULL DEFAULT 19,  -- Prozent, wenn nicht KU
    default_payment_days INTEGER NOT NULL DEFAULT 14,
    invoice_number_format TEXT NOT NULL DEFAULT 'RE-{YYYY}-{####}',
    quote_number_format   TEXT NOT NULL DEFAULT 'AN-{YYYY}-{####}',
    invoice_footer_text  TEXT NOT NULL DEFAULT '',
    quote_footer_text    TEXT NOT NULL DEFAULT '',
    kleinunternehmer_note TEXT NOT NULL DEFAULT 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.',
    updated_at           TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---------------------------------------------------------------------------
-- Kunden
-- ---------------------------------------------------------------------------
CREATE TABLE customer (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name   TEXT NOT NULL DEFAULT '',
    contact_name   TEXT NOT NULL DEFAULT '',
    address_line1  TEXT NOT NULL DEFAULT '',
    address_line2  TEXT NOT NULL DEFAULT '',
    zip            TEXT NOT NULL DEFAULT '',
    city           TEXT NOT NULL DEFAULT '',
    country        TEXT NOT NULL DEFAULT 'Deutschland',
    email          TEXT NOT NULL DEFAULT '',
    phone          TEXT NOT NULL DEFAULT '',
    vat_id         TEXT NOT NULL DEFAULT '',
    type           TEXT NOT NULL DEFAULT 'business', -- business | private
    notes          TEXT NOT NULL DEFAULT '',
    created_at     TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at     TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_customer_name ON customer (company_name, contact_name);

-- ---------------------------------------------------------------------------
-- Projekte
-- ---------------------------------------------------------------------------
CREATE TABLE project (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id      INTEGER NOT NULL REFERENCES customer(id) ON DELETE RESTRICT,
    name             TEXT NOT NULL,
    status           TEXT NOT NULL DEFAULT 'active', -- active | done | paused | cancelled
    hourly_rate_cents INTEGER NOT NULL DEFAULT 0,
    description      TEXT NOT NULL DEFAULT '',
    start_date       TEXT,
    end_date         TEXT,
    created_at       TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at       TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_project_customer ON project (customer_id);

-- ---------------------------------------------------------------------------
-- Angebote
-- ---------------------------------------------------------------------------
CREATE TABLE quote (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    number              TEXT UNIQUE,            -- erst bei Finalisierung vergeben
    customer_id         INTEGER NOT NULL REFERENCES customer(id) ON DELETE RESTRICT,
    project_id          INTEGER REFERENCES project(id) ON DELETE SET NULL,
    status              TEXT NOT NULL DEFAULT 'draft', -- draft|sent|accepted|rejected
    quote_date          TEXT NOT NULL DEFAULT (date('now')),
    valid_until         TEXT,
    is_kleinunternehmer INTEGER NOT NULL DEFAULT 1,
    vat_rate            INTEGER NOT NULL DEFAULT 0,
    intro_text          TEXT NOT NULL DEFAULT '',
    footer_text         TEXT NOT NULL DEFAULT '',
    net_total_cents     INTEGER NOT NULL DEFAULT 0,
    vat_total_cents     INTEGER NOT NULL DEFAULT 0,
    gross_total_cents   INTEGER NOT NULL DEFAULT 0,
    converted_invoice_id INTEGER REFERENCES invoice(id) ON DELETE SET NULL,
    created_at          TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at          TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_quote_customer ON quote (customer_id);

CREATE TABLE quote_item (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    quote_id         INTEGER NOT NULL REFERENCES quote(id) ON DELETE CASCADE,
    position         INTEGER NOT NULL DEFAULT 0,
    description      TEXT NOT NULL DEFAULT '',
    quantity         REAL NOT NULL DEFAULT 1,
    unit             TEXT NOT NULL DEFAULT 'Stk',
    unit_price_cents INTEGER NOT NULL DEFAULT 0,
    line_total_cents INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_quote_item_quote ON quote_item (quote_id);

-- ---------------------------------------------------------------------------
-- Rechnungen
-- ---------------------------------------------------------------------------
CREATE TABLE invoice (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    number              TEXT UNIQUE,            -- erst bei Finalisierung vergeben
    customer_id         INTEGER NOT NULL REFERENCES customer(id) ON DELETE RESTRICT,
    project_id          INTEGER REFERENCES project(id) ON DELETE SET NULL,
    quote_id            INTEGER REFERENCES quote(id) ON DELETE SET NULL,
    status              TEXT NOT NULL DEFAULT 'draft', -- draft|sent|paid|overdue|cancelled
    is_locked           INTEGER NOT NULL DEFAULT 0,     -- 1 = finalisiert, unveränderbar
    invoice_date        TEXT NOT NULL DEFAULT (date('now')),
    service_date_from   TEXT,
    service_date_to     TEXT,
    due_date            TEXT,
    is_kleinunternehmer INTEGER NOT NULL DEFAULT 1,
    vat_rate            INTEGER NOT NULL DEFAULT 0,
    intro_text          TEXT NOT NULL DEFAULT '',
    footer_text         TEXT NOT NULL DEFAULT '',
    net_total_cents     INTEGER NOT NULL DEFAULT 0,
    vat_total_cents     INTEGER NOT NULL DEFAULT 0,
    gross_total_cents   INTEGER NOT NULL DEFAULT 0,
    paid_total_cents    INTEGER NOT NULL DEFAULT 0,
    cancels_invoice_id  INTEGER REFERENCES invoice(id) ON DELETE SET NULL, -- Storno-Verweis
    pdf_archive_path    TEXT NOT NULL DEFAULT '',
    finalized_at        TEXT,
    created_at          TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at          TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_invoice_customer ON invoice (customer_id);
CREATE INDEX idx_invoice_status ON invoice (status);

CREATE TABLE invoice_item (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id       INTEGER NOT NULL REFERENCES invoice(id) ON DELETE CASCADE,
    position         INTEGER NOT NULL DEFAULT 0,
    description      TEXT NOT NULL DEFAULT '',
    quantity         REAL NOT NULL DEFAULT 1,
    unit             TEXT NOT NULL DEFAULT 'Stk',
    unit_price_cents INTEGER NOT NULL DEFAULT 0,
    vat_rate         INTEGER NOT NULL DEFAULT 0,
    line_total_cents INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_invoice_item_invoice ON invoice_item (invoice_id);

-- ---------------------------------------------------------------------------
-- Zahlungen
-- ---------------------------------------------------------------------------
CREATE TABLE payment (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id    INTEGER NOT NULL REFERENCES invoice(id) ON DELETE RESTRICT,
    paid_on       TEXT NOT NULL DEFAULT (date('now')),
    amount_cents  INTEGER NOT NULL,
    method        TEXT NOT NULL DEFAULT 'Überweisung',
    note          TEXT NOT NULL DEFAULT '',
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_payment_invoice ON payment (invoice_id);

-- ---------------------------------------------------------------------------
-- Ausgaben
-- ---------------------------------------------------------------------------
CREATE TABLE expense (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    expense_date  TEXT NOT NULL DEFAULT (date('now')),
    supplier      TEXT NOT NULL DEFAULT '',
    category      TEXT NOT NULL DEFAULT '',
    tax_category  TEXT NOT NULL DEFAULT '',  -- EÜR-Kategorie
    amount_cents  INTEGER NOT NULL DEFAULT 0,
    vat_rate      INTEGER NOT NULL DEFAULT 0,
    method        TEXT NOT NULL DEFAULT '',
    status        TEXT NOT NULL DEFAULT 'paid', -- open | paid
    note          TEXT NOT NULL DEFAULT '',
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_expense_date ON expense (expense_date);

-- ---------------------------------------------------------------------------
-- Belege
-- ---------------------------------------------------------------------------
CREATE TABLE receipt (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    stored_path   TEXT NOT NULL,               -- relativ zu storage/receipts
    original_name TEXT NOT NULL DEFAULT '',
    mime          TEXT NOT NULL DEFAULT '',
    size_bytes    INTEGER NOT NULL DEFAULT 0,
    sha256        TEXT NOT NULL DEFAULT '',
    type          TEXT NOT NULL DEFAULT 'sonstiges', -- eingangsrechnung|quittung|kontoauszug|sonstiges
    linkable_type TEXT,                         -- 'expense' | 'invoice' | NULL
    linkable_id   INTEGER,
    locked        INTEGER NOT NULL DEFAULT 0,   -- 1 = nach Zuordnung unveränderbar
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_receipt_link ON receipt (linkable_type, linkable_id);

-- ---------------------------------------------------------------------------
-- Buchungsjournal (append-only)
-- ---------------------------------------------------------------------------
CREATE TABLE ledger_entry (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_date     TEXT NOT NULL,
    type           TEXT NOT NULL,               -- income | expense
    reference_type TEXT NOT NULL DEFAULT '',    -- payment | expense | ...
    reference_id   INTEGER,
    category       TEXT NOT NULL DEFAULT '',
    description    TEXT NOT NULL DEFAULT '',
    amount_cents   INTEGER NOT NULL,            -- positiv = Einnahme, negativ = Ausgabe
    receipt_id     INTEGER REFERENCES receipt(id) ON DELETE SET NULL,
    created_at     TEXT NOT NULL DEFAULT (datetime('now')),
    created_by     INTEGER REFERENCES user(id) ON DELETE SET NULL
);
CREATE INDEX idx_ledger_date ON ledger_entry (entry_date);

-- Append-only erzwingen: keine Änderung/Löschung von Journalbuchungen.
CREATE TRIGGER ledger_no_update BEFORE UPDATE ON ledger_entry
BEGIN
    SELECT RAISE(ABORT, 'Buchungsjournal-Einträge sind unveränderbar (GoBD).');
END;
CREATE TRIGGER ledger_no_delete BEFORE DELETE ON ledger_entry
BEGIN
    SELECT RAISE(ABORT, 'Buchungsjournal-Einträge dürfen nicht gelöscht werden (GoBD).');
END;

-- ---------------------------------------------------------------------------
-- Audit-Log (append-only)
-- ---------------------------------------------------------------------------
CREATE TABLE audit_log (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    occurred_at TEXT NOT NULL DEFAULT (datetime('now')),
    user_id     INTEGER REFERENCES user(id) ON DELETE SET NULL,
    user_label  TEXT NOT NULL DEFAULT '',
    action      TEXT NOT NULL,                  -- create | update | finalize | cancel | delete | login ...
    entity_type TEXT NOT NULL DEFAULT '',
    entity_id   INTEGER,
    diff_json   TEXT NOT NULL DEFAULT '{}'      -- {"before":..,"after":..}
);
CREATE INDEX idx_audit_entity ON audit_log (entity_type, entity_id);

CREATE TRIGGER audit_no_update BEFORE UPDATE ON audit_log
BEGIN
    SELECT RAISE(ABORT, 'Audit-Log-Einträge sind unveränderbar.');
END;
CREATE TRIGGER audit_no_delete BEFORE DELETE ON audit_log
BEGIN
    SELECT RAISE(ABORT, 'Audit-Log-Einträge dürfen nicht gelöscht werden.');
END;

-- ---------------------------------------------------------------------------
-- Mahnungen
-- ---------------------------------------------------------------------------
CREATE TABLE reminder (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id   INTEGER NOT NULL REFERENCES invoice(id) ON DELETE RESTRICT,
    level        INTEGER NOT NULL DEFAULT 1,    -- 1 = Zahlungserinnerung, 2..n = Mahnstufen
    reminder_date TEXT NOT NULL DEFAULT (date('now')),
    fee_cents    INTEGER NOT NULL DEFAULT 0,
    pdf_path     TEXT NOT NULL DEFAULT '',
    email_text   TEXT NOT NULL DEFAULT '',
    created_at   TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_reminder_invoice ON reminder (invoice_id);

-- ---------------------------------------------------------------------------
-- Nummernkreise (lückenlose, fortlaufende Vergabe je Jahr und Scope)
-- ---------------------------------------------------------------------------
CREATE TABLE number_sequence (
    scope      TEXT NOT NULL,                   -- invoice | quote
    year       INTEGER NOT NULL,
    last_value INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (scope, year)
);
