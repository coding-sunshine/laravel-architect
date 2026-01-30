<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

final class PackageRegistry
{
    /**
     * Built-in known packages: name => hints.
     *
     * @var array<string, array{draft_extensions?: array<string>, generator_variants?: array<string>, suggested_commands?: array<string>}>
     */
    private const KNOWN = [
        // Filament ecosystem
        'filament/filament' => [
            'draft_extensions' => ['filament: true on model for Filament resource'],
            'generator_variants' => ['Filament resource from model'],
            'suggested_commands' => ['php artisan make:filament-resource'],
        ],

        // Spatie packages
        'spatie/laravel-medialibrary' => [
            'draft_extensions' => ['media: true on model for HasMedia'],
            'generator_variants' => ['Media conversions and registration'],
            'suggested_commands' => [],
        ],
        'spatie/laravel-permission' => [
            'draft_extensions' => ['roles/permissions on model'],
            'generator_variants' => ['Permission setup'],
            'suggested_commands' => ['php artisan permission:cache-reset'],
        ],
        'spatie/laravel-activitylog' => [
            'draft_extensions' => ['activity_log: true on model for LogsActivity'],
            'generator_variants' => ['Activity logging trait'],
            'suggested_commands' => ['php artisan activitylog:clean'],
        ],
        'spatie/laravel-backup' => [
            'draft_extensions' => [],
            'generator_variants' => [],
            'suggested_commands' => ['php artisan backup:run', 'php artisan backup:list'],
        ],
        'spatie/laravel-query-builder' => [
            'draft_extensions' => ['filterable/sortable fields on model'],
            'generator_variants' => ['QueryBuilder controller methods'],
            'suggested_commands' => [],
        ],
        'spatie/laravel-sluggable' => [
            'draft_extensions' => ['sluggable: true on model'],
            'generator_variants' => ['Sluggable trait setup'],
            'suggested_commands' => [],
        ],
        'spatie/laravel-tags' => [
            'draft_extensions' => ['tags: true on model for HasTags'],
            'generator_variants' => ['Taggable model setup'],
            'suggested_commands' => [],
        ],

        // Inertia/Livewire
        'inertiajs/inertia-laravel' => [
            'draft_extensions' => [],
            'generator_variants' => ['Inertia page generation'],
            'suggested_commands' => [],
        ],
        'livewire/livewire' => [
            'draft_extensions' => [],
            'generator_variants' => ['Livewire component generation'],
            'suggested_commands' => ['php artisan make:livewire'],
        ],
        'livewire/volt' => [
            'draft_extensions' => [],
            'generator_variants' => ['Volt single-file generation'],
            'suggested_commands' => [],
        ],

        // Laravel first-party
        'laravel/fortify' => [
            'draft_extensions' => [],
            'generator_variants' => ['Auth views and controllers'],
            'suggested_commands' => [],
        ],
        'laravel/sanctum' => [
            'draft_extensions' => ['api_tokens: true on User model'],
            'generator_variants' => ['API token authentication'],
            'suggested_commands' => [],
        ],
        'laravel/passport' => [
            'draft_extensions' => ['oauth: true for OAuth support'],
            'generator_variants' => ['OAuth2 setup'],
            'suggested_commands' => ['php artisan passport:install', 'php artisan passport:keys'],
        ],
        'laravel/horizon' => [
            'draft_extensions' => [],
            'generator_variants' => [],
            'suggested_commands' => ['php artisan horizon', 'php artisan horizon:status'],
        ],
        'laravel/telescope' => [
            'draft_extensions' => [],
            'generator_variants' => [],
            'suggested_commands' => ['php artisan telescope:clear', 'php artisan telescope:prune'],
        ],
        'laravel/scout' => [
            'draft_extensions' => ['searchable: true on model for Searchable'],
            'generator_variants' => ['Scout searchable model'],
            'suggested_commands' => ['php artisan scout:import'],
        ],
        'laravel/socialite' => [
            'draft_extensions' => ['social_auth: true for social login'],
            'generator_variants' => ['Social authentication controllers'],
            'suggested_commands' => [],
        ],
        'laravel/cashier' => [
            'draft_extensions' => ['billable: true on model for Billable'],
            'generator_variants' => ['Stripe/Paddle billing setup'],
            'suggested_commands' => ['php artisan cashier:webhook'],
        ],
        'laravel/pennant' => [
            'draft_extensions' => ['feature_flags: true for feature flags'],
            'generator_variants' => ['Feature flag definitions'],
            'suggested_commands' => ['php artisan pennant:purge'],
        ],
        'laravel/pulse' => [
            'draft_extensions' => [],
            'generator_variants' => [],
            'suggested_commands' => ['php artisan pulse:check', 'php artisan pulse:clear'],
        ],
        'laravel/reverb' => [
            'draft_extensions' => ['broadcasting: true for WebSocket events'],
            'generator_variants' => ['Broadcasting event setup'],
            'suggested_commands' => ['php artisan reverb:start'],
        ],
        'laravel/wayfinder' => [
            'draft_extensions' => [],
            'generator_variants' => ['TypeScript route functions'],
            'suggested_commands' => [],
        ],

        // AI/LLM
        'prism-php/prism' => [
            'draft_extensions' => ['ai_generated: true for AI content'],
            'generator_variants' => ['Prism AI integration'],
            'suggested_commands' => [],
        ],
        'echolabsdev/prism' => [
            'draft_extensions' => ['ai_generated: true for AI content'],
            'generator_variants' => ['Prism AI integration'],
            'suggested_commands' => [],
        ],

        // Testing
        'pestphp/pest' => [
            'draft_extensions' => [],
            'generator_variants' => ['Pest test generation'],
            'suggested_commands' => ['php artisan make:test --pest'],
        ],

        // Other popular packages
        'barryvdh/laravel-debugbar' => [
            'draft_extensions' => [],
            'generator_variants' => [],
            'suggested_commands' => [],
        ],
        'barryvdh/laravel-ide-helper' => [
            'draft_extensions' => [],
            'generator_variants' => [],
            'suggested_commands' => ['php artisan ide-helper:generate', 'php artisan ide-helper:models'],
        ],
        'maatwebsite/excel' => [
            'draft_extensions' => ['exportable: true for Excel export'],
            'generator_variants' => ['Import/Export classes'],
            'suggested_commands' => [],
        ],
        'league/flysystem-aws-s3-v3' => [
            'draft_extensions' => ['s3_storage: true for S3 files'],
            'generator_variants' => [],
            'suggested_commands' => [],
        ],
    ];

    /**
     * @param  array<string, array{draft_extensions?: array<string>, generator_variants?: array<string>, suggested_commands?: array<string>}>  $custom
     */
    public function __construct(
        private readonly array $custom = []
    ) {}

    /**
     * Returns hints for a package name. Merges built-in with config custom.
     *
     * @return array{draft_extensions: array<string>, generator_variants: array<string>, suggested_commands: array<string>}|null
     */
    public function get(string $packageName): ?array
    {
        $builtIn = self::KNOWN[$packageName] ?? null;
        $user = $this->custom[$packageName] ?? [];

        if ($builtIn === null && $user === []) {
            return null;
        }

        $base = $builtIn ?? [
            'draft_extensions' => [],
            'generator_variants' => [],
            'suggested_commands' => [],
        ];

        return [
            'draft_extensions' => array_merge($base['draft_extensions'], $user['draft_extensions'] ?? []),
            'generator_variants' => array_merge($base['generator_variants'], $user['generator_variants'] ?? []),
            'suggested_commands' => array_merge($base['suggested_commands'], $user['suggested_commands'] ?? []),
        ];
    }

    /**
     * Returns all known package names (built-in + custom keys).
     *
     * @return array<string>
     */
    public function knownNames(): array
    {
        return array_values(array_unique(array_merge(array_keys(self::KNOWN), array_keys($this->custom))));
    }

    /**
     * Returns whether the package is known to the registry.
     */
    public function isKnown(string $packageName): bool
    {
        return $this->get($packageName) !== null;
    }
}
