<?php

declare(strict_types=1);

namespace Nova\Core;

/**
 * Kapselt den eingehenden HTTP-Request.
 */
final class Request
{
    /** @var array<string,mixed> */
    public array $query;
    /** @var array<string,mixed> */
    public array $post;
    /** @var array<string,mixed> */
    public array $files;
    public string $method;
    public string $path;

    public function __construct()
    {
        $this->query  = $_GET;
        $this->post   = $_POST;
        $this->files  = $_FILES;
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $uri        = $_SERVER['REQUEST_URI'] ?? '/';
        $path       = parse_url($uri, PHP_URL_PATH) ?: '/';
        $this->path = '/' . trim((string) $path, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key): bool
    {
        $value = $this->input($key);
        return in_array($value, ['1', 'on', 'true', 'yes', true, 1], true);
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** @return array<string,mixed>|null */
    public function file(string $key): ?array
    {
        $f = $this->files[$key] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $f;
    }
}
