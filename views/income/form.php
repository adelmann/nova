<?php
/** @var array<string,mixed> $income */
/** @var array<int,string> $categories */
/** @var array<int,array<string,mixed>> $projects */
/** @var string $action */
$in = $income;
$amount = isset($in['amount_cents']) ? amount((int) $in['amount_cents']) : '0,00';
?>
<div class="panel">
    <form method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="income_date">Datum</label>
                <input type="date" id="income_date" name="income_date" value="<?= e($in['income_date']) ?>">
            </div>
            <div class="field">
                <label for="source">Quelle</label>
                <input type="text" id="source" name="source" value="<?= e($in['source']) ?>" placeholder="z.B. Amazon PartnerNet, Awin">
            </div>
            <div class="field">
                <label for="category">Kategorie</label>
                <select id="category" name="category">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= ($in['category'] ?? '') === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="amount">Betrag (€)</label>
                <input type="text" id="amount" name="amount" value="<?= e($amount) ?>" inputmode="decimal">
            </div>
            <div class="field">
                <label for="project_id">Projekt (optional)</label>
                <select id="project_id" name="project_id">
                    <option value="">– kein Projekt –</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (int) ($in['project_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?><?= ($p['project_type'] ?? '') === 'internal' ? ' (Intern)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field full">
                <label for="note">Notiz</label>
                <textarea id="note" name="note"><?= e($in['note']) ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Speichern</button>
            <a href="/einnahmen" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
<p class="help">Für laufende Affiliate-Erlöse kannst du entweder jede Auszahlung einzeln oder eine Monatssumme erfassen – beides funktioniert. Betragsänderungen werden per Differenz-Gegenbuchung nachgeführt (Journal bleibt unveränderbar, GoBD).</p>
