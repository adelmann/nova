<?php

declare(strict_types=1);

namespace Nova\Core;

/**
 * Minimalistischer Router. Routen werden mit Pfad-Pattern registriert;
 * Platzhalter im Stil {id} werden als Parameter an den Handler übergeben.
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,handler:callable|array,auth:bool}> */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler, bool $auth = true, ?string $cap = null): void
    {
        $this->add('GET', $pattern, $handler, $auth, $cap);
    }

    public function post(string $pattern, callable|array $handler, bool $auth = true, ?string $cap = null): void
    {
        $this->add('POST', $pattern, $handler, $auth, $cap);
    }

    public function add(string $method, string $pattern, callable|array $handler, bool $auth, ?string $cap = null): void
    {
        $this->routes[] = [
            'method'  => $method,
            'pattern' => '/' . trim($pattern, '/'),
            'handler' => $handler,
            'auth'    => $auth,
            'cap'     => $cap,
        ];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            $params = $this->match($route['pattern'], $request->path);
            if ($params === null) {
                continue;
            }

            if ($route['auth'] && !Auth::check()) {
                Session::set('_intended', $request->path);
                Response::redirect('/login');
            }

            if (($route['cap'] ?? null) !== null && !Auth::can($route['cap'])) {
                Response::forbidden();
                return;
            }

            $this->invoke($route['handler'], $request, $params);
            return;
        }

        Response::notFound();
    }

    /**
     * @return array<string,string>|null Parameter bei Treffer, sonst null.
     */
    private function match(string $pattern, string $path): ?array
    {
        if (!str_contains($pattern, '{')) {
            return $pattern === $path ? [] : null;
        }

        $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches) !== 1) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /** @param array<string,string> $params */
    private function invoke(callable|array $handler, Request $request, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();
            $controller->$method($request, $params);
            return;
        }
        $handler($request, $params);
    }
}
