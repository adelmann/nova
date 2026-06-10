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
$sweepUrl = !empty($s['backup_token'])
    ? $cronBase . '/cron/sweep?token=' . $s['backup_token']
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
            <div class="field">
                <label for="backup_interval_hours">Backup anlegen höchstens alle … Stunden</label>
                <input type="number" id="backup_interval_hours" name="backup_interval_hours" min="0" max="8760" value="<?= e((string) ($s['backup_interval_hours'] ?? 24)) ?>">
                <span class="help">Drosselt den Web-Cron: ein neues Backup wird nur angelegt, wenn das letzte älter ist. <code>0</code> = bei jedem Aufruf. Beispiel: stündlicher Cron + <code>24</code> = ein Backup pro Tag.</span>
            </div>
            <div class="field">
                <label for="backup_email_interval_hours">E-Mail-Versand höchstens alle … Stunden</label>
                <input type="number" id="backup_email_interval_hours" name="backup_email_interval_hours" min="0" max="8760" value="<?= e((string) ($s['backup_email_interval_hours'] ?? 24)) ?>">
                <span class="help"><code>0</code> = nie automatisch per E-Mail. So lässt sich z.&nbsp;B. täglich sichern, aber nur wöchentlich (<code>168</code>) mailen.</span>
            </div>
            <div class="field full">
                <label for="backup_dir">Zusätzliches Zielverzeichnis auf dem Server (optional)</label>
                <input type="text" id="backup_dir" name="backup_dir" value="<?= e($s['backup_dir'] ?? '') ?>" placeholder="<?= e($defaultBackupDir) ?>">
                <span class="help">Absoluter Pfad, wird bei Bedarf angelegt. Backups liegen ohnehin immer unter <code><?= e($defaultBackupDir) ?></code> – hier kannst du eine <em>zusätzliche</em> Kopie ablegen (z.B. außerhalb des Web-Roots).</span>
            </div>
        </div>

        <div class="flash flash-warn" style="margin-top:6px;">
            <strong>1) Backup-Cron (Web):</strong> Diese URL regelmäßig vom Cron des Hosters per <code>wget&nbsp;-qO-</code> aufrufen lassen. Wie oft tatsächlich ein Backup entsteht, steuert das Intervall oben.
            <div class="field" style="margin-top:8px;">
                <input type="text" readonly onclick="this.select()" value="<?= e($cronUrl) ?>">
            </div>
            <span class="help">Mit <code>&amp;force=1</code> wird das Intervall ignoriert (sofortiges Backup). Es werden automatisch die letzten 14 Backups aufbewahrt.</span>
            <?php if (!empty($s['backup_token'])): ?>
                <label class="checkbox" style="margin-top:8px;">
                    <input type="checkbox" name="regenerate_backup_token" value="1"> Token neu generieren (beide Links werden ungültig)
                </label>
            <?php endif; ?>
        </div>

        <div class="flash flash-warn" style="margin-top:10px;">
            <strong>2) Wartungs-Cron (Web):</strong> erledigt wiederkehrende Rechnungen &amp; <strong>Dauerausgaben</strong>, jährliche <strong>AfA</strong>, überfällige Rechnungen und automatische Mahnungen. Ohne diesen Aufruf passiert das nicht automatisch! Diese URL z.&nbsp;B. <strong>1–2×&nbsp;täglich</strong> aufrufen lassen:
            <div class="field" style="margin-top:8px;">
                <input type="text" readonly onclick="this.select()" value="<?= e($sweepUrl) ?>">
            </div>
            <span class="help">Alle Aufgaben sind idempotent – häufigeres Aufrufen schadet nicht (es wird nichts doppelt gebucht).</span>
        </div>

        <div class="flash" style="margin-top:10px; background:var(--bg-soft);">
            <strong>Ohne Web-Cron (CLI):</strong> Falls dein Hoster echte Cronjobs erlaubt, stattdessen direkt:
            <pre style="margin:8px 0 0; white-space:pre-wrap;"># täglich 03:00 – Backup
0 3 * * *  cd <?= e((string) ($GLOBALS['nova_config']['paths']['root'] ?? '/pfad/zu/nova')) ?> &amp;&amp; php bin/backup.php

# täglich 02:30 – Wartung (wiederkehrend, AfA, Mahnungen, überfällig)
30 2 * * *  cd <?= e((string) ($GLOBALS['nova_config']['paths']['root'] ?? '/pfad/zu/nova')) ?> &amp;&amp; php bin/sweep.php</pre>
        </div>
    </div>

    <div class="panel">
        <h2>Cloud-Backup (optional)</h2>
        <p class="help" style="margin-top:0">Jedes ausgefüllte Ziel erhält nach jeder Sicherung automatisch eine Kopie des ZIPs. Leere Felder = Ziel inaktiv. Passwörter/Schlüssel werden nur bei Eingabe geändert.</p>

        <h3 style="margin:14px 0 6px; font-size:14px;">WebDAV (Nextcloud / ownCloud)</h3>
        <div class="form-grid">
            <div class="field full">
                <label for="backup_webdav_url">WebDAV-Ordner-URL</label>
                <input type="text" id="backup_webdav_url" name="backup_webdav_url" value="<?= e($s['backup_webdav_url'] ?? '') ?>" placeholder="https://cloud.example.com/remote.php/dav/files/USER/Nova-Backups">
            </div>
            <div class="field">
                <label for="backup_webdav_user">Benutzer</label>
                <input type="text" id="backup_webdav_user" name="backup_webdav_user" value="<?= e($s['backup_webdav_user'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="field">
                <label for="backup_webdav_pass">Passwort / App-Passwort</label>
                <input type="password" id="backup_webdav_pass" name="backup_webdav_pass" value="" autocomplete="new-password" placeholder="<?= !empty($s['backup_webdav_pass']) ? '•••••••• (gespeichert)' : '' ?>">
            </div>
        </div>

        <h3 style="margin:18px 0 6px; font-size:14px;">S3-kompatibel (Backblaze B2 / Wasabi / AWS / Hetzner)</h3>
        <div class="form-grid">
            <div class="field">
                <label for="backup_s3_endpoint">Endpoint</label>
                <input type="text" id="backup_s3_endpoint" name="backup_s3_endpoint" value="<?= e($s['backup_s3_endpoint'] ?? '') ?>" placeholder="s3.eu-central-1.amazonaws.com">
            </div>
            <div class="field">
                <label for="backup_s3_region">Region</label>
                <input type="text" id="backup_s3_region" name="backup_s3_region" value="<?= e($s['backup_s3_region'] ?? '') ?>" placeholder="eu-central-1">
            </div>
            <div class="field">
                <label for="backup_s3_bucket">Bucket</label>
                <input type="text" id="backup_s3_bucket" name="backup_s3_bucket" value="<?= e($s['backup_s3_bucket'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="backup_s3_prefix">Ordner/Präfix (optional)</label>
                <input type="text" id="backup_s3_prefix" name="backup_s3_prefix" value="<?= e($s['backup_s3_prefix'] ?? '') ?>" placeholder="nova-backups">
            </div>
            <div class="field">
                <label for="backup_s3_key">Access Key</label>
                <input type="text" id="backup_s3_key" name="backup_s3_key" value="<?= e($s['backup_s3_key'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="field">
                <label for="backup_s3_secret">Secret Key</label>
                <input type="password" id="backup_s3_secret" name="backup_s3_secret" value="" autocomplete="new-password" placeholder="<?= !empty($s['backup_s3_secret']) ? '•••••••• (gespeichert)' : '' ?>">
            </div>
        </div>

        <h3 style="margin:18px 0 6px; font-size:14px;">FTP / FTPS</h3>
        <div class="form-grid">
            <div class="field">
                <label for="backup_ftp_host">Host</label>
                <input type="text" id="backup_ftp_host" name="backup_ftp_host" value="<?= e($s['backup_ftp_host'] ?? '') ?>" placeholder="ftp.example.com">
            </div>
            <div class="field">
                <label for="backup_ftp_port">Port</label>
                <input type="number" id="backup_ftp_port" name="backup_ftp_port" value="<?= e((string) ($s['backup_ftp_port'] ?? 21)) ?>" min="1" max="65535">
            </div>
            <div class="field">
                <label for="backup_ftp_user">Benutzer</label>
                <input type="text" id="backup_ftp_user" name="backup_ftp_user" value="<?= e($s['backup_ftp_user'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="field">
                <label for="backup_ftp_pass">Passwort</label>
                <input type="password" id="backup_ftp_pass" name="backup_ftp_pass" value="" autocomplete="new-password" placeholder="<?= !empty($s['backup_ftp_pass']) ? '•••••••• (gespeichert)' : '' ?>">
            </div>
            <div class="field">
                <label for="backup_ftp_path">Zielordner</label>
                <input type="text" id="backup_ftp_path" name="backup_ftp_path" value="<?= e($s['backup_ftp_path'] ?? '') ?>" placeholder="/backups/nova">
            </div>
            <div class="field">
                <label class="checkbox" style="margin-top:24px;"><input type="checkbox" name="backup_ftp_tls" value="1" <?= !empty($s['backup_ftp_tls']) ? 'checked' : '' ?>> Verschlüsselt (FTPS/TLS)</label>
            </div>
        </div>

        <h3 style="margin:18px 0 6px; font-size:14px;">Dropbox</h3>
        <div class="form-grid">
            <div class="field">
                <label for="backup_dropbox_token">Access-Token</label>
                <input type="password" id="backup_dropbox_token" name="backup_dropbox_token" value="" autocomplete="new-password" placeholder="<?= !empty($s['backup_dropbox_token']) ? '•••••••• (gespeichert)' : 'sl.… (App-Token)' ?>">
            </div>
            <div class="field">
                <label for="backup_dropbox_path">Zielordner</label>
                <input type="text" id="backup_dropbox_path" name="backup_dropbox_path" value="<?= e($s['backup_dropbox_path'] ?? '') ?>" placeholder="/Nova-Backups">
            </div>
        </div>
        <span class="help">Dropbox: in der <a href="https://www.dropbox.com/developers/apps" target="_blank" rel="noopener">App-Konsole</a> eine App (Scoped, <code>files.content.write</code>) anlegen und ein Access-Token erzeugen.</span>

        <h3 style="margin:18px 0 6px; font-size:14px;">Google Drive</h3>
        <div class="form-grid">
            <div class="field">
                <label for="backup_gdrive_client_id">OAuth Client-ID</label>
                <input type="text" id="backup_gdrive_client_id" name="backup_gdrive_client_id" value="<?= e($s['backup_gdrive_client_id'] ?? '') ?>" autocomplete="off" placeholder="…apps.googleusercontent.com">
            </div>
            <div class="field">
                <label for="backup_gdrive_client_secret">Client-Secret</label>
                <input type="password" id="backup_gdrive_client_secret" name="backup_gdrive_client_secret" value="" autocomplete="new-password" placeholder="<?= !empty($s['backup_gdrive_client_secret']) ? '•••••••• (gespeichert)' : '' ?>">
            </div>
            <div class="field">
                <label for="backup_gdrive_refresh_token">Refresh-Token</label>
                <input type="password" id="backup_gdrive_refresh_token" name="backup_gdrive_refresh_token" value="" autocomplete="new-password" placeholder="<?= !empty($s['backup_gdrive_refresh_token']) ? '•••••••• (gespeichert)' : '1//…' ?>">
            </div>
            <div class="field">
                <label for="backup_gdrive_folder_id">Ziel-Ordner-ID (optional)</label>
                <input type="text" id="backup_gdrive_folder_id" name="backup_gdrive_folder_id" value="<?= e($s['backup_gdrive_folder_id'] ?? '') ?>" placeholder="aus der Drive-Ordner-URL">
            </div>
        </div>
        <span class="help">Einrichtung: in der <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">Google Cloud Console</a> eine OAuth-Client-ID (Typ „Desktop") für die <em>Google Drive API</em> anlegen. Damit über den <a href="https://developers.google.com/oauthplayground" target="_blank" rel="noopener">OAuth Playground</a> (eigene Client-Daten, Scope <code>drive.file</code>) ein Refresh-Token erzeugen. Die Ordner-ID steht in der URL des Drive-Ordners.</span>
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
