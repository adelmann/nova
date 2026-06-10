<?php
/** @var array<int,int> $years */
/** @var int $year */
?>
<div class="toolbar">
    <form method="get" action="/exporte" style="display:flex; gap:8px; align-items:center;">
        <label class="muted" for="jahr" style="font-size:13px;">Jahr</label>
        <select name="jahr" id="jahr" style="width:auto;" onchange="this.form.submit()">
            <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
        </select>
    </form>
</div>

<div class="panel">
    <h2>CSV-Exporte <?= $year ?></h2>
    <p><a href="/exporte/einnahmen?jahr=<?= $year ?>" class="btn btn-secondary">Einnahmen (CSV)</a>
       <a href="/exporte/ausgaben?jahr=<?= $year ?>" class="btn btn-secondary">Ausgaben (CSV)</a>
       <a href="/exporte/journal?jahr=<?= $year ?>" class="btn btn-secondary">Buchungsjournal (CSV)</a>
       <a href="/exporte/datev?jahr=<?= $year ?>" class="btn btn-secondary">Buchungsstapel DATEV (CSV)</a></p>
    <p class="help" style="margin-bottom:0">Der DATEV-Buchungsstapel enthält die Spaltenstruktur; Konto/Gegenkonto ordnet dein Steuerberater nach seinem Kontenrahmen zu.</p>
</div>

<div class="panel">
    <h2>EÜR <?= $year ?></h2>
    <p><a href="/auswertungen/csv?jahr=<?= $year ?>" class="btn btn-secondary">EÜR (CSV)</a>
       <a href="/auswertungen/pdf?jahr=<?= $year ?>&amp;_=<?= time() ?>" class="btn btn-secondary" target="_blank">EÜR (PDF)</a></p>
</div>

<div class="panel">
    <h2>Komplett-Archiv <?= $year ?></h2>
    <p>ZIP mit allen finalisierten Rechnungen, Belegen und einer EÜR-Übersicht des Jahres.</p>
    <p><a href="/exporte/jahr?jahr=<?= $year ?>" class="btn">Jahres-ZIP herunterladen</a>
       <a href="/exporte/belege?jahr=<?= $year ?>" class="btn btn-secondary">Nur Belege (ZIP)</a></p>
</div>
<p class="help">Alle Daten eines Jahres lassen sich so vollständig exportieren und archivieren (GoBD).</p>
