<?php

declare(strict_types=1);

namespace Nova\Services;

use Nova\Core\Version;

/**
 * Prüft GitHub auf neue Releases und liefert die Installationsquelle für den
 * 1-Klick-Updater. Netzwerkaufrufe werden gecacht, damit normale Seitenaufrufe
 * nie blockieren (die Views lesen nur den Cache via cached()).
 */
final class UpdateService
{
    private const CACHE_TTL = 21600; // 6 Stunden

    private static function repo(): string
    {
        return (string) ($GLOBALS['nova_config']['github_repo'] ?? '');
    }

    private static function cacheFile(): string
    {
        $dir = ($GLOBALS['nova_config']['paths']['storage'] ?? sys_get_temp_dir()) . '/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/update.json';
    }

    /**
     * Liest nur den zwischengespeicherten Stand (kein Netzwerk).
     *
     * @return array<string,mixed>|null
     */
    public static function cached(): ?array
    {
        $file = self::cacheFile();
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /** Cache ist älter als die TTL (oder fehlt). */
    public static function isStale(): bool
    {
        $c = self::cached();
        return $c === null || (int) ($c['checked_at'] ?? 0) < (time() - self::CACHE_TTL);
    }

    /**
     * Fragt GitHub ab (sofern fällig oder erzwungen) und aktualisiert den Cache.
     *
     * @return array<string,mixed>
     */
    public static function check(bool $force = false): array
    {
        if (!$force && !self::isStale()) {
            return self::cached() ?? self::emptyResult();
        }

        $result = self::emptyResult();
        $repo   = self::repo();
        if ($repo === '') {
            $result['error'] = 'Kein GitHub-Repository konfiguriert.';
            self::write($result);
            return $result;
        }

        [$status, $body] = self::httpGet("https://api.github.com/repos/{$repo}/releases/latest");

        if ($status === 404) {
            // Noch kein Release veröffentlicht – kein Fehler.
            self::write($result);
            return $result;
        }
        if ($status !== 200 || $body === '') {
            $result['error'] = 'GitHub nicht erreichbar (HTTP ' . $status . ').';
            // Fehler nicht cachen, damit der nächste Versuch es erneut probiert.
            return $result;
        }

        $json = json_decode($body, true);
        if (!is_array($json) || empty($json['tag_name'])) {
            $result['error'] = 'Unerwartete Antwort von GitHub.';
            return $result;
        }

        $latest = ltrim((string) $json['tag_name'], 'vV');
        $result['latest']      = $latest;
        $result['url']         = (string) ($json['html_url'] ?? '');
        $result['notes']       = (string) ($json['body'] ?? '');
        $result['published_at']= (string) ($json['published_at'] ?? '');
        $result['zip_url']     = self::pickZip($json);
        $result['has_update']  = version_compare($latest, Version::CURRENT, '>');
        self::write($result);
        return $result;
    }

    /** Wählt ein passendes ZIP: bevorzugt ein .zip-Release-Asset, sonst den Source-Zipball. */
    private static function pickZip(array $json): string
    {
        foreach (($json['assets'] ?? []) as $asset) {
            if (str_ends_with(strtolower((string) ($asset['name'] ?? '')), '.zip')
                && !empty($asset['browser_download_url'])) {
                return (string) $asset['browser_download_url'];
            }
        }
        return (string) ($json['zipball_url'] ?? '');
    }

    /** @return array{0:int,1:string} [HTTP-Status, Body] */
    private static function httpGet(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: Nova-Updater',
                'Accept: application/vnd.github+json',
            ],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$status, $body === false ? '' : (string) $body];
    }

    /** @return array<string,mixed> */
    private static function emptyResult(): array
    {
        return [
            'current'    => Version::CURRENT,
            'latest'     => null,
            'has_update' => false,
            'url'        => '',
            'notes'      => '',
            'zip_url'    => '',
            'checked_at' => time(),
            'error'      => null,
        ];
    }

    /** @param array<string,mixed> $result */
    private static function write(array $result): void
    {
        $result['current']    = Version::CURRENT;
        $result['checked_at'] = time();
        @file_put_contents(self::cacheFile(), json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
