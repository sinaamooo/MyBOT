<?php

final class Router
{
    private array $routes = [];
    private array $groupMw = [];
    private string $groupPrefix = '';

    public function get(string $path, callable|array $handler, array $mw = []): void
    {
        $this->add(['GET'], $path, $handler, $mw);
    }

    public function post(string $path, callable|array $handler, array $mw = []): void
    {
        $this->add(['POST'], $path, $handler, $mw);
    }

    public function any(string $path, callable|array $handler, array $mw = []): void
    {
        $this->add(['GET', 'POST'], $path, $handler, $mw);
    }

    public function group(string $prefix, array $mw, callable $fn): void
    {
        [$p, $m] = [$this->groupPrefix, $this->groupMw];
        $this->groupPrefix = $p . '/' . trim($prefix, '/');
        $this->groupMw = array_merge($m, $mw);
        $fn($this);
        [$this->groupPrefix, $this->groupMw] = [$p, $m];
    }

    private function add(array $methods, string $path, callable|array $handler, array $mw): void
    {
        $full = '/' . trim($this->groupPrefix . '/' . trim($path, '/'), '/');
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $full) . '$#u';
        $this->routes[] = [$methods, $regex, $handler, array_merge($this->groupMw, $mw)];
    }

    public static function currentPath(): string
    {
        if (isset($_GET['r'])) {
            $path = (string)$_GET['r'];
        } else {
            $uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
            $base = base_path();
            if ($base !== '' && str_starts_with($uri, $base)) {
                $uri = substr($uri, strlen($base));
            }
            if (str_starts_with($uri, '/index.php')) {
                $uri = substr($uri, 10);
            }
            $path = $uri;
        }
        return '/' . trim($path, '/');
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $path = self::currentPath();
        $GLOBALS['__path'] = $path;
        $allowed = false;

        foreach ($this->routes as [$methods, $regex, $handler, $mw]) {
            if (!preg_match($regex, $path, $m)) {
                continue;
            }
            if (!in_array($method, $methods, true)) {
                $allowed = true;
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $this->runMiddleware($mw, $method);
            if (is_array($handler)) {
                [$class, $fn] = $handler;
                $out = (new $class())->$fn(...array_values($params));
            } else {
                $out = $handler(...array_values($params));
            }
            if (is_string($out)) {
                echo $out;
            } elseif (is_array($out)) {
                json($out);
            }
            return;
        }
        abort($allowed ? 405 : 404);
    }

    private function runMiddleware(array $mw, string $method): void
    {
        if ($method === 'POST' && !in_array('nocsrf', $mw, true) && !csrf_valid()) {
            if (is_ajax()) {
                json(['ok' => false, 'message' => 'نشست شما منقضی شده است. صفحه را مجدداً بارگذاری کنید.'], 419);
            }
            flash('error', 'نشست شما منقضی شده است. لطفاً دوباره تلاش کنید.');
            back();
        }
        foreach ($mw as $m) {
            switch ($m) {
                case 'auth':
                    if (!Auth::check()) {
                        if (is_ajax()) {
                            json(['ok' => false, 'message' => 'ابتدا وارد حساب کاربری شوید.', 'login' => url('login')], 401);
                        }
                        redirect(url('login', ['next' => $_SERVER['REQUEST_URI'] ?? '']), true);
                    }
                    break;
                case 'guest':
                    if (Auth::check()) {
                        redirect(is_admin() ? 'admin' : 'dashboard');
                    }
                    break;
                case 'admin':
                    if (!Auth::check()) {
                        redirect(url('login', ['next' => $_SERVER['REQUEST_URI'] ?? '']), true);
                    }
                    if (!is_admin()) {
                        abort(403, 'شما به این بخش دسترسی ندارید.');
                    }
                    break;
            }
        }
    }
}
