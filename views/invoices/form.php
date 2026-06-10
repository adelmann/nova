<?php
/** @var array<string,mixed> $invoice */
/** @var array<int,array<string,mixed>> $items */
/** @var array<int,array<string,mixed>> $customers */
/** @var array<int,array<string,mixed>> $projects */
/** @var string $action */
$inv = $invoice;
?>
<div class="panel">
    <form method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="customer_id">Kunde</label>
                <select id="customer_id" name="customer_id" required>
                    <option value="">– bitte wählen –</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) $inv['customer_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['company_name'] ?: $c['contact_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="project_id">Projekt (optional)</label>
                <select id="project_id" name="project_id">
                    <option value="">– kein Projekt –</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (int) ($inv['project_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?> (<?= e($p['company_name'] ?: $p['contact_name'] ?: 'Intern') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="invoice_date">Rechnungsdatum</label>
                <input type="date" id="invoice_date" name="invoice_date" value="<?= e($inv['invoice_date']) ?>">
            </div>
            <div class="field"></div>
            <div class="field">
                <label for="service_date_from">Leistungszeitraum von</label>
                <input type="date" id="service_date_from" name="service_date_from" value="<?= e($inv['service_date_from']) ?>">
            </div>
            <div class="field">
                <label for="service_date_to">bis</label>
                <input type="date" id="service_date_to" name="service_date_to" value="<?= e($inv['service_date_to']) ?>">
            </div>
            <div class="field full">
                <label for="intro_text">Einleitungstext (optional)</label>
                <textarea id="intro_text" name="intro_text"><?= e($inv['intro_text']) ?></textarea>
            </div>
        </div>

        <h2 style="margin-top:18px;font-size:15px;">Positionen</h2>
        <?= partial('partials/line_items', ['items' => $items]) ?>

        <div class="field full" style="margin-top:14px;">
            <label for="footer_text">Fußtext</label>
            <textarea id="footer_text" name="footer_text"><?= e($inv['footer_text']) ?></textarea>
        </div>

        <?php if ((int) $inv['is_kleinunternehmer'] === 1): ?>
            <p class="help">Kleinunternehmer nach § 19 UStG aktiv – es wird keine Umsatzsteuer ausgewiesen.</p>
        <?php else: ?>
            <p class="help">USt-Satz: <?= (int) $inv['vat_rate'] ?> % (aus den Einstellungen).</p>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn">Als Entwurf speichern</button>
            <a href="/rechnungen" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
<p class="help">Hinweis: Die Rechnung wird zunächst als Entwurf gespeichert. Erst beim <strong>Finalisieren</strong> wird eine fortlaufende Rechnungsnummer vergeben; danach ist sie nicht mehr änderbar (GoBD).</p>
