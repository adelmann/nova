<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\DB;
use Nova\Core\Format;
use Nova\Core\Request;
use Nova\Services\AssistantService;
use Nova\Services\EuerService;

final class AssistantController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('assistant/index', [
            'title'   => 'KI-Assistent',
            'enabled' => AssistantService::isEnabled(),
            'answer'  => null,
            'prompt'  => '',
        ]);
    }

    public function ask(Request $request): void
    {
        $this->verifyCsrf($request);

        $prompt = $request->str('prompt');
        $answer = null;
        $error  = null;

        if ($prompt === '') {
            $error = 'Bitte eine Frage eingeben.';
        } else {
            try {
                $answer = AssistantService::ask($prompt, $this->systemPrompt());
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        $this->view('assistant/index', [
            'title'   => 'KI-Assistent',
            'enabled' => AssistantService::isEnabled(),
            'answer'  => $answer,
            'error'   => $error,
            'prompt'  => $prompt,
        ]);
    }

    /**
     * Systemkontext mit echten Geschäftszahlen, damit der Assistent Fragen wie
     * „Wie lief mein Monat?" beantworten kann.
     */
    private function systemPrompt(): string
    {
        $db    = DB::getInstance();
        $year  = (int) date('Y');
        $s     = EuerService::summary($year);

        $openInvoices = (int) $db->fetchColumn("SELECT COUNT(*) FROM invoice WHERE status IN ('sent','overdue')");
        $openSum      = (int) $db->fetchColumn("SELECT COALESCE(SUM(gross_total_cents-paid_total_cents),0) FROM invoice WHERE status IN ('sent','overdue')");
        $openExpenses = (int) $db->fetchColumn("SELECT COUNT(*) FROM expense WHERE status='open'");

        return implode("\n", [
            'Du bist der Buchhaltungs-Assistent von „Nova", einem Tool für ein deutsches Kleingewerbe.',
            'Antworte knapp, freundlich und auf Deutsch. Du gibst KEINE verbindliche Steuerberatung; weise bei steuerlichen Fragen darauf hin.',
            '',
            'Aktuelle Geschäftszahlen (Jahr ' . $year . '):',
            '- Einnahmen: ' . Format::money($s['income']),
            '- Ausgaben: ' . Format::money($s['expense']),
            '- Gewinn (EÜR): ' . Format::money($s['profit']),
            '- Offene Rechnungen: ' . $openInvoices . ' (' . Format::money($openSum) . ' ausstehend)',
            '- Unbezahlte Ausgaben: ' . $openExpenses,
        ]);
    }
}
