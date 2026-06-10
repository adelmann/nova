<?php

declare(strict_types=1);

namespace Nova\Services;

/**
 * Optionaler KI-Assistent über die Anthropic Messages-API.
 *
 * Bewusst per nativem cURL umgesetzt (keine zusätzliche Abhängigkeit,
 * passend zum schlanken Stack). Deaktiviert, solange kein API-Schlüssel
 * (ANTHROPIC_API_KEY) gesetzt ist.
 */
final class AssistantService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public static function isEnabled(): bool
    {
        return self::apiKey() !== '';
    }

    private static function apiKey(): string
    {
        return (string) (getenv('ANTHROPIC_API_KEY') ?: '');
    }

    private static function model(): string
    {
        // Default-Modell laut Anthropic-Empfehlung; per ENV überschreibbar.
        return (string) (getenv('NOVA_AI_MODEL') ?: 'claude-opus-4-8');
    }

    /**
     * Schickt eine Anfrage an Claude und gibt den Antworttext zurück.
     *
     * @throws \RuntimeException bei Konfigurations-/API-Fehlern
     */
    public static function ask(string $userPrompt, string $systemPrompt = ''): string
    {
        if (!self::isEnabled()) {
            throw new \RuntimeException('KI-Assistent ist nicht konfiguriert (ANTHROPIC_API_KEY fehlt).');
        }

        $payload = [
            'model'      => self::model(),
            'max_tokens' => 4096,
            'messages'   => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];
        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'x-api-key: ' . self::apiKey(),
                'anthropic-version: ' . self::API_VERSION,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('Verbindung zur KI fehlgeschlagen: ' . $curlErr);
        }

        $data = json_decode((string) $response, true);
        if ($status !== 200) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $status);
            throw new \RuntimeException('KI-Fehler: ' . $msg);
        }

        // Antwort besteht aus Content-Blöcken; Texte zusammenfügen.
        $text = '';
        foreach (($data['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        return trim($text) !== '' ? $text : '(keine Antwort erhalten)';
    }
}
