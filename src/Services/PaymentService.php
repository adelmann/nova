<?php

declare(strict_types=1);

namespace Nova\Services;

use Nova\Core\DB;
use Nova\Models\ExpenseRepository;
use Nova\Models\InvoiceRepository;
use Nova\Models\PaymentRepository;

/**
 * Online-Zahlung (opt-in). Erzeugt anbieterspezifische Bezahl-Links und verbucht
 * eingehende Zahlungen idempotent: Bruttobetrag als Einnahme + Anbietergebühr als
 * Ausgabe (EÜR). Aktuell: Stripe. PayPal folgt mit gleicher Struktur.
 */
final class PaymentService
{
    /**
     * Konfigurierte/aktive Anbieter.
     *
     * @param array<string,mixed> $settings
     * @return array<int,string>
     */
    /** Bereits umgesetzte Anbieter (PayPal folgt). */
    private const IMPLEMENTED = ['stripe'];

    public static function providers(array $settings): array
    {
        $p = [];
        if (in_array('stripe', self::IMPLEMENTED, true) && trim((string) ($settings['stripe_secret_key'] ?? '')) !== '') {
            $p[] = 'stripe';
        }
        if (in_array('paypal', self::IMPLEMENTED, true)
            && trim((string) ($settings['paypal_client_id'] ?? '')) !== '' && trim((string) ($settings['paypal_secret'] ?? '')) !== '') {
            $p[] = 'paypal';
        }
        return $p;
    }

    public static function label(string $provider): string
    {
        return match ($provider) {
            'stripe' => 'Kreditkarte (Stripe)',
            'paypal' => 'PayPal',
            default  => $provider,
        };
    }

    /**
     * Erzeugt einen Bezahl-Link beim Anbieter und gibt die Weiterleitungs-URL zurück.
     *
     * @param array<string,mixed> $invoice  Rechnung inkl. Beträge/Nummer
     * @param array<string,mixed> $settings
     * @throws \RuntimeException
     */
    public static function checkoutUrl(string $provider, array $invoice, array $settings, string $baseUrl): string
    {
        $amount = (int) $invoice['gross_total_cents'] - (int) $invoice['paid_total_cents'];
        if ($amount <= 0) {
            throw new \RuntimeException('Diese Rechnung ist bereits bezahlt.');
        }
        $token   = (string) $invoice['pay_token'];
        $success = $baseUrl . '/zahlen/' . $token . '/erfolg';
        $cancel  = $baseUrl . '/zahlen/' . $token;

        return match ($provider) {
            'stripe' => self::stripeCheckout($invoice, $amount, $settings, $success, $cancel),
            default  => throw new \RuntimeException('Anbieter nicht verfügbar: ' . $provider),
        };
    }

    /**
     * Bucht eine erfolgreiche Zahlung: Einnahme (Brutto) + Gebühr (Ausgabe).
     * Idempotent über external_ref. Gibt true bei Neubuchung zurück.
     *
     * @param array<string,mixed> $settings
     */
    public static function bookPaid(int $invoiceId, int $grossCents, int $feeCents, string $provider, string $externalRef, array $settings): bool
    {
        $payments = new PaymentRepository();
        if ($payments->existsExternalRef($externalRef)) {
            return false; // schon verbucht (Webhook-Wiederholung)
        }
        $invRepo = new InvoiceRepository();
        $inv = $invRepo->find($invoiceId);
        if ($inv === null || (int) $inv['is_locked'] !== 1 || $inv['status'] === 'cancelled') {
            return false;
        }
        $today = date('Y-m-d');
        $label = self::label($provider);

        // 1. Einnahme = Bruttobetrag.
        $pid = $payments->createPayment($invoiceId, $today, $grossCents, $label, 'Online-Zahlung', $externalRef);
        LedgerService::recordIncome($today, $grossCents, 'payment', $pid, 'Umsatzerlöse', 'Online-Zahlung Rechnung ' . $inv['number']);
        $invRepo->recalcPaymentStatus($invoiceId);

        // 2. Anbietergebühr = Betriebsausgabe.
        if ($feeCents > 0) {
            $cat = (string) ($settings['payment_fee_category'] ?? 'Bankgebühren');
            $exRepo = new ExpenseRepository();
            $exId = $exRepo->createFromInput([
                'expense_date' => $today,
                'supplier'     => $label,
                'tax_category' => $cat,
                'category'     => 'Zahlungsgebühr',
                'amount'       => number_format($feeCents / 100, 2, ',', ''),
                'status'       => 'paid',
                'method'       => $label,
                'note'         => 'Gebühr zu Rechnung ' . $inv['number'],
            ]);
            $e = $exRepo->find($exId);
            LedgerService::syncExpense($exId, -$feeCents, $e['expense_date'], $cat, 'Zahlungsgebühr ' . $inv['number']);
        }

        AuditService::record('payment', 'invoice', $invoiceId, null, ['provider' => $provider, 'gross' => $grossCents, 'fee' => $feeCents]);
        return true;
    }

    // ---- Stripe ------------------------------------------------------------
    /** @param array<string,mixed> $invoice @param array<string,mixed> $settings */
    private static function stripeCheckout(array $invoice, int $amount, array $settings, string $success, string $cancel): string
    {
        $params = [
            'mode'                => 'payment',
            'success_url'         => $success,
            'cancel_url'          => $cancel,
            'client_reference_id' => (string) $invoice['id'],
            'metadata[invoice_id]' => (string) $invoice['id'],
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => 'eur',
            'line_items[0][price_data][unit_amount]' => (string) $amount,
            'line_items[0][price_data][product_data][name]' => 'Rechnung ' . ($invoice['number'] ?: (string) $invoice['id']),
        ];
        [$status, $body] = self::stripeApi('POST', 'checkout/sessions', (string) $settings['stripe_secret_key'], $params);
        $json = json_decode($body, true);
        if ($status !== 200 || empty($json['url'])) {
            throw new \RuntimeException('Stripe-Fehler: ' . ($json['error']['message'] ?? ('HTTP ' . $status)));
        }
        return (string) $json['url'];
    }

    /** Stripe-Signatur des Webhooks prüfen (HMAC-SHA256). */
    public static function verifyStripeSignature(string $payload, string $sigHeader, string $secret): bool
    {
        if ($secret === '' || $sigHeader === '') {
            return false;
        }
        $t = null; $v1 = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$k, $val] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') { $t = $val; }
            if ($k === 'v1') { $v1[] = $val; }
        }
        if ($t === null || $v1 === []) {
            return false;
        }
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        foreach ($v1 as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ermittelt die Stripe-Gebühr zu einem PaymentIntent (in Cent).
     *
     * @param array<string,mixed> $settings
     */
    public static function stripeFee(string $paymentIntentId, array $settings): int
    {
        if ($paymentIntentId === '') {
            return 0;
        }
        [$status, $body] = self::stripeApi(
            'GET',
            'payment_intents/' . rawurlencode($paymentIntentId) . '?expand[]=latest_charge.balance_transaction',
            (string) $settings['stripe_secret_key']
        );
        if ($status !== 200) {
            return 0;
        }
        $json = json_decode($body, true);
        return (int) ($json['latest_charge']['balance_transaction']['fee'] ?? 0);
    }

    /**
     * @param array<string,string> $params
     * @return array{0:int,1:string}
     */
    private static function stripeApi(string $method, string $path, string $secret, array $params = []): array
    {
        $ch = curl_init('https://api.stripe.com/v1/' . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $secret],
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
        }
        curl_setopt_array($ch, $opts);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$status, $body === false ? '' : (string) $body];
    }
}
