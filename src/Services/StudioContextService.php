<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class StudioContextService
{
    public function __construct(
        private readonly StackDetector $stackDetector,
        private readonly PackageDiscovery $packageDiscovery,
        private readonly PackageRegistry $packageRegistry,
        private readonly ImportService $importService,
        private readonly GeneratorVariantResolver $variantResolver,
        private readonly AppModelService $appModelService,
    ) {}

    /**
     * Build context payload for the Studio UI (stack, packages, models, paths, ai, starters, variants, features).
     *
     * @return array{
     *     stack: string,
     *     packages: array<int, array{name: string, version: string, hints: array|null}>,
     *     existing_models: array<int, array{name: string, table: string}>,
     *     draft_path: string,
     *     state_path: string,
     *     schema_version: string,
     *     ai_enabled: bool,
     *     starters: array<int, string>,
     *     variants: array{stack: string, api_auth: string, test_framework: string, admin_panel: string, api_docs: string, broadcasting: string},
     *     features: array<string, bool>,
     *     schema_hints: array<string, array{schema_key: string, description: string, requires_package: string, available: bool}>,
     *     ai_capabilities: array{available: bool, provider: string|null, features: array<string, bool>},
     *     app_model: array{routes: array, db_schema: array, models: array, actions: array, pages: array, packages: array, stack: string, conventions: array},
     *     fingerprint: array{stack: string, models: array, route_count: int, route_sample: array, package_names: array, conventions: array},
     * }
     */
    public function build(): array
    {
        $stack = config('architect.stack', 'auto');
        if ($stack === 'auto') {
            $stack = $this->stackDetector->detect();
        }

        $installed = $this->packageDiscovery->installed();
        $packages = [];
        foreach ($installed as $name => $version) {
            $hints = $this->packageRegistry->get($name);
            $packages[] = [
                'name' => $name,
                'version' => $version,
                'hints' => $hints,
            ];
        }

        $existingModels = $this->existingModels();

        $draftPath = config('architect.draft_path', base_path('draft.yaml'));
        $statePath = config('architect.state_path', base_path('.architect-state.json'));
        $schemaVersion = '1.0';

        $aiEnabled = config('architect.ai.enabled', true)
            && class_exists(\Prism\Prism\Facades\Prism::class);

        $starters = $this->starterNames();

        // Add generator variants and features
        $variants = $this->variantResolver->resolveAll();
        $features = $this->variantResolver->resolveFeatures();
        $schemaHints = $this->buildSchemaHints($installed);
        $aiCapabilities = $this->buildAICapabilities($aiEnabled);
        $appModel = $this->appModelService->appModel();
        $fingerprint = $this->appModelService->fingerprint();

        return [
            'stack' => $stack,
            'packages' => $packages,
            'existing_models' => $existingModels,
            'draft_path' => $draftPath,
            'state_path' => $statePath,
            'schema_version' => $schemaVersion,
            'ai_enabled' => $aiEnabled,
            'starters' => $starters,
            'variants' => $variants,
            'features' => $features,
            'schema_hints' => $schemaHints,
            'ai_capabilities' => $aiCapabilities,
            'app_model' => $appModel,
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * Build schema hints showing available features.
     *
     * @param  array<string, string>  $installed
     * @return array<string, array{schema_key: string, description: string, requires_package: string, available: bool}>
     */
    private function buildSchemaHints(array $installed): array
    {
        $hints = [
            'media' => [
                'schema_key' => 'media',
                'description' => 'Enable file uploads with Media Library. Adds HasMedia interface and InteractsWithMedia trait.',
                'requires_package' => 'spatie/laravel-medialibrary',
                'available' => isset($installed['spatie/laravel-medialibrary']),
            ],
            'searchable' => [
                'schema_key' => 'searchable',
                'description' => 'Enable full-text search with Scout. Adds Searchable trait.',
                'requires_package' => 'laravel/scout',
                'available' => isset($installed['laravel/scout']),
            ],
            'billable' => [
                'schema_key' => 'billable',
                'description' => 'Enable Stripe billing with Cashier. Adds Billable trait.',
                'requires_package' => 'laravel/cashier',
                'available' => isset($installed['laravel/cashier']) || isset($installed['laravel/cashier-stripe']),
            ],
            'filament' => [
                'schema_key' => 'filament',
                'description' => 'Generate Filament admin resource for this model.',
                'requires_package' => 'filament/filament',
                'available' => isset($installed['filament/filament']),
            ],
            'sluggable' => [
                'schema_key' => 'sluggable',
                'description' => 'Auto-generate URL slugs. Adds HasSlug trait.',
                'requires_package' => 'spatie/laravel-sluggable',
                'available' => isset($installed['spatie/laravel-sluggable']),
            ],
            'tags' => [
                'schema_key' => 'tags',
                'description' => 'Enable tagging functionality. Adds HasTags trait.',
                'requires_package' => 'spatie/laravel-tags',
                'available' => isset($installed['spatie/laravel-tags']),
            ],
            'activity_log' => [
                'schema_key' => 'activity_log',
                'description' => 'Log model activity. Adds LogsActivity trait.',
                'requires_package' => 'spatie/laravel-activitylog',
                'available' => isset($installed['spatie/laravel-activitylog']),
            ],
            'roles' => [
                'schema_key' => 'roles',
                'description' => 'Enable role-based permissions. Adds HasRoles trait.',
                'requires_package' => 'spatie/laravel-permission',
                'available' => isset($installed['spatie/laravel-permission']),
            ],
            'api_tokens' => [
                'schema_key' => 'api_tokens',
                'description' => 'Enable API token authentication with Sanctum. Adds HasApiTokens trait.',
                'requires_package' => 'laravel/sanctum',
                'available' => isset($installed['laravel/sanctum']),
            ],
            'softDeletes' => [
                'schema_key' => 'softDeletes',
                'description' => 'Enable soft deletes. Adds SoftDeletes trait and deleted_at column.',
                'requires_package' => 'laravel/framework',
                'available' => true,
            ],
            'exportable' => [
                'schema_key' => 'exportable',
                'description' => 'Enable Excel export functionality.',
                'requires_package' => 'maatwebsite/excel',
                'available' => isset($installed['maatwebsite/excel']),
            ],
            'broadcasting' => [
                'schema_key' => 'broadcasting',
                'description' => 'Enable WebSocket broadcasting for real-time updates.',
                'requires_package' => 'laravel/reverb',
                'available' => isset($installed['laravel/reverb']) || isset($installed['pusher/pusher-php-server']),
            ],
        ];

        return $hints;
    }

    /**
     * Build AI capabilities information.
     *
     * @return array{available: bool, provider: string|null, features: array<string, bool>}
     */
    private function buildAICapabilities(bool $aiEnabled): array
    {
        return [
            'available' => $aiEnabled,
            'provider' => $aiEnabled ? config('architect.ai.provider', 'anthropic') : null,
            'features' => [
                'chat' => $aiEnabled,
                'suggestions' => $aiEnabled,
                'validation' => $aiEnabled,
                'code_generation' => $aiEnabled,
                'package_analysis' => $aiEnabled,
                'conflict_detection' => $aiEnabled,
                'boilerplate_generation' => $aiEnabled,
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, table: string}>
     */
    private function existingModels(): array
    {
        $draft = $this->importService->import(null);
        $models = $draft['models'] ?? [];
        $out = [];
        foreach (array_keys($models) as $name) {
            $out[] = [
                'name' => $name,
                'table' => Str::snake(Str::plural($name)),
            ];
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function starterNames(): array
    {
        $packageRoot = dirname(__DIR__, 2);
        $startersPath = $packageRoot.'/resources/starters';
        if (! File::isDirectory($startersPath)) {
            return [];
        }

        $files = File::glob($startersPath.'/*.yaml');
        if (! is_array($files)) {
            return [];
        }

        $names = [];
        foreach ($files as $path) {
            $names[] = basename($path, '.yaml');
        }

        sort($names);

        return $names;
    }
}
