-- 002: Interne Projekte ermöglichen.
-- customer_id wird optional (interne Projekte haben keinen Kunden); neue Spalte
-- project_type unterscheidet 'customer' und 'internal'.
-- SQLite kann Spalten-Constraints nicht direkt ändern -> Tabelle neu aufbauen.

PRAGMA foreign_keys=OFF;

CREATE TABLE project_new (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id       INTEGER REFERENCES customer(id) ON DELETE SET NULL, -- jetzt optional
    project_type      TEXT NOT NULL DEFAULT 'customer',  -- customer | internal
    name              TEXT NOT NULL,
    status            TEXT NOT NULL DEFAULT 'active',
    hourly_rate_cents INTEGER NOT NULL DEFAULT 0,
    description       TEXT NOT NULL DEFAULT '',
    start_date        TEXT,
    end_date          TEXT,
    created_at        TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at        TEXT NOT NULL DEFAULT (datetime('now'))
);

INSERT INTO project_new (id, customer_id, project_type, name, status, hourly_rate_cents, description, start_date, end_date, created_at, updated_at)
SELECT id, customer_id, 'customer', name, status, hourly_rate_cents, description, start_date, end_date, created_at, updated_at
FROM project;

DROP TABLE project;
ALTER TABLE project_new RENAME TO project;
CREATE INDEX idx_project_customer ON project (customer_id);

PRAGMA foreign_keys=ON;
