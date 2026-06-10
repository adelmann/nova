<?php
/** @var array<string,mixed> $settings */
$s = $settings;
$appUrl = rtrim((string) ($GLOBALS['nova_config']['app_url'] ?? ''), '/');
if ($appUrl === '') {
    // Aktuelle Domain aus dem Request ableiten.
    $scheme   = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') ? 'https' : 'http';
    $host     = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $cronBase = $host !== '' ? $scheme . '://' . $host : 'https://IHRE-DOMAIN';
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
