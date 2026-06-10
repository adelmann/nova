<?php

declare(strict_types=1);

namespace Nova\Services;

use Nova\Core\DB;
use Nova\Core\Format;
use Nova\Models\CompanySettingsRepository;
use Nova\Models\InvoiceRepository;
use Nova\Models\RecurringInvoiceRepository;

/**
 * Erzeugt aus fälligen wiederkehrenden Profilen Rechnungen – als Entwurf oder,
 * bei auto_send, direkt finalisiert und per E-Mail versendet. Wird vom Cron
 * (bin/sweep.php) aufgerufen.
 */
final class RecurringService
{
    /**
     * @param array<string,mixed> $config
     * @return array<int,string> Protokollzeilen
     */
    public static function runDue(array $config): array
    {
        $repo     = new RecurringInvoiceRepository();
        $invRepo  = new InvoiceRepository();
        $settings = (new CompanySettingsRepository())->get();
        $isKU     = (int) $settings['is_kleinunternehmer'] === 1;
        $vatRate  = $isKU ? 0 : (int) $settings['default_vat_rate'];
        $log      = [];

        foreach ($repo->due() as $profile) {
            $rid   = (int) $profile['id'];
            $items = [];
            $pos   = 0;
            $net   = 0;
            foreach ($repo->items($rid) as $row) {
                $qty   = (float) $row['quantity'];
                $price = (int) $row['unit_price_cents'];
                $line  = (int) round($qty * $price);
                $net  += $line;
                $items[] = [
                    'position' => ++$pos, 'description' => (string) $row['description'],
                    'quantity' => $qty, 'unit' => (string) $row['unit'],
                    'unit_price_cents' => $price, 'vat_rate' => $vatRate, 'line_total_cents' => $line,
                ];
            }

            if ($items === []) {
                $repo->advance($rid, self::nextDate((string) $profile['next_date'], (string) $profile['interval_unit']));
                $log[] = "Profil #{$rid}: keine Positionen – übersprungen.";
                continue;
            }

            $vat   = $isKU ? 0 : (int) round($net * $vatRate / 100);
            $header = [
                'customer_id'         => (int) $profile['customer_id'],
                'status'              => 'draft',
                'is_locked'           => 0,
                'invoice_date'        => date('Y-m-d'),
                'is_kleinunternehmer' => $isKU ? 1 : 0,
                'vat_rate'            => $vatRate,
                'intro_text'          => (string) $profile['intro_text'],
                'footer_text'         => (string) ($profile['footer_text'] ?: $settings['invoice_footer_text']),
                'net_total_cents'     => $net,
                'vat_total_cents'     => $vat,
                'gross_total_cents'   => $net + $vat,
            ];
            $invId = $invRepo->createWithItems($header, $items);
            AuditService::record('create', 'invoice', $invId, ['recurring' => $rid], null);

            $note = 'Entwurf';
            if ((int) $profile['auto_send'] === 1) {
                $note = self::finalizeAndSend($invId, $invRepo, $settings, $config);
            }

            $repo->advance($rid, self::nextDate((string) $profile['next_date'], (string) $profile['interval_unit']));
            $log[] = "Profil #{$rid} → Rechnung #{$invId} ({$note}).";
        }

        return $log;
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $config
     */
    private static function finalizeAndSend(int $invId, InvoiceRepository $invRepo, array $settings, array $config): string
    {
        try {
            $number = $invRepo->finalize($invId, (string) $settings['invoice_number_format'], (int) $settings['default_payment_days']);

            // PDF archivieren.
            $inv = $invRepo->findWithCustomer($invId);
            $rel = date('Y') . '/Rechnung-' . str_replace(['/', ' '], '-', $number) . '.pdf';
            $abs = ($config['paths']['invoices'] ?? '') . '/' . $rel;
            PdfService::renderToFile('pdf/invoice', ['invoice' => $inv, 'items' => $invRepo->items($invId), 'settings' => $settings], $abs);
            $invRepo->setArchivePath($invId, $rel);

            // Per E-Mail senden, falls Kundenadresse vorhanden.
            $email = (string) DB::getInstance()->fetchColumn('SELECT email FROM customer WHERE id = :id', ['id' => (int) $inv['customer_id']]);
            if (trim($email) === '') {
                return "finalisiert {$number}, kein Versand (keine Kunden-E-Mail)";
            }
            $anrede = $inv['contact_name'] ?: $inv['company_name'];
            $vars = [
                '{kunde}' => (string) $anrede, '{nummer}' => $number,
                '{datum}' => Format::date($inv['invoice_date']),
                '{betrag}' => Format::money((int) $inv['gross_total_cents']),
                '{faellig}' => $inv['due_date'] ? Format::date($inv['due_date']) : '',
                '{firma}' => (string) ($settings['company_name'] ?? ''),
            ];
            $settings['email_signature'] = (string) ($settings['email_signature'] ?? '') ?: CompanySettingsRepository::DEFAULT_EMAIL_SIGNATURE;
            ['subject' => $subject, 'body' => $body] = Mailer::compose(
                $settings,
                (string) ($settings['invoice_email_subject'] ?? '') ?: CompanySettingsRepository::DEFAULT_INVOICE_EMAIL_SUBJECT,
                CompanySettingsRepository::DEFAULT_INVOICE_EMAIL_SUBJECT,
                (string) ($settings['invoice_email_body'] ?? '') ?: CompanySettingsRepository::DEFAULT_INVOICE_EMAIL_BODY,
                CompanySettingsRepository::DEFAULT_INVOICE_EMAIL_BODY,
                $vars
            );
            Mailer::send($settings, $email, (string) $anrede, $subject, $body, [
                ['name' => 'Rechnung-' . str_replace(['/', ' '], '-', $number) . '.pdf', 'data' => (string) file_get_contents($abs), 'mime' => 'application/pdf'],
            ]);
            return "finalisiert {$number}, versendet an {$email}";
        } catch (\Throwable $e) {
            return 'als Entwurf belassen (Auto-Versand fehlgeschlagen: ' . $e->getMessage() . ')';
        }
    }

    private static function nextDate(string $current, string $unit): string
    {
        $add = match ($unit) {
            'quarter' => '+3 months',
            'year'    => '+1 year',
            default   => '+1 month',
        };
        return date('Y-m-d', strtotime($current . ' ' . $add) ?: time());
    }
}
