<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use CodingSunshine\Architect\Support\Draft;

/**
 * Validates draft schema against installed packages.
 *
 * Features:
 * - Missing dependency warnings
 * - Package version warnings
 * - Incompatibility detection
 * - Missing config reminders
 * - Publish config prompts
 * - Migration reminders
 */
final class PackageValidationService
{
    /**
     * Schema keys that require specific packages.
     *
     * @var array<string, array{package: string, message: string, install_command?: string}>
     */
    private const REQUIRED_PACKAGES = [
        'media' => [
            'package' => 'spatie/laravel-medialibrary',
            'message' => 'The "media" feature requires spatie/laravel-medialibrary',
            'install_command' => 'composer require spatie/laravel-medialibrary',
        ],
        'searchable' => [
            'package' => 'laravel/scout',
            'message' => 'The "searchable" feature requires laravel/scout',
            'install_command' => 'composer require laravel/scout',
        ],
        'billable' => [
            'package' => 'laravel/cashier',
            'message' => 'The "billable" feature requires laravel/cashier',
            'install_command' => 'composer require laravel/cashier',
        ],
        'filament' => [
            'package' => 'filament/filament',
            'message' => 'The "filament" feature requires filament/filament',
            'install_command' => 'composer require filament/filament',
        ],
        'sluggable' => [
            'package' => 'spatie/laravel-sluggable',
            'message' => 'The "sluggable" feature requires spatie/laravel-sluggable',
            'install_command' => 'composer require spatie/laravel-sluggable',
        ],
        'tags' => [
            'package' => 'spatie/laravel-tags',
            'message' => 'The "tags" feature requires spatie/laravel-tags',
            'install_command' => 'composer require spatie/laravel-tags',
        ],
        'activity_log' => [
            'package' => 'spatie/laravel-activitylog',
            'message' => 'The "activity_log" feature requires spatie/laravel-activitylog',
            'install_command' => 'composer require spatie/laravel-activitylog',
        ],
        'roles' => [
            'package' => 'spatie/laravel-permission',
            'message' => 'The "roles" feature requires spatie/laravel-permission',
            'install_command' => 'composer require spatie/laravel-permission',
        ],
        'permissions' => [
            'package' => 'spatie/laravel-permission',
            'message' => 'The "permissions" feature requires spatie/laravel-permission',
            'install_command' => 'composer require spatie/laravel-permission',
        ],
        'exportable' => [
            'package' => 'maatwebsite/excel',
            'message' => 'The "exportable" feature requires maatwebsite/excel',
            'install_command' => 'composer require maatwebsite/excel',
        ],
        'broadcasting' => [
            'package' => 'laravel/reverb',
            'message' => 'The "broadcasting" feature requires laravel/reverb (or pusher)',
            'install_command' => 'composer require laravel/reverb',
        ],
        'api_tokens' => [
            'package' => 'laravel/sanctum',
            'message' => 'The "api_tokens" feature requires laravel/sanctum',
            'install_command' => 'composer require laravel/sanctum',
        ],
        'oauth' => [
            'package' => 'laravel/passport',
            'message' => 'The "oauth" feature requires laravel/passport',
            'install_command' => 'composer require laravel/passport',
        ],
    ];

    /**
     * Package dependencies (packages that require other packages).
     *
     * @var array<string, array{requires: array<string>, message: string}>
     */
    private const PACKAGE_DEPENDENCIES = [
        'filament/filament' => [
            'requires' => ['livewire/livewire'],
            'message' => 'Filament requires Livewire to be installed',
        ],
        'livewire/volt' => [
            'requires' => ['livewire/livewire'],
            'message' => 'Volt requires Livewire to be installed',
        ],
    ];

    /**
     * Incompatible package combinations.
     *
     * @var array<array{packages: array<string>, message: string, severity: string}>
     */
    private const INCOMPATIBILITIES = [
        [
            'packages' => ['laravel/sanctum', 'laravel/passport'],
            'message' => 'Using both Sanctum and Passport is not recommended. Choose one authentication strategy.',
            'severity' => 'warning',
        ],
    ];

    /**
     * Minimum version requirements for features.
     *
     * @var array<string, array{min_version: string, feature: string, message: string}>
     */
    private const VERSION_REQUIREMENTS = [
        'filament/filament' => [
            'min_version' => '3.0.0',
            'feature' => 'filament',
            'message' => 'Filament v3+ is required for this schema syntax',
        ],
        'livewire/livewire' => [
            'min_version' => '3.0.0',
            'feature' => 'livewire',
            'message' => 'Livewire v3+ is required for this syntax',
        ],
        'inertiajs/inertia-laravel' => [
            'min_version' => '1.0.0',
            'feature' => 'inertia',
            'message' => 'Inertia v1+ is required',
        ],
    ];

    /**
     * Config requirements for packages.
     *
     * @var array<string, array{config_key: string, env_vars?: array<string>, publish_command?: string, message: string}>
     */
    private const CONFIG_REQUIREMENTS = [
        'laravel/scout' => [
            'config_key' => 'scout.driver',
            'env_vars' => ['SCOUT_DRIVER'],
            'publish_command' => 'php artisan vendor:publish --provider="Laravel\\Scout\\ScoutServiceProvider"',
            'message' => 'Scout requires SCOUT_DRIVER to be configured',
        ],
        'laravel/cashier' => [
            'config_key' => 'cashier.key',
            'env_vars' => ['STRIPE_KEY', 'STRIPE_SECRET'],
            'message' => 'Cashier requires Stripe API keys to be configured',
        ],
        'laravel/horizon' => [
            'config_key' => 'horizon',
            'env_vars' => ['REDIS_HOST'],
            'publish_command' => 'php artisan horizon:install',
            'message' => 'Horizon requires Redis to be configured',
        ],
        'spatie/laravel-permission' => [
            'config_key' => 'permission',
            'publish_command' => 'php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider"',
            'message' => 'Permission package config should be published',
        ],
        'spatie/laravel-medialibrary' => [
            'config_key' => 'media-library',
            'publish_command' => 'php artisan vendor:publish --provider="Spatie\\MediaLibrary\\MediaLibraryServiceProvider" --tag="medialibrary-migrations"',
            'message' => 'Media Library migrations should be published',
        ],
        'filament/filament' => [
            'config_key' => 'filament',
            'publish_command' => 'php artisan filament:install --panels',
            'message' => 'Filament should be installed with panels',
        ],
    ];

    /**
     * Migration requirements for packages.
     *
     * @var array<string, array{tables: array<string>, command: string, message: string}>
     */
    private const MIGRATION_REQUIREMENTS = [
        'spatie/laravel-permission' => [
            'tables' => ['roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions'],
            'command' => 'php artisan migrate',
            'message' => 'Run migrations to create roles and permissions tables',
        ],
        'spatie/laravel-medialibrary' => [
            'tables' => ['media'],
            'command' => 'php artisan migrate',
            'message' => 'Run migrations to create media table',
        ],
        'spatie/laravel-activitylog' => [
            'tables' => ['activity_log'],
            'command' => 'php artisan migrate',
            'message' => 'Run migrations to create activity_log table',
        ],
        'laravel/sanctum' => [
            'tables' => ['personal_access_tokens'],
            'command' => 'php artisan migrate',
            'message' => 'Run migrations to create personal_access_tokens table',
        ],
        'laravel/passport' => [
            'tables' => ['oauth_auth_codes', 'oauth_access_tokens', 'oauth_refresh_tokens', 'oauth_clients', 'oauth_personal_access_clients'],
            'command' => 'php artisan passport:install',
            'message' => 'Run passport:install to set up OAuth tables',
        ],
    ];

    public function __construct(
        private readonly PackageDiscovery $packageDiscovery,
    ) {}

    /**
     * Validate a draft and return all warnings/errors.
     *
     * @return array{
     *     errors: array<array{type: string, message: string, model?: string, schema_key?: string, install_command?: string}>,
     *     warnings: array<array{type: string, message: string, package?: string, severity?: string}>,
     *     config_reminders: array<array{package: string, message: string, env_vars?: array<string>, publish_command?: string}>,
     *     migration_reminders: array<array{package: string, message: string, command: string, tables: array<string>}>,
     * }
     */
    public function validate(Draft $draft): array
    {
        $installed = $this->packageDiscovery->installed();

        return [
            'errors' => $this->getMissingDependencyErrors($draft, $installed),
            'warnings' => array_merge(
                $this->getVersionWarnings($installed),
                $this->getIncompatibilityWarnings($installed),
                $this->getDependencyChainWarnings($installed)
            ),
            'config_reminders' => $this->getConfigReminders($installed),
            'migration_reminders' => $this->getMigrationReminders($installed),
        ];
    }

    /**
     * Get errors for missing package dependencies.
     *
     * @param  array<string, string>  $installed
     * @return array<array{type: string, message: string, model?: string, schema_key?: string, install_command?: string}>
     */
    public function getMissingDependencyErrors(Draft $draft, array $installed): array
    {
        $errors = [];

        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            foreach (self::REQUIRED_PACKAGES as $schemaKey => $config) {
                if (isset($modelDef[$schemaKey]) && $modelDef[$schemaKey]) {
                    if (! isset($installed[$config['package']])) {
                        $errors[] = [
                            'type' => 'missing_dependency',
                            'message' => $config['message'],
                            'model' => $modelName,
                            'schema_key' => $schemaKey,
                            'install_command' => $config['install_command'] ?? null,
                        ];
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Get warnings for package version issues.
     *
     * @param  array<string, string>  $installed
     * @return array<array{type: string, message: string, package: string, current_version: string, min_version: string}>
     */
    public function getVersionWarnings(array $installed): array
    {
        $warnings = [];

        foreach (self::VERSION_REQUIREMENTS as $package => $config) {
            if (isset($installed[$package])) {
                $currentVersion = $this->normalizeVersion($installed[$package]);
                $minVersion = $this->normalizeVersion($config['min_version']);

                if (version_compare($currentVersion, $minVersion, '<')) {
                    $warnings[] = [
                        'type' => 'version_warning',
                        'message' => $config['message'],
                        'package' => $package,
                        'current_version' => $installed[$package],
                        'min_version' => $config['min_version'],
                    ];
                }
            }
        }

        return $warnings;
    }

    /**
     * Get warnings for incompatible package combinations.
     *
     * @param  array<string, string>  $installed
     * @return array<array{type: string, message: string, packages: array<string>, severity: string}>
     */
    public function getIncompatibilityWarnings(array $installed): array
    {
        $warnings = [];

        foreach (self::INCOMPATIBILITIES as $incompatibility) {
            $installedConflicts = array_filter(
                $incompatibility['packages'],
                fn (string $pkg): bool => isset($installed[$pkg])
            );

            if (count($installedConflicts) >= 2) {
                $warnings[] = [
                    'type' => 'incompatibility',
                    'message' => $incompatibility['message'],
                    'packages' => $installedConflicts,
                    'severity' => $incompatibility['severity'],
                ];
            }
        }

        return $warnings;
    }

    /**
     * Get warnings for missing package dependencies.
     *
     * @param  array<string, string>  $installed
     * @return array<array{type: string, message: string, package: string, requires: array<string>}>
     */
    public function getDependencyChainWarnings(array $installed): array
    {
        $warnings = [];

        foreach (self::PACKAGE_DEPENDENCIES as $package => $config) {
            if (isset($installed[$package])) {
                $missingDeps = array_filter(
                    $config['requires'],
                    fn (string $dep): bool => ! isset($installed[$dep])
                );

                if ($missingDeps !== []) {
                    $warnings[] = [
                        'type' => 'missing_package_dependency',
                        'message' => $config['message'],
                        'package' => $package,
                        'requires' => array_values($missingDeps),
                    ];
                }
            }
        }

        return $warnings;
    }

    /**
     * Get config reminders for installed packages.
     *
     * @param  array<string, string>  $installed
     * @return array<array{package: string, message: string, env_vars?: array<string>, publish_command?: string}>
     */
    public function getConfigReminders(array $installed): array
    {
        $reminders = [];

        foreach (self::CONFIG_REQUIREMENTS as $package => $config) {
            if (isset($installed[$package])) {
                // Check if config exists
                $configExists = config($config['config_key']) !== null;

                // Check env vars if specified
                $envMissing = [];
                if (isset($config['env_vars'])) {
                    foreach ($config['env_vars'] as $envVar) {
                        if (empty(env($envVar))) {
                            $envMissing[] = $envVar;
                        }
                    }
                }

                if (! $configExists || $envMissing !== []) {
                    $reminder = [
                        'package' => $package,
                        'message' => $config['message'],
                    ];

                    if ($envMissing !== []) {
                        $reminder['env_vars'] = $envMissing;
                    }

                    if (isset($config['publish_command'])) {
                        $reminder['publish_command'] = $config['publish_command'];
                    }

                    $reminders[] = $reminder;
                }
            }
        }

        return $reminders;
    }

    /**
     * Get migration reminders for installed packages.
     *
     * @param  array<string, string>  $installed
     * @return array<array{package: string, message: string, command: string, tables: array<string>}>
     */
    public function getMigrationReminders(array $installed): array
    {
        $reminders = [];

        foreach (self::MIGRATION_REQUIREMENTS as $package => $config) {
            if (isset($installed[$package])) {
                // Check if tables exist
                $missingTables = [];
                foreach ($config['tables'] as $table) {
                    if (! $this->tableExists($table)) {
                        $missingTables[] = $table;
                    }
                }

                if ($missingTables !== []) {
                    $reminders[] = [
                        'package' => $package,
                        'message' => $config['message'],
                        'command' => $config['command'],
                        'tables' => $missingTables,
                    ];
                }
            }
        }

        return $reminders;
    }

    /**
     * Check if a package is installed.
     */
    public function isPackageInstalled(string $package): bool
    {
        $installed = $this->packageDiscovery->installed();

        return isset($installed[$package]);
    }

    /**
     * Get the installed version of a package.
     */
    public function getPackageVersion(string $package): ?string
    {
        $installed = $this->packageDiscovery->installed();

        return $installed[$package] ?? null;
    }

    /**
     * Check if a table exists in the database.
     */
    private function tableExists(string $table): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Normalize version string for comparison.
     */
    private function normalizeVersion(string $version): string
    {
        // Remove 'v' prefix
        $version = ltrim($version, 'vV');

        // Extract numeric version (e.g., "3.2.1-beta" -> "3.2.1")
        if (preg_match('/^(\d+(?:\.\d+)*)/', $version, $matches)) {
            return $matches[1];
        }

        return $version;
    }
}
