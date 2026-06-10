<?php

declare(strict_types=1);

namespace Nova\Services;

use Nova\Controllers\ReminderController;
use Nova\Core\DB;
use Nova\Core\Format;
use Nova\Models\CompanySettingsRepository;
use Nova\Models\ReminderRepository;

/**
 * Automatische Zahlungserinnerungen (Stufe 1) für überfällige Rechnungen.
 * Aktiv nur, wenn auto_reminder_days > 0. Eskalationen bleiben manuell.
 */
final class ReminderService
{
    /**
     * @param array<string,mixed> $config
     * @return array<int,string> Protokollzeilen
     */
    public static function sendAuto(array $config): array
    {
        $settings = (new CompanySettingsRepository())->get();
        $days     = (int) ($settings['auto_reminder_days'] ?? 0);
        if ($days < 1) {
            return [];
        }

        $threshold = date('Y-m-d', strtotime("-{$days} days") ?: time());
        $candidates = DB::getInstance()->fetchAll(
            "SELECT i.*, c.email AS cust_email, c.contact_name, c.company_name
             FROM invoice i JOIN customer c ON c.id = i.customer_id
             WHERE i.is_locked = 1 AND i.status IN ('sent','overdue')
               AND i.due_date IS NOT NULL AND i.due_date <= :thr
               AND (i.gross_total_cents - i.paid_total_cents) > 0
               AND NOT EXISTS (SELECT 1 FROM reminder r WHERE r.invoice_id = i.id)
             ORDER BY i.due_date",
            ['thr' => $threshold]
        );

        $repo = new ReminderRepository();
        $log  = [];
        foreach ($candidates as $inv) {
            $offen = (int) $inv['gross_total_cents'] - (int) $inv['paid_total_cents'];
            $text  = self::buildText($inv, $offen, $settings);

            $id = $repo->createReminder([
                'invoice_id'    => (int) $inv['id'],
                'level'         => 1,
                'reminder_date' => date('Y-m-d'),
                'fee_cents'     => 0,
                'email_text'    => $text,
            ]);

            // PDF erzeugen und archivieren.
            $rel = date('Y') . '/Mahnung-' . str_replace(['/', ' '], '-', (string) $inv['number']) . '-Stufe1.pdf';
            $abs = ($config['paths']['invoices'] ?? '') . '/' . $rel;
            try {
                PdfService::renderToFile('pdf/reminder', [
                    'invoice' => $inv, 'level' => 1, 'offen' => $offen, 'feeCents' => 0, 'settings' => $settings,
                ], $abs);
                $repo->setPdfPath($id, $rel);
            } catch (\Throwable $e) {
                // PDF optional – ohne Anhang trotzdem versenden.
            }

            AuditService::record('create', 'reminder', $id, ['auto' => true], ['invoice_id' => (int) $inv['id']]);

            $email = trim((string) $inv['cust_email']);
            if ($email === '') {
                $log[] = "Rechnung {$inv['number']}: Erinnerung erstellt, kein Versand (keine Kunden-E-Mail).";
                continue;
            }
            $anrede = $inv['contact_name'] ?: $inv['company_name'];
            $subject = 'Zahlungserinnerung zu Rechnung ' . $inv['number'];
            $attach  = is_file($abs) ? [['name' => basename($abs), 'data' => (string) file_get_contents($abs), 'mime' => 'application/pdf']] : [];
            try {
                Mailer::send($settings, $email, (string) $anrede, $subject, $text, $attach);
                $log[] = "Rechnung {$inv['number']}: Zahlungserinnerung an {$email} versendet.";
            } catch (\RuntimeException $e) {
                $log[] = "Rechnung {$inv['number']}: Versand fehlgeschlagen ({$e->getMessage()}).";
            }
        }

        return $log;
    }

    /** @param array<string,mixed> $inv @param array<string,mixed> $settings */
    private static function buildText(array $inv, int $offen, array $settings): string
    {
        $anrede = $inv['contact_name'] ?: $inv['company_name'];
        return implode("\n", [
            "Betreff: Zahlungserinnerung zu Rechnung {$inv['number']}",
            '',
            "Sehr geehrte Damen und Herren, {$anrede},",
            '',
            "unsere Rechnung {$inv['number']} vom " . Format::date($inv['invoice_date'])
                . ' ist seit dem ' . Format::date($inv['due_date']) . ' fällig. Möglicherweise ist Ihnen die Zahlung entgangen.',
            '',
            'Bitte überweisen Sie den offenen Betrag von ' . Format::money($offen)
                . ' zeitnah auf das Konto ' . (string) $settings['iban'] . '.',
            '',
            'Sollte sich Ihre Zahlung mit diesem Schreiben überschnitten haben, betrachten Sie es bitte als gegenstandslos.',
            '',
            'Mit freundlichen Grüßen',
            $settings['owner_name'] ?: $settings['company_name'],
        ]);
    }
}
