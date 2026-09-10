<?php
/**
 * Mova CMS - Router
 */

namespace Mova\Core;

class Router
{
    private array $routes = [];
    private array $params = [];

    public function get(string $pattern, $handler): self
    {
        return $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, $handler): self
    {
        return $this->add('POST', $pattern, $handler);
    }

    public function any(string $pattern, $handler): self
    {
        $this->add('GET', $pattern, $handler);
        $this->add('POST', $pattern, $handler);
        return $this;
    }

    private function add(string $method, string $pattern, $handler): self
    {
        $this->routes[] = [
            'method'  => $method,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path   = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->match($route['pattern'], $path);
            if ($params !== false) {
                $this->params = $params;
                return $this->invoke($route['handler'], $request, $params);
            }
        }

        return Response::make('Not Found', 404);
    }

    private function match(string $pattern, string $path)
    {
        // Convert {param} to named capture groups
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $path, $matches)) {
            return false;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $params[$key] = $value;
            }
        }
        return $params;
    }

    private function invoke($handler, Request $request, array $params): Response
    {
        if (is_callable($handler)) {
            $result = $handler($request, $params);
        } elseif (is_string($handler) && strpos($handler, '@') !== false) {
            [$class, $method] = explode('@', $handler, 2);
            if (!class_exists($class)) {
                $class = 'Mova\\' . $class;
            }
            $controller = new $class();
            $result = $controller->$method($request, $params);
        } else {
            return Response::make('Invalid handler', 500);
        }

        if ($result instanceof Response) {
            return $result;
        }

        return Response::make((string) $result);
    }

    public function params(): array
    {
        return $this->params;
    }
}
