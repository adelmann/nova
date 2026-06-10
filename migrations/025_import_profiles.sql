-- 025: Speicherbare Import-Profile für den CSV-Bankimport. Hält Trennzeichen,
-- Kopfzeile und die Spaltenzuordnung je Bankformat, damit wiederkehrende
-- Importe nicht jedes Mal neu konfiguriert werden müssen.
CREATE TABLE import_profile (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL DEFAULT '',
    delimiter   TEXT NOT NULL DEFAULT ';',
    has_header  INTEGER NOT NULL DEFAULT 1,
    col_date    INTEGER NOT NULL DEFAULT 1,
    col_amount  INTEGER NOT NULL DEFAULT 4,
    col_purpose INTEGER NOT NULL DEFAULT 3,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
