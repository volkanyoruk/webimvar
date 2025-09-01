<?php
/**
 * Çok basit Router
 * - GET/POST rota ekleme
 * - Orta katman (middleware) desteği
 * - dispatch() ve run() (alias) mevcut
 */
class Router
{
    private $routes = [
        'GET'  => [],
        'POST' => [],
    ];

    /**
     * Rota ekle
     */
    public function add($method, $path, callable $handler, array $middleware = [])
    {
        $method = strtoupper($method);
        $path   = '/' . ltrim($path, '/'); // normalize
        $this->routes[$method][$path] = [
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function get($path, callable $handler, array $middleware = [])
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post($path, callable $handler, array $middleware = [])
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * Eşleşen rotayı bul
     */
    private function match($method, $path)
    {
        $method = strtoupper($method);
        $path   = '/' . ltrim($path, '/'); // normalize

        if (isset($this->routes[$method][$path])) {
            return $this->routes[$method][$path];
        }
        return null;
    }

    /**
     * Middleware zincirini çalıştır
     * Middleware false dönerse akışı keser.
     */
    private function runMiddleware(array $middlewares)
    {
        foreach ($middlewares as $mw) {
            if (is_callable($mw)) {
                $ok = $mw();
                if ($ok === false) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * İsteği çalıştır
     */
    public function dispatch($path = null, $method = null)
    {
        if ($path === null) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        }
        if ($method === null) {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        }

        // normalize: çift slash temizle
        $path = preg_replace('#//+#', '/', $path);

        $route = $this->match($method, $path);
        if ($route === null) {
            // Not Found
            if (class_exists('Response') && method_exists('Response', 'notFound')) {
                return Response::notFound("Not Found: {$path}");
            }
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
            echo "Not Found: {$path}";
            return;
        }

        // Middleware
        if (!$this->runMiddleware($route['middleware'])) {
            return;
        }

        // Handler
        $handler = $route['handler'];
        return $handler();
    }

    /**
     * Eski çağrıları kırmamak için alias.
     */
    public function run($path = null, $method = null)
    {
        return $this->dispatch($path, $method);
    }
}