<?php ?>
<div class="panel">
    <h2>Bankumsätze importieren</h2>
    <form method="post" action="/bankimport/vorschau" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field full">
                <label for="csv">CSV-Datei (Kontoauszug)</label>
                <input type="file" id="csv" name="csv" accept=".csv,text/csv" required>
            </div>
            <div class="field">
                <label for="delimiter">Trennzeichen</label>
                <select id="delimiter" name="delimiter">
                    <option value=";">Semikolon (;)</option>
                    <option value=",">Komma (,)</option>
                    <option value="	">Tabulator</option>
                </select>
            </div>
            <div class="field">
                <label class="checkbox" style="margin-top:24px;"><input type="checkbox" name="has_header" value="1" checked> Erste Zeile ist Kopfzeile</label>
            </div>
            <div class="field">
                <label for="col_date">Spalte: Datum (Nr.)</label>
                <input type="number" id="col_date" name="col_date" value="1" min="1">
            </div>
            <div class="field">
                <label for="col_purpose">Spalte: Verwendungszweck (Nr.)</label>
                <input type="number" id="col_purpose" name="col_purpose" value="3" min="1">
            </div>
            <div class="field">
                <label for="col_amount">Spalte: Betrag (Nr.)</label>
                <input type="number" id="col_amount" name="col_amount" value="4" min="1">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Vorschau</button>
        </div>
    </form>
</div>
<p class="help">Negative Beträge werden als Ausgaben importiert. Die Spaltennummern beziehen sich auf die CSV-Spalten (1 = erste Spalte). Encoding (UTF-8/ISO-8859-1) wird automatisch erkannt.</p>
