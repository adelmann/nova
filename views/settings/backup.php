<?php
/** @var array<string,mixed> $settings */
/** @var array<int,array{name:string,size:int,created:int}> $backups */
$s = $settings;
$backups = $backups ?? [];
$fmtSize = static fn (int $b): string => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
$appUrl = rtrim((string) ($GLOBALS['nova_config']['app_url'] ?? ''), '/');
if ($appUrl === '') {
    // Aktuelle Domain aus dem Request ableiten.
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443') ? 'https' : 'http';
    $host     = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $cronBase = $scheme . '://' . $host;
} else {
    $cronBase = $appUrl;
}
$cronUrl  = !empty($s['backup_token'])
    ? $cronBase . '/cron/backup?token=' . $s['backup_token']
    : '(wird nach dem Speichern erzeugt)';
$defaultBackupDir = (string) ($GLOBALS['nova_config']['paths']['backups'] ?? '');
?>
<?= partial('settings/_nav', ['active' => 'backup']) ?>

<form method="post" action="/einstellungen/datensicherung">
    <?= csrf_field() ?>
    <div class="panel">
        <h2>Datensicherung (Backup)</h2>
        <p class="help" style="margin-top:0">Packt Datenbank, Belege, Rechnungen und Angebote in ein <?= \Nova\Services\BackupService::encryptionSupported() ? 'AES-256-verschlüsseltes' : '(unverschlüsseltes – Server ohne ZIP-Verschlüsselung)' ?> ZIP. Versand per E-Mail und/oder Ablage in einem Serververzeichnis sind optional.</p>
        <div class="form-grid">
            <div class="field">
                <label for="backup_password">ZIP-Passwort</label>
                <input type="password" id="backup_password" name="backup_password" value="" autocomplete="new-password" placeholder="<?= !empty($s['backup_password']) ? '•••••••• (gespeichert – zum Ändern neu eingeben)' : 'leer = unverschlüsselt' ?>">
                <span class="help">Zum Öffnen der Backups nötig – bitte sicher notieren.</span>
            </div>
            <div class="field">
                <label for="backup_email">Backup per E-Mail an (optional)</label>
                <input type="email" id="backup_email" name="backup_email" value="<?= e($s['backup_email'] ?? '') ?>" placeholder="z.B. backup@meine-domain.de">
            </div>
            <div class="field full">
                <label for="backup_dir">Zusätzliches Zielverzeichnis auf dem Server (optional)</label>
                <input type="text" id="backup_dir" name="backup_dir" value="<?= e($s['backup_dir'] ?? '') ?>" placeholder="<?= e($defaultBackupDir) ?>">
                <span class="help">Absoluter Pfad, wird bei Bedarf angelegt. Backups liegen ohnehin immer unter <code><?= e($defaultBackupDir) ?></code> – hier kannst du eine <em>zusätzliche</em> Kopie ablegen (z.B. außerhalb des Web-Roots).</span>
            </div>
        </div>

        <div class="flash flash-warn" style="margin-top:6px;">
            <strong>Cron-Aufruf (Web):</strong> Diese URL z.B. täglich vom Cron des Hosters per <code>wget&nbsp;-qO-</code> aufrufen lassen:
            <div class="field" style="margin-top:8px;">
                <input type="text" readonly onclick="this.select()" value="<?= e($cronUrl) ?>">
            </div>
            <span class="help">Alternativ ohne Web: <code>php bin/backup.php</code> als CLI-Cron. Es werden automatisch die letzten 14 Backups aufbewahrt.</span>
            <?php if (!empty($s['backup_token'])): ?>
                <label class="checkbox" style="margin-top:8px;">
                    <input type="checkbox" name="regenerate_backup_token" value="1"> Token neu generieren (alter Link wird ungültig)
                </label>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Speichern</button>
    </div>
</form>

<div class="panel" style="margin-top:28px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h2 style="margin:0;">Vorhandene Backups</h2>
        <form method="post" action="/einstellungen/datensicherung/jetzt">
            <?= csrf_field() ?>
            <button type="submit" class="btn">Backup jetzt erstellen</button>
        </form>
    </div>

    <?php if ($backups === []): ?>
        <p class="muted" style="margin-bottom:0">Noch keine Backups vorhanden. Erstelle eines manuell oder per Cron.</p>
    <?php else: ?>
        <div class="table-wrap" style="box-shadow:none;border:1px solid var(--border); margin-top:12px;">
            <table>
                <thead><tr><th>Datei</th><th>Erstellt</th><th class="num">Größe</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($backups as $b): ?>
                    <tr>
                        <td><?= e($b['name']) ?></td>
                        <td><?= e(date('d.m.Y H:i', $b['created'])) ?></td>
                        <td class="num"><?= e($fmtSize($b['size'])) ?></td>
                        <td style="text-align:right; white-space:nowrap;">
                            <a class="btn btn-secondary btn-sm" href="/einstellungen/datensicherung/download?file=<?= urlencode($b['name']) ?>">Herunterladen</a>
                            <form method="post" action="/einstellungen/datensicherung/loeschen" data-confirm="Dieses Backup löschen?" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="file" value="<?= e($b['name']) ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">✕</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="help" style="margin-bottom:0">Es werden automatisch die letzten 14 Backups aufbewahrt.<?php if (!empty($s['backup_password'])): ?> Die ZIPs sind passwortgeschützt.<?php endif; ?></p>
    <?php endif; ?>
</div>
