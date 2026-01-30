<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

/**
 * Resolves the CRUD/table generation variant based on stack and installed packages.
 *
 * When generating CRUD (tables, lists, forms), the resolver picks a variant so
 * generated code uses the app's table/CRUD packages (Power Grid, Inertia Tables,
 * Filament resource) instead of plain framework code.
 */
final class CrudStackResolver
{
    public const VARIANT_POWER_GRID = 'power_grid';

    public const VARIANT_INERTIA_TABLES = 'inertia_tables';

    public const VARIANT_FILAMENT_RESOURCE = 'filament_resource';

    public const VARIANT_PLAIN = 'plain';

    /**
     * Known package names that provide table/CRUD UI. Config can override or extend.
     *
     * @var array<string, string> package name => crud_variant
     */
    private const KNOWN_CRUD_PACKAGES = [
        'power-grid/laravel-powergrid' => self::VARIANT_POWER_GRID,
        'power-grid/powergrid' => self::VARIANT_POWER_GRID,
        'protonemedia/inertiajs-tables-laravel' => self::VARIANT_INERTIA_TABLES,
        'filament/filament' => self::VARIANT_FILAMENT_RESOURCE,
    ];

    public function __construct(
        private readonly PackageDiscovery $packageDiscovery,
        private readonly StackDetector $stackDetector,
    ) {}

    /**
     * Resolve the CRUD variant for the current app.
     *
     * Priority: Filament resource if Filament installed and scope is admin;
     * else Power Grid for Livewire; else Inertia Tables for Inertia; else plain.
     */
    public function resolve(?string $scope = null): string
    {
        $stack = config('architect.stack', 'auto');
        if ($stack === 'auto') {
            $stack = $this->stackDetector->detect();
        }
        $installed = $this->packageDiscovery->installed();
        $packages = $this->getCrudPackagesConfig();

        if ($scope === 'admin' && isset($installed['filament/filament'])) {
            return self::VARIANT_FILAMENT_RESOURCE;
        }

        if ($stack === 'livewire' || $stack === 'volt') {
            foreach (array_keys($packages) as $pkg) {
                if (isset($installed[$pkg]) && ($packages[$pkg] ?? '') === self::VARIANT_POWER_GRID) {
                    return self::VARIANT_POWER_GRID;
                }
            }
            if (isset($installed['power-grid/laravel-powergrid']) || isset($installed['power-grid/powergrid'])) {
                return self::VARIANT_POWER_GRID;
            }

            return self::VARIANT_PLAIN;
        }

        if ($stack === 'inertia-react' || $stack === 'inertia-vue') {
            foreach (array_keys($packages) as $pkg) {
                if (isset($installed[$pkg]) && ($packages[$pkg] ?? '') === self::VARIANT_INERTIA_TABLES) {
                    return self::VARIANT_INERTIA_TABLES;
                }
            }
            if (isset($installed['protonemedia/inertiajs-tables-laravel'])) {
                return self::VARIANT_INERTIA_TABLES;
            }

            return self::VARIANT_PLAIN;
        }

        return self::VARIANT_PLAIN;
    }

    /**
     * Known CRUD packages (config can extend).
     *
     * @return array<string, string> package name => crud_variant
     */
    public function getCrudPackagesConfig(): array
    {
        $custom = config('architect.crud_packages', []);

        return array_merge(self::KNOWN_CRUD_PACKAGES, is_array($custom) ? $custom : []);
    }
}
