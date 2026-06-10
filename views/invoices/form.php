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
            <div class="field">
                <label for="invoice_type">Rechnungsart</label>
                <select id="invoice_type" name="invoice_type">
                    <?php $itype = (string) ($inv['invoice_type'] ?? 'standard'); ?>
                    <option value="standard" <?= $itype === 'standard' ? 'selected' : '' ?>>Standardrechnung</option>
                    <option value="partial" <?= $itype === 'partial' ? 'selected' : '' ?>>Abschlagsrechnung (Anzahlung)</option>
                    <option value="final" <?= $itype === 'final' ? 'selected' : '' ?>>Schlussrechnung</option>
                </select>
            </div>
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
        <?= partial('partials/line_items', ['items' => array_values(array_filter($items, static fn ($it) => (int) ($it['unit_price_cents'] ?? 0) >= 0 || strpos((string) ($it['description'] ?? ''), 'Abzüglich Abschlagsrechnung') !== 0))]) ?>

        <?php if (($partials ?? []) !== []): ?>
            <div class="panel" style="margin-top:14px; box-shadow:none; border:1px solid var(--border);">
                <h2 style="font-size:14px; margin-top:0;">Abschläge abziehen (nur bei Schlussrechnung)</h2>
                <p class="help" style="margin-top:0;">Markierte Abschlagsrechnungen werden als negative Positionen abgezogen. Wirksam nur, wenn oben „Schlussrechnung" gewählt ist.</p>
                <?php foreach ($partials as $pt): ?>
                    <label class="check" style="display:block; padding:3px 0;">
                        <input type="checkbox" name="deduct[]" value="<?= (int) $pt['id'] ?>" <?= in_array((int) $pt['id'], $deductedIds ?? [], true) ? 'checked' : '' ?>>
                        <?= e($pt['number']) ?> vom <?= dt($pt['invoice_date']) ?> · netto <?= money((int) $pt['net_total_cents']) ?> (brutto <?= money((int) $pt['gross_total_cents']) ?>)
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php
        $dtype = (string) ($inv['discount_type'] ?? 'none');
        $dval  = $dtype === 'percent' ? rtrim(rtrim(number_format((int) ($inv['discount_value'] ?? 0) / 100, 2, ',', ''), '0'), ',') : amount((int) ($inv['discount_value'] ?? 0));
        ?>
        <div class="form-grid" style="margin-top:14px;">
            <div class="field">
                <label for="discount_type">Rabatt</label>
                <select id="discount_type" name="discount_type">
                    <option value="none" <?= $dtype === 'none' ? 'selected' : '' ?>>kein Rabatt</option>
                    <option value="percent" <?= $dtype === 'percent' ? 'selected' : '' ?>>Prozent (%)</option>
                    <option value="amount" <?= $dtype === 'amount' ? 'selected' : '' ?>>Fester Betrag (€)</option>
                </select>
            </div>
            <div class="field">
                <label for="discount_value">Rabattwert</label>
                <input type="text" id="discount_value" name="discount_value" value="<?= e($dtype === 'none' ? '' : $dval) ?>" inputmode="decimal" placeholder="z.B. 10 (%) oder 50,00 (€)">
            </div>
        </div>

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
