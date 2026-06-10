<?php
/** @var array<string,mixed> $quote */
/** @var array<int,array<string,mixed>> $items */
/** @var array<int,array<string,mixed>> $customers */
/** @var array<int,array<string,mixed>> $projects */
/** @var string $action */
$q = $quote;
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
                        <option value="<?= (int) $c['id'] ?>" <?= (int) $q['customer_id'] === (int) $c['id'] ? 'selected' : '' ?>>
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
                        <option value="<?= (int) $p['id'] ?>" <?= (int) ($q['project_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?> (<?= e($p['company_name'] ?: $p['contact_name'] ?: 'Intern') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="quote_date">Angebotsdatum</label>
                <input type="date" id="quote_date" name="quote_date" value="<?= e($q['quote_date']) ?>">
            </div>
            <div class="field">
                <label for="valid_until">Gültig bis</label>
                <input type="date" id="valid_until" name="valid_until" value="<?= e($q['valid_until']) ?>">
            </div>
            <div class="field full">
                <label for="intro_text">Einleitungstext (optional)</label>
                <textarea id="intro_text" name="intro_text" placeholder="z.B. vielen Dank für Ihre Anfrage…"><?= e($q['intro_text']) ?></textarea>
            </div>
        </div>

        <h2 style="margin-top:18px;font-size:15px;">Positionen</h2>
        <?= partial('partials/line_items', ['items' => $items]) ?>

        <div class="field full" style="margin-top:14px;">
            <label for="footer_text">Fußtext</label>
            <textarea id="footer_text" name="footer_text"><?= e($q['footer_text']) ?></textarea>
        </div>

        <?php if ((int) $q['is_kleinunternehmer'] === 1): ?>
            <p class="help">Kleinunternehmer nach § 19 UStG aktiv – es wird keine Umsatzsteuer ausgewiesen.</p>
        <?php else: ?>
            <p class="help">USt-Satz: <?= (int) $q['vat_rate'] ?> % (aus den Einstellungen).</p>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn">Speichern</button>
            <a href="/angebote" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
