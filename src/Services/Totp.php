<?php

declare(strict_types=1);

namespace Nova\Services;

/**
 * Zeitbasierte Einmalpasswörter (TOTP, RFC 6238) – kompatibel mit Google
 * Authenticator, Authy & Co. Reines PHP, ohne Fremdbibliothek.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD    = 30;

    /** Neues Base32-Secret (160 Bit). */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** Aktuellen 6-stelligen Code für ein Secret berechnen. */
    public static function code(string $secret, ?int $counter = null): string
    {
        $counter ??= intdiv(time(), self::PERIOD);
        $key     = self::base32Decode($secret);
        $binary  = "\x00\x00\x00\x00" . pack('N', $counter); // 8-Byte-Counter (big-endian)
        $hash    = hash_hmac('sha1', $binary, $key, true);

        $offset    = ord($hash[19]) & 0x0f;
        $truncated = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($truncated % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /** Prüft eine Eingabe gegen das Secret (± window Zeitfenster gegen Drift). */
    public static function verify(string $secret, string $input, int $window = 1): bool
    {
        $input = preg_replace('/\D/', '', $input) ?? '';
        if (strlen($input) !== 6 || $secret === '') {
            return false;
        }
        $counter = intdiv(time(), self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $counter + $i), $input)) {
                return true;
            }
        }
        return false;
    }

    /** otpauth://-URI für QR-Code / manuelle Einrichtung. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        return 'otpauth://totp/' . $label
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=' . self::PERIOD;
    }

    /** Secret in 4er-Gruppen für die manuelle Eingabe lesbar machen. */
    public static function formatSecret(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    public static function base32Encode(string $data): string
    {
        $out = '';
        $buffer = 0;
        $bits = 0;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= self::ALPHABET[($buffer >> $bits) & 0x1f];
            }
        }
        if ($bits > 0) {
            $out .= self::ALPHABET[($buffer << (5 - $bits)) & 0x1f];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = rtrim(strtoupper(trim($b32)), '=');
        $out = '';
        $buffer = 0;
        $bits = 0;
        for ($i = 0, $n = strlen($b32); $i < $n; $i++) {
            $v = strpos(self::ALPHABET, $b32[$i]);
            if ($v === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $v;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buffer >> $bits) & 0xff);
            }
        }
        return $out;
    }

    /**
     * Erzeugt $count Recovery-Codes (Klartext, dem Nutzer einmalig anzuzeigen).
     *
     * @return array<int,string>
     */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)));
        }
        return $codes;
    }
}
