<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use Illuminate\Support\Facades\Route;

final class RouteDiscovery
{
    /**
     * Get a normalized list of routes (method, uri, name, action, middleware).
     * Optionally exclude vendor routes.
     *
     * @return array<int, array{method: string, uri: string, name: string|null, action: string, middleware: array<string>}>
     */
    public function discover(bool $excludeVendor = true): array
    {
        $routes = Route::getRoutes();
        $list = [];

        foreach ($routes as $route) {
            $action = $route->getActionName();

            if ($excludeVendor && $this->isVendorRoute($action)) {
                continue;
            }

            $methods = $route->methods();
            $method = in_array('GET', $methods) && in_array('HEAD', $methods)
                ? 'GET'
                : (implode('|', $methods));

            $list[] = [
                'method' => $method,
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $action,
                'middleware' => $route->middleware(),
            ];
        }

        return $list;
    }

    private function isVendorRoute(string $action): bool
    {
        return str_starts_with($action, 'Illuminate\\')
            || str_contains($action, '\\Vendor\\')
            || $action === 'Closure';
    }
}
