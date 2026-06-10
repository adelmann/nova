<?php
/** @var array<string,array<string,mixed>> $presets */
/** @var array<int,array<string,mixed>> $profiles */
$presets  = $presets ?? [];
$profiles = $profiles ?? [];
// Daten für das JS-Prefill (Presets + gespeicherte Profile) zusammenstellen.
$jsMap = [];
foreach ($presets as $key => $p) {
    $jsMap['preset:' . $key] = ['delimiter' => $p['delimiter'], 'has_header' => (int) $p['has_header'], 'col_date' => (int) $p['col_date'], 'col_amount' => (int) $p['col_amount'], 'col_purpose' => (int) $p['col_purpose']];
}
foreach ($profiles as $p) {
    $jsMap['profile:' . $p['id']] = ['delimiter' => $p['delimiter'], 'has_header' => (int) $p['has_header'], 'col_date' => (int) $p['col_date'], 'col_amount' => (int) $p['col_amount'], 'col_purpose' => (int) $p['col_purpose']];
}
?>
<div class="panel">
    <h2>Bankumsätze importieren</h2>
    <form method="post" action="/bankimport/vorschau" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field full">
                <label for="csv">CSV-Datei (Kontoauszug)</label>
                <input type="file" id="csv" name="csv" accept=".csv,text/csv" required>
            </div>

            <?php if ($presets !== [] || $profiles !== []): ?>
            <div class="field full">
                <label for="import_profile">Format-Vorlage / gespeichertes Profil</label>
                <select id="import_profile" onchange="novaApplyImportProfile(this.value)">
                    <option value="">– manuell einstellen –</option>
                    <optgroup label="Vorlagen">
                        <?php foreach ($presets as $key => $p): ?>
                            <option value="preset:<?= e($key) ?>"><?= e($p['label']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php if ($profiles !== []): ?>
                        <optgroup label="Eigene Profile">
                            <?php foreach ($profiles as $p): ?>
                                <option value="profile:<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
                <span class="help">Befüllt die Felder unten. Spaltennummern ggf. an den konkreten Export anpassen.</span>
            </div>
            <?php endif; ?>

            <div class="field">
                <label for="delimiter">Trennzeichen</label>
                <select id="delimiter" name="delimiter">
                    <option value=";">Semikolon (;)</option>
                    <option value=",">Komma (,)</option>
                    <option value="&#9;">Tabulator</option>
                </select>
            </div>
            <div class="field">
                <label class="checkbox" style="margin-top:24px;"><input type="checkbox" id="has_header" name="has_header" value="1" checked> Erste Zeile ist Kopfzeile</label>
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

<div class="panel">
    <h2>Mapping als Profil speichern</h2>
    <p class="help" style="margin-top:0;">Übernimmt die aktuell oben eingestellten Werte (Trennzeichen, Kopfzeile, Spalten) als wiederverwendbares Profil.</p>
    <form method="post" action="/bankimport/profil" style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">
        <?= csrf_field() ?>
        <div class="field" style="flex:1; min-width:200px;">
            <label for="profile_name">Profilname</label>
            <input type="text" id="profile_name" name="profile_name" placeholder="z.B. Meine Hausbank">
        </div>
        <input type="hidden" name="delimiter" id="save_delimiter" value=";">
        <input type="hidden" name="has_header" id="save_has_header" value="1">
        <input type="hidden" name="col_date" id="save_col_date" value="1">
        <input type="hidden" name="col_purpose" id="save_col_purpose" value="3">
        <input type="hidden" name="col_amount" id="save_col_amount" value="4">
        <button type="submit" class="btn btn-secondary" onclick="novaSyncSaveProfile()">Profil speichern</button>
    </form>

    <?php if ($profiles !== []): ?>
        <div class="table-wrap" style="box-shadow:none;border:1px solid var(--border); margin-top:14px;">
            <table>
                <thead><tr><th>Profil</th><th>Trennz.</th><th>Datum</th><th>Zweck</th><th>Betrag</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($profiles as $p): ?>
                    <tr>
                        <td><strong><?= e($p['name']) ?></strong></td>
                        <td><?= $p['delimiter'] === "\t" ? 'Tab' : e($p['delimiter']) ?></td>
                        <td><?= (int) $p['col_date'] ?></td>
                        <td><?= (int) $p['col_purpose'] ?></td>
                        <td><?= (int) $p['col_amount'] ?></td>
                        <td style="text-align:right;">
                            <form method="post" action="/bankimport/profil/<?= (int) $p['id'] ?>/loeschen" data-confirm="Profil löschen?" style="display:inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-secondary btn-sm">✕</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<p class="help">Negative Beträge werden als Ausgaben importiert, positive Eingänge offenen Rechnungen zugeordnet. Encoding (UTF-8/ISO-8859-1) wird automatisch erkannt; Auszahlungen von Stripe/PayPal werden als Geldtransit erkannt.</p>

<script>
var novaImportProfiles = <?= json_encode($jsMap, JSON_UNESCAPED_UNICODE) ?>;
function novaApplyImportProfile(key) {
    var p = novaImportProfiles[key];
    if (!p) { return; }
    document.getElementById('delimiter').value = p.delimiter;
    document.getElementById('has_header').checked = p.has_header === 1;
    document.getElementById('col_date').value = p.col_date;
    document.getElementById('col_purpose').value = p.col_purpose;
    document.getElementById('col_amount').value = p.col_amount;
}
function novaSyncSaveProfile() {
    document.getElementById('save_delimiter').value = document.getElementById('delimiter').value;
    document.getElementById('save_has_header').value = document.getElementById('has_header').checked ? '1' : '0';
    document.getElementById('save_col_date').value = document.getElementById('col_date').value;
    document.getElementById('save_col_purpose').value = document.getElementById('col_purpose').value;
    document.getElementById('save_col_amount').value = document.getElementById('col_amount').value;
}
</script>
