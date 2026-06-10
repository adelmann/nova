<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Format;
use Nova\Core\Request;
use Nova\Core\Session;
use Nova\Models\CompanySettingsRepository;
use Nova\Services\AuditService;
use Nova\Services\ReceiptService;
use Nova\Services\UpdateService;

/**
 * Einstellungen, aufgeteilt in Abschnitte (Unternehmen, Rechnungen & Steuer,
 * E-Mail, Datensicherung, System). Jeder Abschnitt hat sein eigenes Formular
 * und speichert nur seine Felder.
 */
final class SettingsController extends Controller
{
    // ---- Unternehmen (Firma, Bank, Logo, Rechtliches) ----------------------
    public function edit(Request $request): void
    {
        $this->section('settings/company', 'Einstellungen · Unternehmen');
    }

    public function update(Request $request): void
    {
        $this->verifyCsrf($request);
        $repo   = new CompanySettingsRepository();
        $before = $repo->get();

        $data = [
            'company_name'  => $request->str('company_name'),
            'owner_name'    => $request->str('owner_name'),
            'address_line1' => $request->str('address_line1'),
            'address_line2' => $request->str('address_line2'),
            'zip'           => $request->str('zip'),
            'city'          => $request->str('city'),
            'country'       => $request->str('country', 'Deutschland'),
            'email'         => $request->str('email'),
            'phone'         => $request->str('phone'),
            'website'       => $request->str('website'),
            'social_media'  => $request->str('social_media'),
            'tax_number'    => $request->str('tax_number'),
            'vat_id'        => $request->str('vat_id'),
            'bank_name'     => $request->str('bank_name'),
            'iban'          => $request->str('iban'),
            'bic'           => $request->str('bic'),
            'imprint_url'   => $request->str('imprint_url'),
            'privacy_url'   => $request->str('privacy_url'),
        ];

        $logo = $request->file('logo');
        if ($logo !== null) {
            try {
                $data['logo_path'] = ReceiptService::storeLogo($logo);
            } catch (\RuntimeException $e) {
                Session::flash('error', 'Logo-Upload fehlgeschlagen: ' . $e->getMessage());
                $this->redirect('/einstellungen');
            }
        }

        $this->save($repo, $before, $data, '/einstellungen');
    }

    // ---- Rechnungen & Steuer ----------------------------------------------
    public function invoicing(Request $request): void
    {
        $this->section('settings/invoicing', 'Einstellungen · Rechnungen & Steuer');
    }

    public function saveInvoicing(Request $request): void
    {
        $this->verifyCsrf($request);
        $repo   = new CompanySettingsRepository();
        $before = $repo->get();

        $this->save($repo, $before, [
            'is_kleinunternehmer'   => $request->bool('is_kleinunternehmer') ? 1 : 0,
            'default_vat_rate'      => $request->int('default_vat_rate', 19),
            'default_payment_days'  => $request->int('default_payment_days', 14),
            'invoice_number_format' => $request->str('invoice_number_format', 'RE-{YYYY}-{####}'),
            'quote_number_format'   => $request->str('quote_number_format', 'AN-{YYYY}-{####}'),
            'kleinunternehmer_note' => $request->str('kleinunternehmer_note'),
            'invoice_footer_text'   => $request->str('invoice_footer_text'),
            'quote_footer_text'     => $request->str('quote_footer_text'),
            'payment_methods'       => $this->normalizeLines($request->str('payment_methods')),
            'auto_reminder_days'    => max(0, $request->int('auto_reminder_days', 0)),
            'dunning_fee_cents'     => Format::toCents($request->str('dunning_fee')),
            'interest_rate_bp'      => max(0, (int) round(((float) str_replace(',', '.', $request->str('interest_rate'))) * 100)),
            'skonto_percent_bp'     => max(0, (int) round(((float) str_replace(',', '.', $request->str('skonto_percent'))) * 100)),
            'skonto_days'           => max(0, $request->int('skonto_days', 0)),
        ], '/einstellungen/rechnungen');
    }

    // ---- E-Mail (SMTP + Signatur + Vorlagen) ------------------------------
    public function emailSettings(Request $request): void
    {
        $this->section('settings/email', 'Einstellungen · E-Mail');
    }

    public function saveEmail(Request $request): void
    {
        $this->verifyCsrf($request);
        $repo   = new CompanySettingsRepository();
        $before = $repo->get();

        $enc = $request->str('smtp_encryption', 'tls');
        $data = [
            'mail_from_email'       => $request->str('mail_from_email'),
            'mail_from_name'        => $request->str('mail_from_name'),
            'smtp_host'             => $request->str('smtp_host'),
            'smtp_port'             => $request->int('smtp_port', 587),
            'smtp_user'             => $request->str('smtp_user'),
            'smtp_encryption'       => in_array($enc, ['none', 'tls', 'ssl'], true) ? $enc : 'tls',
            'email_signature'       => $request->str('email_signature'),
            'invoice_email_subject' => $request->str('invoice_email_subject'),
            'invoice_email_body'    => $request->str('invoice_email_body'),
            'quote_email_subject'   => $request->str('quote_email_subject'),
            'quote_email_body'      => $request->str('quote_email_body'),
        ];
        $smtpPass = $request->str('smtp_pass');
        if ($smtpPass !== '') {
            $data['smtp_pass'] = $smtpPass;
        }

        $this->save($repo, $before, $data, '/einstellungen/email');
    }

    // ---- Datensicherung ----------------------------------------------------
    public function backupSettings(Request $request): void
    {
        $this->view('settings/backup', [
            'title'    => 'Einstellungen · Datensicherung',
            'settings' => (new CompanySettingsRepository())->get(),
            'backups'  => \Nova\Services\BackupService::listBackups($GLOBALS['nova_config']),
        ]);
    }

    /** Erstellt sofort ein Backup (und verteilt es gemäß Einstellungen). */
    public function runBackup(Request $request): void
    {
        $this->verifyCsrf($request);
        try {
            $log = \Nova\Services\BackupService::runFromSettings(
                (new CompanySettingsRepository())->get(),
                $GLOBALS['nova_config'],
                true // manueller Lauf: immer anlegen + versenden
            );
            AuditService::record('backup', 'company_settings', 1, null, ['via' => 'manuell']);
            Session::flash('success', 'Backup erstellt. ' . implode(' · ', $log));
        } catch (\Throwable $e) {
            Session::flash('error', 'Backup fehlgeschlagen: ' . $e->getMessage());
        }
        $this->redirect('/einstellungen/datensicherung');
    }

    /** Lädt ein vorhandenes Backup-Archiv herunter. */
    public function downloadBackup(Request $request): void
    {
        $path = \Nova\Services\BackupService::pathForName($GLOBALS['nova_config'], $request->str('file'));
        if ($path === null) {
            \Nova\Core\Response::notFound('Backup nicht gefunden.');
            return;
        }
        \Nova\Core\Response::download($path, basename($path), 'application/zip');
    }

    /** Löscht ein vorhandenes Backup-Archiv. */
    public function deleteBackup(Request $request): void
    {
        $this->verifyCsrf($request);
        $path = \Nova\Services\BackupService::pathForName($GLOBALS['nova_config'], $request->str('file'));
        if ($path !== null) {
            @unlink($path);
            Session::flash('success', 'Backup gelöscht.');
        } else {
            Session::flash('error', 'Backup nicht gefunden.');
        }
        $this->redirect('/einstellungen/datensicherung');
    }

    public function saveBackup(Request $request): void
    {
        $this->verifyCsrf($request);
        $repo   = new CompanySettingsRepository();
        $before = $repo->get();

        $data = [
            'backup_email' => $request->str('backup_email'),
            'backup_dir'   => $request->str('backup_dir'),
            'backup_interval_hours'       => max(0, $request->int('backup_interval_hours', 24)),
            'backup_email_interval_hours' => max(0, $request->int('backup_email_interval_hours', 24)),
            // Cloud-Ziele (nicht-geheime Felder immer übernehmen)
            'backup_webdav_url'  => $request->str('backup_webdav_url'),
            'backup_webdav_user' => $request->str('backup_webdav_user'),
            'backup_s3_endpoint' => $request->str('backup_s3_endpoint'),
            'backup_s3_region'   => $request->str('backup_s3_region'),
            'backup_s3_bucket'   => $request->str('backup_s3_bucket'),
            'backup_s3_key'      => $request->str('backup_s3_key'),
            'backup_s3_prefix'   => $request->str('backup_s3_prefix'),
            'backup_ftp_host'    => $request->str('backup_ftp_host'),
            'backup_ftp_port'    => max(1, $request->int('backup_ftp_port', 21)),
            'backup_ftp_user'    => $request->str('backup_ftp_user'),
            'backup_ftp_path'    => $request->str('backup_ftp_path'),
            'backup_ftp_tls'     => $request->bool('backup_ftp_tls') ? 1 : 0,
            'backup_dropbox_path' => $request->str('backup_dropbox_path'),
        ];
        $backupPass = $request->str('backup_password');
        if ($backupPass !== '') {
            $data['backup_password'] = $backupPass;
        }
        // Geheimnisse nur bei Eingabe aktualisieren.
        foreach (['backup_webdav_pass', 'backup_s3_secret', 'backup_ftp_pass', 'backup_dropbox_token'] as $secret) {
            $val = $request->str($secret);
            if ($val !== '') {
                $data[$secret] = $val;
            }
        }
        $token = (string) ($before['backup_token'] ?? '');
        if ($token === '' || $request->bool('regenerate_backup_token')) {
            $data['backup_token'] = bin2hex(random_bytes(24));
        }

        $this->save($repo, $before, $data, '/einstellungen/datensicherung');
    }

    // ---- Online-Zahlung ----------------------------------------------------
    public function payments(Request $request): void
    {
        $this->section('settings/payment', 'Einstellungen · Online-Zahlung');
    }

    public function savePayments(Request $request): void
    {
        $this->verifyCsrf($request);
        $repo   = new CompanySettingsRepository();
        $before = $repo->get();

        $mode = $request->str('paypal_mode', 'sandbox');
        $data = [
            'paypal_client_id'     => $request->str('paypal_client_id'),
            'paypal_mode'          => in_array($mode, ['sandbox', 'live'], true) ? $mode : 'sandbox',
            'payment_fee_category' => $request->str('payment_fee_category', 'Bankgebühren') ?: 'Bankgebühren',
        ];
        // Geheimnisse nur bei Neueingabe überschreiben (sonst leert leer den Wert).
        foreach (['stripe_secret_key', 'stripe_webhook_secret', 'paypal_secret'] as $secret) {
            if ($request->str($secret) !== '') {
                $data[$secret] = $request->str($secret);
            }
        }
        $this->save($repo, $before, $data, '/einstellungen/zahlung');
    }

    // ---- System (Updates) --------------------------------------------------
    public function system(Request $request): void
    {
        $this->view('settings/system', [
            'title'    => 'Einstellungen · System',
            'settings' => (new CompanySettingsRepository())->get(),
            'update'   => UpdateService::check(false),
        ]);
    }

    // ---- Helfer ------------------------------------------------------------
    private function section(string $template, string $title): void
    {
        $this->view($template, [
            'title'    => $title,
            'settings' => (new CompanySettingsRepository())->get(),
        ]);
    }

    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $data
     */
    private function save(CompanySettingsRepository $repo, array $before, array $data, string $redirect): void
    {
        $repo->update($data);
        AuditService::record('update', 'company_settings', 1, $before, $repo->get());
        Session::flash('success', 'Einstellungen gespeichert.');
        $this->redirect($redirect);
    }

    /** Mehrzeilige Eingabe säubern: leere Zeilen entfernen, trimmen, je Zeile. */
    private function normalizeLines(string $text): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: [])));
        return implode("\n", $lines);
    }
}
