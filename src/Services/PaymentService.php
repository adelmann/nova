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
    /** Bereits umgesetzte Anbieter. */
    private const IMPLEMENTED = ['stripe', 'paypal'];

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
            'paypal' => self::paypalCheckout($invoice, $amount, $settings, $success . '?provider=paypal', $cancel),
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

    // ---- PayPal (Orders v2, Capture bei Rückkehr) --------------------------
    /** Live- bzw. Sandbox-Endpunkt je nach konfiguriertem Modus. */
    private static function paypalBase(array $settings): string
    {
        return ($settings['paypal_mode'] ?? 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /** Beträge in Cent als PayPal-Dezimalstring ("119.00"). */
    private static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** Dezimalstring (PayPal) zurück in Cent. */
    private static function toCents(string $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    /** @param array<string,mixed> $invoice @param array<string,mixed> $settings */
    private static function paypalCheckout(array $invoice, int $amount, array $settings, string $success, string $cancel): string
    {
        $token = self::paypalToken($settings);
        $order = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'custom_id'   => (string) $invoice['id'],
                'description' => 'Rechnung ' . ($invoice['number'] ?: (string) $invoice['id']),
                'amount'      => ['currency_code' => 'EUR', 'value' => self::decimal($amount)],
            ]],
            'application_context' => [
                'return_url'  => $success,
                'cancel_url'  => $cancel,
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ],
        ];
        [$status, $body] = self::paypalApi('POST', '/v2/checkout/orders', $settings, $token, $order);
        $json = json_decode($body, true);
        if (($status !== 200 && $status !== 201) || empty($json['links'])) {
            throw new \RuntimeException('PayPal-Fehler: ' . ($json['message'] ?? ('HTTP ' . $status)));
        }
        foreach ($json['links'] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                return (string) $link['href'];
            }
        }
        throw new \RuntimeException('PayPal: kein Freigabe-Link erhalten.');
    }

    /**
     * Schließt eine PayPal-Zahlung ab (Capture des Orders) und gibt die für die
     * Buchung nötigen Werte zurück: invoiceId, Brutto- und Gebührbetrag (Cent)
     * sowie eine eindeutige Referenz. Gibt null zurück, wenn nicht abgeschlossen.
     *
     * @param array<string,mixed> $settings
     * @return array{invoice_id:int,gross:int,fee:int,ref:string}|null
     */
    public static function paypalCapture(string $orderId, array $settings): ?array
    {
        if ($orderId === '') {
            return null;
        }
        $token = self::paypalToken($settings);
        [$status, $body] = self::paypalApi('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', $settings, $token, []);
        $json = json_decode($body, true);
        if (($status !== 200 && $status !== 201) || ($json['status'] ?? '') !== 'COMPLETED') {
            return null;
        }
        $unit    = $json['purchase_units'][0] ?? [];
        $capture = $unit['payments']['captures'][0] ?? [];
        $invoiceId = (int) ($unit['custom_id'] ?? $capture['custom_id'] ?? 0);
        $gross   = self::toCents((string) ($capture['amount']['value'] ?? '0'));
        $fee     = self::toCents((string) ($capture['seller_receivable_breakdown']['paypal_fee']['value'] ?? '0'));
        $ref     = 'paypal:' . (string) ($capture['id'] ?? $orderId);
        if ($invoiceId === 0 || $gross <= 0) {
            return null;
        }
        return ['invoice_id' => $invoiceId, 'gross' => $gross, 'fee' => $fee, 'ref' => $ref];
    }

    /** OAuth2-Access-Token (Client-Credentials). */
    private static function paypalToken(array $settings): string
    {
        $ch = curl_init(self::paypalBase($settings) . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERPWD        => (string) $settings['paypal_client_id'] . ':' . (string) $settings['paypal_secret'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode($body === false ? '' : (string) $body, true);
        if ($status !== 200 || empty($json['access_token'])) {
            throw new \RuntimeException('PayPal-Anmeldung fehlgeschlagen (HTTP ' . $status . ').');
        }
        return (string) $json['access_token'];
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,mixed>|null $jsonBody
     * @return array{0:int,1:string}
     */
    private static function paypalApi(string $method, string $path, array $settings, string $token, ?array $jsonBody = null): array
    {
        $ch = curl_init(self::paypalBase($settings) . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ];
        if ($jsonBody !== null) {
            $opts[CURLOPT_POSTFIELDS] = $jsonBody === [] ? '{}' : (string) json_encode($jsonBody);
        }
        curl_setopt_array($ch, $opts);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$status, $body === false ? '' : (string) $body];
    }
}
