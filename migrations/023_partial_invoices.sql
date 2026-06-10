-- 023: Abschlags-/Teilrechnungen & Schlussrechnung. invoice_type unterscheidet
-- Standardrechnung, Abschlagsrechnung (Anzahlung) und Schlussrechnung. Eine
-- Schlussrechnung zieht ausgewählte, bereits gestellte Abschläge als negative
-- Positionen ab; deducted_invoice_ids hält die berücksichtigten Abschlag-IDs.
ALTER TABLE invoice ADD COLUMN invoice_type TEXT NOT NULL DEFAULT 'standard';   -- standard | partial | final
ALTER TABLE invoice ADD COLUMN deducted_invoice_ids TEXT NOT NULL DEFAULT '';
