<?php
/** @var array<string,mixed> $user */
/** @var array<string,string> $roles */
/** @var string $action */
$u = $user;
$isNew = empty($u['id']);
$isSelf = $isSelf ?? false;
$active = (int) ($u['is_active'] ?? 1) === 1;
?>
<div class="toolbar">
    <a href="/benutzer" class="btn btn-secondary btn-sm">← Zurück</a>
</div>

<div class="panel">
    <form method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?= e($u['name']) ?>" required>
            </div>
            <div class="field">
                <label for="email">E-Mail<?= $isNew ? ' (Login)' : '' ?></label>
                <?php if ($isNew): ?>
                    <input type="email" id="email" name="email" value="<?= e($u['email']) ?>" required>
                <?php else: ?>
                    <input type="email" id="email" value="<?= e($u['email']) ?>" disabled>
                    <span class="help">Die E-Mail ändert der Benutzer selbst unter „Konto".</span>
                <?php endif; ?>
            </div>
            <div class="field full">
                <label for="role">Rolle</label>
                <select id="role" name="role">
                    <?php foreach ($roles as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($u['role'] ?? 'staff') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="help">Steuerberater sieht nur Buchhaltungs-/Finanzansichten (nur lesend). Mitarbeiter darf operativ arbeiten, aber keine Einstellungen/Benutzer.</span>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn"><?= $isNew ? 'Anlegen & Einladung senden' : 'Speichern' ?></button>
        </div>
    </form>
</div>

<?php if (!$isNew): ?>
    <div class="panel">
        <h2>Zugang</h2>
        <p>
            Status:
            <?php if ($active): ?><span class="badge badge-paid">aktiv</span><?php else: ?><span class="badge badge-draft">deaktiviert</span><?php endif; ?>
            &nbsp;·&nbsp; 2FA: <?= (int) ($u['totp_enabled'] ?? 0) === 1 ? 'aktiv 🔒' : 'inaktiv' ?>
        </p>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <form method="post" action="/benutzer/<?= (int) $u['id'] ?>/einladung" data-confirm="Einladungs-/Passwortlink erneut per E-Mail senden?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">Einladung erneut senden</button>
            </form>
            <?php if (!$isSelf): ?>
                <form method="post" action="/benutzer/<?= (int) $u['id'] ?>/status" data-confirm="<?= $active ? 'Benutzer deaktivieren (Login wird gesperrt)?' : 'Benutzer wieder aktivieren?' ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn <?= $active ? 'btn-danger' : '' ?>"><?= $active ? 'Deaktivieren' : 'Aktivieren' ?></button>
                </form>
            <?php endif; ?>
        </div>
        <p class="help" style="margin-bottom:0">Benutzer werden deaktiviert statt gelöscht – so bleibt die Historie im Änderungsprotokoll erhalten.</p>
    </div>
<?php endif; ?>
