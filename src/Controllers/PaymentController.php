<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Models\CompanySettingsRepository;
use Nova\Models\InvoiceRepository;
use Nova\Services\PaymentService;

/**
 * Öffentliche Bezahlseite + Webhooks der Zahlungsanbieter. Keine Anmeldung –
 * der Zugriff erfolgt über den nicht erratbaren Bezahl-Token bzw. die
 * signaturgeprüften Webhooks.
 */
final class PaymentController extends Controller
{
    /** Bezahlseite für den Kunden (Token-Link aus der Rechnungs-E-Mail). */
    public function pay(Request $request, array $params): void
    {
        $repo = new InvoiceRepository();
        $inv  = $repo->findByPayToken((string) $params['token']);
        if ($inv === null) {
            Response::notFound('Rechnung nicht gefunden.');
            return;
        }
        $settings = (new CompanySettingsRepository())->get();
        $this->view('payment/pay', [
            'title'     => 'Rechnung bezahlen',
            'invoice'   => $inv,
            'settings'  => $settings,
            'providers' => PaymentService::providers($settings),
            'open'      => (int) $inv['gross_total_cents'] - (int) $inv['paid_total_cents'],
        ], layout: null);
    }

    /** Startet die Zahlung beim gewählten Anbieter und leitet dorthin weiter. */
    public function start(Request $request, array $params): void
    {
        $this->verifyCsrf($request);
        $repo = new InvoiceRepository();
        $inv  = $repo->findByPayToken((string) $params['token']);
        if ($inv === null) {
            Response::notFound('Rechnung nicht gefunden.');
            return;
        }
        $settings = (new CompanySettingsRepository())->get();
        $provider = $request->str('provider');
        if (!in_array($provider, PaymentService::providers($settings), true)) {
            Session::flash('error', 'Zahlungsart nicht verfügbar.');
            $this->redirect('/zahlen/' . $inv['pay_token']);
        }
        try {
            $url = PaymentService::checkoutUrl($provider, $inv, $settings, $this->baseUrl());
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/zahlen/' . $inv['pay_token']);
        }
        Response::redirect($url);
    }

    /**
     * Erfolgsseite nach Rückkehr vom Anbieter. Bei Stripe erfolgt die Buchung
     * per Webhook; bei PayPal wird die Zahlung hier (bei Rückkehr) abgeschlossen
     * und verbucht – die Capture-Antwort liefert auch die Gebühr.
     */
    public function success(Request $request, array $params): void
    {
        $inv = (new InvoiceRepository())->findByPayToken((string) $params['token']);
        if ($inv === null) {
            Response::notFound('Rechnung nicht gefunden.');
            return;
        }

        if ($request->str('provider') === 'paypal') {
            $orderId  = $request->str('token'); // PayPal hängt ?token=<orderId> an
            $settings = (new CompanySettingsRepository())->get();
            try {
                $cap = PaymentService::paypalCapture($orderId, $settings);
                if ($cap !== null) {
                    PaymentService::bookPaid($cap['invoice_id'], $cap['gross'], $cap['fee'], 'paypal', $cap['ref'], $settings);
                }
            } catch (\RuntimeException $e) {
                // Buchung scheitert lautlos – die Bezahlseite bleibt aufrufbar; ein
                // erneuter Versuch ist idempotent über die external_ref abgesichert.
            }
        }

        $this->view('payment/success', [
            'title'   => 'Zahlung erhalten',
            'invoice' => $inv,
        ], layout: null);
    }

    /** Stripe-Webhook: prüft Signatur und verbucht die Zahlung. */
    public function webhookStripe(Request $request): void
    {
        header('Content-Type: text/plain; charset=UTF-8');
        $payload  = (string) file_get_contents('php://input');
        $sig      = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        $settings = (new CompanySettingsRepository())->get();
        $secret   = (string) ($settings['stripe_webhook_secret'] ?? '');

        if (!PaymentService::verifyStripeSignature($payload, $sig, $secret)) {
            http_response_code(400);
            echo "invalid signature\n";
            return;
        }
        $event = json_decode($payload, true);
        if (($event['type'] ?? '') !== 'checkout.session.completed') {
            echo "ignored\n";
            return;
        }
        $session = $event['data']['object'] ?? [];
        $invoiceId = (int) ($session['metadata']['invoice_id'] ?? $session['client_reference_id'] ?? 0);
        $gross     = (int) ($session['amount_total'] ?? 0);
        $piId      = (string) ($session['payment_intent'] ?? '');
        if ($invoiceId === 0 || $gross <= 0) {
            echo "no invoice\n";
            return;
        }
        $fee = PaymentService::stripeFee($piId, $settings);
        PaymentService::bookPaid($invoiceId, $gross, $fee, 'stripe', 'stripe:' . ($session['id'] ?? $piId), $settings);
        echo "ok\n";
    }

    private function baseUrl(): string
    {
        $url = rtrim((string) ($GLOBALS['nova_config']['app_url'] ?? ''), '/');
        if ($url !== '') {
            return $url;
        }
        $scheme = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
