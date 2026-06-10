<?php
/** @var array<string,mixed> $profile */
/** @var array<int,array<string,mixed>> $items */
/** @var array<int,array<string,mixed>> $customers */
/** @var string $action */
$p = $profile;
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
                        <option value="<?= (int) $c['id'] ?>" <?= (int) $p['customer_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['company_name'] ?: $c['contact_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="title">Bezeichnung (intern)</label>
                <input type="text" id="title" name="title" value="<?= e($p['title']) ?>" placeholder="z.B. Hosting-Abo Müller">
            </div>
            <div class="field">
                <label for="interval_unit">Intervall</label>
                <select id="interval_unit" name="interval_unit">
                    <?php foreach (['month' => 'monatlich', 'quarter' => 'quartalsweise', 'year' => 'jährlich'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $p['interval_unit'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="next_date">Nächste Rechnung am</label>
                <input type="date" id="next_date" name="next_date" value="<?= e($p['next_date']) ?>">
            </div>
            <div class="field full">
                <label for="intro_text">Einleitungstext (optional)</label>
                <textarea id="intro_text" name="intro_text"><?= e($p['intro_text']) ?></textarea>
            </div>
        </div>

        <h2 style="margin-top:18px;font-size:15px;">Positionen</h2>
        <?= partial('partials/line_items', ['items' => $items]) ?>

        <div class="field full" style="margin-top:14px;">
            <label for="footer_text">Fußtext (optional)</label>
            <textarea id="footer_text" name="footer_text"><?= e($p['footer_text']) ?></textarea>
        </div>

        <div class="form-grid" style="margin-top:8px;">
            <div class="field full">
                <label class="checkbox">
                    <input type="checkbox" name="auto_send" value="1" <?= (int) $p['auto_send'] === 1 ? 'checked' : '' ?>>
                    Automatisch finalisieren <strong>und</strong> per E-Mail an den Kunden senden (sonst nur Entwurf erstellen)
                </label>
            </div>
            <div class="field full">
                <label class="checkbox">
                    <input type="checkbox" name="active" value="1" <?= (int) $p['active'] === 1 ? 'checked' : '' ?>>
                    Aktiv (pausieren = kein automatischer Lauf)
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">Speichern</button>
            <a href="/wiederkehrend" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
    <?php if (!empty($p['id'])): ?>
        <form method="post" action="/wiederkehrend/<?= (int) $p['id'] ?>/loeschen" data-confirm="Profil wirklich löschen?" style="margin-top:10px;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Profil löschen</button>
        </form>
    <?php endif; ?>
</div>
<p class="help">Die Rechnungen werden automatisch erzeugt, sobald der Cron läuft (<code>php bin/sweep.php</code> bzw. der Wartungs-Cron). „Auto senden" finalisiert die Rechnung und verschickt sie per E-Mail – andernfalls landet sie als Entwurf in den Rechnungen.</p>
