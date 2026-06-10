<?php
/** @var array<string,mixed> $project */
/** @var array<int,array<string,mixed>> $customers */
/** @var string $action */
$p = $project;
$rate = isset($p['hourly_rate_cents']) ? amount((int) $p['hourly_rate_cents']) : '0,00';
$type = $p['project_type'] ?? 'customer';
?>
<div class="panel">
    <form method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="name">Projektname</label>
                <input type="text" id="name" name="name" value="<?= e($p['name']) ?>" required>
            </div>
            <div class="field">
                <label for="project_type">Projekttyp</label>
                <select id="project_type" name="project_type" onchange="novaToggleCustomer(this.value)">
                    <option value="customer" <?= $type === 'customer' ? 'selected' : '' ?>>Kundenprojekt</option>
                    <option value="internal" <?= $type === 'internal' ? 'selected' : '' ?>>Intern (z.B. eigene Website/Affiliate)</option>
                </select>
            </div>
            <div class="field" id="customer-field" style="<?= $type === 'internal' ? 'display:none' : '' ?>">
                <label for="customer_id">Kunde</label>
                <select id="customer_id" name="customer_id">
                    <option value="">– bitte wählen –</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($p['customer_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['company_name'] ?: $c['contact_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach (['active' => 'Aktiv', 'paused' => 'Pausiert', 'done' => 'Abgeschlossen', 'cancelled' => 'Abgebrochen'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $p['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="hourly_rate">Stundensatz (€)</label>
                <input type="text" id="hourly_rate" name="hourly_rate" value="<?= e($rate) ?>" inputmode="decimal">
            </div>
            <div class="field">
                <label for="start_date">Startdatum</label>
                <input type="date" id="start_date" name="start_date" value="<?= e($p['start_date']) ?>">
            </div>
            <div class="field">
                <label for="end_date">Enddatum</label>
                <input type="date" id="end_date" name="end_date" value="<?= e($p['end_date']) ?>">
            </div>
            <div class="field full">
                <label for="description">Beschreibung</label>
                <textarea id="description" name="description"><?= e($p['description']) ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Speichern</button>
            <a href="/projekte" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
<script>
function novaToggleCustomer(type) {
    document.getElementById('customer-field').style.display = (type === 'internal') ? 'none' : '';
}
</script>
