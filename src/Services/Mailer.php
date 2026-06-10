<?php

declare(strict_types=1);

namespace Nova\Services;

/**
 * Schlanker E-Mail-Versand ohne Fremdbibliothek.
 *
 * Ist in den Einstellungen ein SMTP-Host hinterlegt, wird per SMTP versendet
 * (AUTH LOGIN, optional STARTTLS/SSL). Andernfalls greift die PHP-Funktion
 * mail(). Unterstützt Datei-Anhänge (z.B. Rechnungs-PDFs).
 */
final class Mailer
{
    /**
     * Baut Betreff und Text aus einer Vorlage (oder Standardtext) und hängt die
     * konfigurierte Signatur (bzw. einen Standard-Gruß) an. Platzhalter in
     * $vars (z.B. {nummer}) werden ersetzt.
     *
     * @param array<string,mixed> $settings
     * @param array<string,string> $vars
     * @return array{subject:string,body:string}
     */
    public static function compose(
        array $settings,
        string $subjectTpl,
        string $defaultSubject,
        string $bodyTpl,
        string $defaultBody,
        array $vars
    ): array {
        $subject = trim($subjectTpl) !== '' ? strtr($subjectTpl, $vars) : $defaultSubject;
        $body    = trim($bodyTpl) !== '' ? strtr($bodyTpl, $vars) : $defaultBody;

        $sig = trim((string) ($settings['email_signature'] ?? ''));
        $closing = $sig !== ''
            ? strtr($sig, $vars)
            : 'Mit freundlichen Grüßen' . "\n" . ((string) ($settings['owner_name'] ?? '') ?: (string) ($settings['company_name'] ?? ''));

        return ['subject' => $subject, 'body' => rtrim($body) . "\n\n" . $closing];
    }

    /**
     * @param array<string,mixed> $settings  company_settings
     * @param array<int,array{name:string,data:string,mime:string}> $attachments
     *
     * @throws \RuntimeException bei Versandfehlern
     */
    public static function send(
        array $settings,
        string $toEmail,
        string $toName,
        string $subject,
        string $body,
        array $attachments = []
    ): void {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Keine gültige Empfänger-E-Mail-Adresse.');
        }

        $fromEmail = trim((string) ($settings['mail_from_email'] ?? '')) ?: trim((string) ($settings['email'] ?? ''));
        if ($fromEmail === '') {
            throw new \RuntimeException('Keine Absenderadresse konfiguriert (Einstellungen → E-Mail).');
        }
        $fromName = trim((string) ($settings['mail_from_name'] ?? '')) ?: trim((string) ($settings['company_name'] ?? 'Nova'));

        $boundary = 'nova_' . bin2hex(random_bytes(8));
        $headers  = self::buildHeaders($fromEmail, $fromName, $toEmail, $toName, $subject, $boundary, $attachments !== []);
        $mimeBody = self::buildBody($body, $boundary, $attachments);

        $host = trim((string) ($settings['smtp_host'] ?? ''));
        if ($host !== '') {
            self::sendSmtp($settings, $fromEmail, $toEmail, $headers, $mimeBody);
            return;
        }

        // Fallback: PHP mail(). Subject und To werden separat übergeben.
        $extraHeaders = $headers;
        unset($extraHeaders['To'], $extraHeaders['Subject']);
        $headerLines = [];
        foreach ($extraHeaders as $k => $v) {
            $headerLines[] = "{$k}: {$v}";
        }
        $ok = @mail($toEmail, self::encodeHeader($subject), $mimeBody, implode("\r\n", $headerLines));
        if ($ok === false) {
            throw new \RuntimeException('Versand über mail() fehlgeschlagen (kein SMTP konfiguriert?).');
        }
    }

    /**
     * @param array<int,array{name:string,data:string,mime:string}> $attachments
     * @return array<string,string>
     */
    private static function buildHeaders(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $boundary,
        bool $hasAttachments
    ): array {
        $contentType = $hasAttachments
            ? "multipart/mixed; boundary=\"{$boundary}\""
            : 'text/plain; charset=UTF-8';

        $headers = [
            'From'         => self::formatAddress($fromEmail, $fromName),
            'To'           => self::formatAddress($toEmail, $toName),
            'Subject'      => self::encodeHeader($subject),
            'MIME-Version' => '1.0',
            'Content-Type' => $contentType,
        ];
        if (!$hasAttachments) {
            $headers['Content-Transfer-Encoding'] = 'base64';
        }
        return $headers;
    }

    /**
     * @param array<int,array{name:string,data:string,mime:string}> $attachments
     */
    private static function buildBody(string $body, string $boundary, array $attachments): string
    {
        $bodyB64 = chunk_split(base64_encode($body));

        if ($attachments === []) {
            return $bodyB64;
        }

        $parts   = [];
        $parts[] = "--{$boundary}";
        $parts[] = 'Content-Type: text/plain; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: base64';
        $parts[] = '';
        $parts[] = $bodyB64;

        foreach ($attachments as $att) {
            $name = self::encodeHeader($att['name']);
            $parts[] = "--{$boundary}";
            $parts[] = 'Content-Type: ' . $att['mime'] . '; name="' . $name . '"';
            $parts[] = 'Content-Transfer-Encoding: base64';
            $parts[] = 'Content-Disposition: attachment; filename="' . $name . '"';
            $parts[] = '';
            $parts[] = chunk_split(base64_encode($att['data']));
        }
        $parts[] = "--{$boundary}--";
        $parts[] = '';

        return implode("\r\n", $parts);
    }

    /**
     * @param array<string,mixed> $settings
     * @param array<string,string> $headers
     */
    private static function sendSmtp(array $settings, string $fromEmail, string $toEmail, array $headers, string $body): void
    {
        $host = trim((string) $settings['smtp_host']);
        $port = (int) ($settings['smtp_port'] ?? 587) ?: 587;
        $enc  = (string) ($settings['smtp_encryption'] ?? 'tls');
        $user = (string) ($settings['smtp_user'] ?? '');
        $pass = (string) ($settings['smtp_pass'] ?? '');

        $transport = $enc === 'ssl' ? "ssl://{$host}" : $host;
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client("{$transport}:{$port}", $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if ($fp === false) {
            throw new \RuntimeException("SMTP-Verbindung fehlgeschlagen: {$errstr} ({$errno}).");
        }
        stream_set_timeout($fp, 20);

        $read = static function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 512)) !== false) {
                $data .= $line;
                // Mehrzeilige Antworten enden, wenn das 4. Zeichen ein Leerzeichen ist.
                if (strlen($line) >= 4 && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $expect = static function (string $resp, string $code, string $step) {
            if (!str_starts_with($resp, $code)) {
                throw new \RuntimeException("SMTP-Fehler bei {$step}: " . trim($resp));
            }
        };
        $cmd = static function (string $line) use ($fp): void {
            fwrite($fp, $line . "\r\n");
        };

        try {
            $expect($read(), '220', 'Verbindung');
            $ehloHost = $settings['mail_from_email'] ? explode('@', (string) $settings['mail_from_email'])[1] ?? 'localhost' : 'localhost';

            $cmd('EHLO ' . $ehloHost);
            $expect($read(), '250', 'EHLO');

            if ($enc === 'tls') {
                $cmd('STARTTLS');
                $expect($read(), '220', 'STARTTLS');
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('SMTP: TLS-Verschlüsselung konnte nicht aktiviert werden.');
                }
                $cmd('EHLO ' . $ehloHost);
                $expect($read(), '250', 'EHLO (TLS)');
            }

            if ($user !== '') {
                $cmd('AUTH LOGIN');
                $expect($read(), '334', 'AUTH');
                $cmd(base64_encode($user));
                $expect($read(), '334', 'AUTH-Benutzer');
                $cmd(base64_encode($pass));
                $expect($read(), '235', 'AUTH-Passwort');
            }

            $cmd('MAIL FROM:<' . $fromEmail . '>');
            $expect($read(), '250', 'MAIL FROM');
            $cmd('RCPT TO:<' . $toEmail . '>');
            $expect($read(), '25', 'RCPT TO'); // 250/251

            $cmd('DATA');
            $expect($read(), '354', 'DATA');

            $headerLines = [];
            foreach ($headers as $k => $v) {
                $headerLines[] = "{$k}: {$v}";
            }
            // Punkt am Zeilenanfang im Body verdoppeln (Transparenz-Regel).
            $payload = implode("\r\n", $headerLines) . "\r\n\r\n" . $body;
            $payload = preg_replace('/^\./m', '..', $payload);
            fwrite($fp, $payload . "\r\n.\r\n");
            $expect($read(), '250', 'Nachricht');

            $cmd('QUIT');
        } finally {
            fclose($fp);
        }
    }

    private static function formatAddress(string $email, string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return $email;
        }
        return self::encodeHeader($name) . " <{$email}>";
    }

    /** RFC-2047-Kodierung für Header mit Nicht-ASCII-Zeichen (Umlaute). */
    private static function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value) !== 1) {
            return $value;
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
