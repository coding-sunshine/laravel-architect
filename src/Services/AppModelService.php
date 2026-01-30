<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class AppModelService
{
    private const FINGERPRINT_CACHE_KEY = 'architect:app:fingerprint';

    private const FINGERPRINT_CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly RouteDiscovery $routeDiscovery,
        private readonly SchemaDiscovery $schemaDiscovery,
        private readonly PackageDiscovery $packageDiscovery,
        private readonly StackDetector $stackDetector,
        private readonly ImportService $importService,
    ) {}

    /**
     * Aggregate app model: routes, db_schema, models, actions, pages, packages, stack, conventions.
     *
     * @return array{
     *     routes: array<int, array{method: string, uri: string, name: string|null, action: string, middleware: array<string>}>,
     *     db_schema: array<string, array{columns: array<string>}>,
     *     models: array<int, array{name: string, table: string, columns?: array<string>}>,
     *     actions: array<int, string>,
     *     pages: array<int, string>,
     *     packages: array<string, string>,
     *     stack: string,
     *     conventions: array<string, mixed>,
     * }
     */
    public function appModel(): array
    {
        $imported = $this->importService->import(null);
        $dbSchema = $this->schemaDiscovery->discover();
        $stack = config('architect.stack', 'auto');
        if ($stack === 'auto') {
            $stack = $this->stackDetector->detect();
        }

        $modelNames = array_keys($imported['models']);
        $models = [];
        foreach ($modelNames as $name) {
            $table = Str::snake(Str::plural($name));
            $columns = $dbSchema[$table]['columns'] ?? [];
            $models[] = [
                'name' => $name,
                'table' => $table,
                'columns' => $columns,
            ];
        }

        return [
            'routes' => $this->routeDiscovery->discover(true),
            'db_schema' => $dbSchema,
            'models' => $models,
            'actions' => array_keys($imported['actions']),
            'pages' => array_keys($imported['pages']),
            'packages' => $this->packageDiscovery->installed(),
            'stack' => $stack,
            'conventions' => config('architect.conventions', []),
        ];
    }

    /**
     * Small subset for AI: stack, models with table/columns, route count, package names, conventions.
     * Cacheable. No raw code.
     *
     * @return array{
     *     stack: string,
     *     models: array<int, array{name: string, table: string, columns: array<string>}>,
     *     route_count: int,
     *     route_sample: array<int, array{method: string, uri: string, name: string|null}>,
     *     package_names: array<int, string>,
     *     conventions: array<string, mixed>,
     * }
     */
    public function fingerprint(): array
    {
        $cacheKey = $this->fingerprintCacheKey();

        return Cache::remember($cacheKey, self::FINGERPRINT_CACHE_TTL_SECONDS, function (): array {
            $app = $this->appModel();

            $routeSample = array_slice(array_map(function (array $r): array {
                return [
                    'method' => $r['method'],
                    'uri' => $r['uri'],
                    'name' => $r['name'],
                ];
            }, $app['routes']), 0, 50);

            return [
                'stack' => $app['stack'],
                'models' => $app['models'],
                'route_count' => count($app['routes']),
                'route_sample' => $routeSample,
                'package_names' => array_keys($app['packages']),
                'conventions' => $app['conventions'],
            ];
        });
    }

    private function fingerprintCacheKey(): string
    {
        $lockPath = base_path('composer.lock');
        $mtime = File::exists($lockPath) ? (string) filemtime($lockPath) : '0';

        return self::FINGERPRINT_CACHE_KEY.':'.md5(base_path().$mtime);
    }
}
