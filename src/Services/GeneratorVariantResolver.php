<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

/**
 * Resolves generator variants based on installed packages and stack.
 *
 * Features:
 * - Stack-aware page generation (Inertia/React, Inertia/Vue, Livewire, Volt, Blade)
 * - API generation variants (Sanctum, Passport, or none)
 * - Test generation variants (Pest or PHPUnit)
 * - Admin panel variants (Filament, Nova, or none)
 * - Package-specific migration columns
 * - Event/Listener scaffolding variants
 * - API documentation variants
 */
final class GeneratorVariantResolver
{
    /**
     * UI stack variants.
     */
    public const STACK_INERTIA_REACT = 'inertia-react';

    public const STACK_INERTIA_VUE = 'inertia-vue';

    public const STACK_LIVEWIRE = 'livewire';

    public const STACK_VOLT = 'volt';

    public const STACK_BLADE = 'blade';

    /**
     * API auth variants.
     */
    public const API_AUTH_SANCTUM = 'sanctum';

    public const API_AUTH_PASSPORT = 'passport';

    public const API_AUTH_NONE = 'none';

    /**
     * Test framework variants.
     */
    public const TEST_PEST = 'pest';

    public const TEST_PHPUNIT = 'phpunit';

    /**
     * Admin panel variants.
     */
    public const ADMIN_FILAMENT = 'filament';

    public const ADMIN_NOVA = 'nova';

    public const ADMIN_NONE = 'none';

    /**
     * API documentation variants.
     */
    public const API_DOCS_SCRAMBLE = 'scramble';

    public const API_DOCS_SCRIBE = 'scribe';

    public const API_DOCS_NONE = 'none';

    /**
     * Broadcasting variants.
     */
    public const BROADCAST_REVERB = 'reverb';

    public const BROADCAST_PUSHER = 'pusher';

    public const BROADCAST_NONE = 'none';

    public function __construct(
        private readonly PackageDiscovery $packageDiscovery,
        private readonly StackDetector $stackDetector,
    ) {}

    /**
     * Get all resolved variants for the current application.
     *
     * @return array{
     *     stack: string,
     *     api_auth: string,
     *     test_framework: string,
     *     admin_panel: string,
     *     api_docs: string,
     *     broadcasting: string,
     *     features: array<string, bool>,
     * }
     */
    public function resolveAll(): array
    {
        return [
            'stack' => $this->resolveStack(),
            'api_auth' => $this->resolveApiAuth(),
            'test_framework' => $this->resolveTestFramework(),
            'admin_panel' => $this->resolveAdminPanel(),
            'api_docs' => $this->resolveApiDocs(),
            'broadcasting' => $this->resolveBroadcasting(),
            'features' => $this->resolveFeatures(),
        ];
    }

    /**
     * Resolve the UI stack variant.
     */
    public function resolveStack(): string
    {
        $configStack = config('architect.stack', 'auto');
        if ($configStack !== 'auto') {
            return $configStack;
        }

        return $this->stackDetector->detect();
    }

    /**
     * Resolve the API authentication variant.
     */
    public function resolveApiAuth(): string
    {
        $installed = $this->packageDiscovery->installed();

        // Prefer Sanctum over Passport (more common for SPAs)
        if (isset($installed['laravel/sanctum'])) {
            return self::API_AUTH_SANCTUM;
        }

        if (isset($installed['laravel/passport'])) {
            return self::API_AUTH_PASSPORT;
        }

        return self::API_AUTH_NONE;
    }

    /**
     * Resolve the test framework variant.
     */
    public function resolveTestFramework(): string
    {
        $installed = $this->packageDiscovery->installed();

        if (isset($installed['pestphp/pest'])) {
            return self::TEST_PEST;
        }

        return self::TEST_PHPUNIT;
    }

    /**
     * Resolve the admin panel variant.
     */
    public function resolveAdminPanel(): string
    {
        $installed = $this->packageDiscovery->installed();

        if (isset($installed['filament/filament'])) {
            return self::ADMIN_FILAMENT;
        }

        if (isset($installed['laravel/nova'])) {
            return self::ADMIN_NOVA;
        }

        return self::ADMIN_NONE;
    }

    /**
     * Resolve the API documentation variant.
     */
    public function resolveApiDocs(): string
    {
        $installed = $this->packageDiscovery->installed();

        if (isset($installed['dedoc/scramble'])) {
            return self::API_DOCS_SCRAMBLE;
        }

        if (isset($installed['knuckleswtf/scribe'])) {
            return self::API_DOCS_SCRIBE;
        }

        return self::API_DOCS_NONE;
    }

    /**
     * Resolve the broadcasting variant.
     */
    public function resolveBroadcasting(): string
    {
        $installed = $this->packageDiscovery->installed();

        if (isset($installed['laravel/reverb'])) {
            return self::BROADCAST_REVERB;
        }

        if (isset($installed['pusher/pusher-php-server'])) {
            return self::BROADCAST_PUSHER;
        }

        return self::BROADCAST_NONE;
    }

    /**
     * Resolve available features based on installed packages.
     *
     * @return array<string, bool>
     */
    public function resolveFeatures(): array
    {
        $installed = $this->packageDiscovery->installed();

        return [
            'media' => isset($installed['spatie/laravel-medialibrary']),
            'permissions' => isset($installed['spatie/laravel-permission']),
            'activity_log' => isset($installed['spatie/laravel-activitylog']),
            'sluggable' => isset($installed['spatie/laravel-sluggable']),
            'tags' => isset($installed['spatie/laravel-tags']),
            'searchable' => isset($installed['laravel/scout']),
            'billable' => isset($installed['laravel/cashier']) || isset($installed['laravel/cashier-stripe']),
            'feature_flags' => isset($installed['laravel/pennant']),
            'social_auth' => isset($installed['laravel/socialite']),
            'horizon' => isset($installed['laravel/horizon']),
            'telescope' => isset($installed['laravel/telescope']),
            'pulse' => isset($installed['laravel/pulse']),
            'prism' => isset($installed['prism-php/prism']) || isset($installed['echolabsdev/prism']),
            'excel' => isset($installed['maatwebsite/excel']),
            'query_builder' => isset($installed['spatie/laravel-query-builder']),
            'wayfinder' => isset($installed['laravel/wayfinder']),
        ];
    }

    /**
     * Get the API middleware based on resolved auth variant.
     *
     * @return array<string>
     */
    public function getApiMiddleware(): array
    {
        $auth = $this->resolveApiAuth();

        return match ($auth) {
            self::API_AUTH_SANCTUM => ['auth:sanctum'],
            self::API_AUTH_PASSPORT => ['auth:api'],
            default => [],
        };
    }

    /**
     * Get the page file extension based on stack.
     */
    public function getPageExtension(): string
    {
        $stack = $this->resolveStack();

        return match ($stack) {
            self::STACK_INERTIA_REACT => '.tsx',
            self::STACK_INERTIA_VUE => '.vue',
            self::STACK_LIVEWIRE, self::STACK_VOLT => '.php',
            self::STACK_BLADE => '.blade.php',
            default => '.tsx',
        };
    }

    /**
     * Get the pages directory based on stack.
     */
    public function getPagesDirectory(): string
    {
        $stack = $this->resolveStack();

        return match ($stack) {
            self::STACK_INERTIA_REACT, self::STACK_INERTIA_VUE => resource_path('js/pages'),
            self::STACK_LIVEWIRE => app_path('Livewire'),
            self::STACK_VOLT => resource_path('views/livewire'),
            self::STACK_BLADE => resource_path('views'),
            default => resource_path('js/pages'),
        };
    }

    /**
     * Get test assertion helpers based on stack.
     *
     * @return array<string>
     */
    public function getTestAssertionHelpers(): array
    {
        $stack = $this->resolveStack();
        $helpers = [];

        if (str_starts_with($stack, 'inertia')) {
            $helpers[] = 'assertInertia';
        }

        if ($stack === self::STACK_LIVEWIRE || $stack === self::STACK_VOLT) {
            $helpers[] = 'livewire';
        }

        return $helpers;
    }

    /**
     * Get package-specific migration columns.
     *
     * @return array<string, array{column: string, type: string, after?: string}>
     */
    public function getPackageSpecificColumns(): array
    {
        $installed = $this->packageDiscovery->installed();
        $columns = [];

        if (isset($installed['laravel/cashier']) || isset($installed['laravel/cashier-stripe'])) {
            $columns['stripe_id'] = ['column' => 'stripe_id', 'type' => 'string', 'after' => 'id'];
            $columns['pm_type'] = ['column' => 'pm_type', 'type' => 'string:nullable'];
            $columns['pm_last_four'] = ['column' => 'pm_last_four', 'type' => 'string:4:nullable'];
            $columns['trial_ends_at'] = ['column' => 'trial_ends_at', 'type' => 'timestamp:nullable'];
        }

        if (isset($installed['laravel/sanctum'])) {
            // Sanctum uses a separate table, but might want to track tokens
            $columns['api_tokens_enabled'] = ['column' => 'api_tokens_enabled', 'type' => 'boolean:default:false'];
        }

        return $columns;
    }

    /**
     * Get event/listener scaffolding config based on packages.
     *
     * @return array{use_broadcasting: bool, use_queued_listeners: bool, driver: string}
     */
    public function getEventConfig(): array
    {
        $broadcasting = $this->resolveBroadcasting();
        $installed = $this->packageDiscovery->installed();

        return [
            'use_broadcasting' => $broadcasting !== self::BROADCAST_NONE,
            'use_queued_listeners' => isset($installed['laravel/horizon']),
            'driver' => $broadcasting,
        ];
    }

    /**
     * Get API documentation config based on packages.
     *
     * @return array{variant: string, annotations: bool, group_by_controller: bool}
     */
    public function getApiDocsConfig(): array
    {
        $variant = $this->resolveApiDocs();

        return [
            'variant' => $variant,
            'annotations' => $variant === self::API_DOCS_SCRIBE,
            'group_by_controller' => $variant === self::API_DOCS_SCRAMBLE,
        ];
    }

    /**
     * Check if a specific feature is available.
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->resolveFeatures();

        return $features[$feature] ?? false;
    }

    /**
     * Get the stub variant suffix based on stack.
     */
    public function getStubVariant(): string
    {
        $stack = $this->resolveStack();

        return match ($stack) {
            self::STACK_INERTIA_REACT => 'inertia-react',
            self::STACK_INERTIA_VUE => 'inertia-vue',
            self::STACK_LIVEWIRE => 'livewire',
            self::STACK_VOLT => 'volt',
            self::STACK_BLADE => 'blade',
            default => 'inertia-react',
        };
    }
}
