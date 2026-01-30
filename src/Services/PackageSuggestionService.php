<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Services;

use CodingSunshine\Architect\Support\Draft;

/**
 * Provides intelligent suggestions based on installed packages and draft schema.
 *
 * Features:
 * - Smart field detection (media, searchable, etc.)
 * - Relationship inference based on packages
 * - Trait auto-discovery suggestions
 * - Soft delete awareness
 * - Factory state suggestions
 * - Seeder template suggestions
 */
final class PackageSuggestionService
{
    /**
     * Field patterns that suggest specific package features.
     *
     * @var array<string, array{packages: array<string>, suggestion: string, schema_key: string, trait?: string}>
     */
    private const FIELD_PATTERNS = [
        // Media fields -> spatie/laravel-medialibrary
        'avatar' => [
            'packages' => ['spatie/laravel-medialibrary'],
            'suggestion' => 'Add media: true to enable file uploads with Media Library',
            'schema_key' => 'media',
            'trait' => 'Spatie\\MediaLibrary\\HasMedia',
        ],
        'image' => [
            'packages' => ['spatie/laravel-medialibrary'],
            'suggestion' => 'Add media: true to enable file uploads with Media Library',
            'schema_key' => 'media',
            'trait' => 'Spatie\\MediaLibrary\\HasMedia',
        ],
        'photo' => [
            'packages' => ['spatie/laravel-medialibrary'],
            'suggestion' => 'Add media: true to enable file uploads with Media Library',
            'schema_key' => 'media',
            'trait' => 'Spatie\\MediaLibrary\\HasMedia',
        ],
        'thumbnail' => [
            'packages' => ['spatie/laravel-medialibrary'],
            'suggestion' => 'Add media: true to enable file uploads with Media Library',
            'schema_key' => 'media',
            'trait' => 'Spatie\\MediaLibrary\\HasMedia',
        ],
        'cover' => [
            'packages' => ['spatie/laravel-medialibrary'],
            'suggestion' => 'Add media: true to enable file uploads with Media Library',
            'schema_key' => 'media',
            'trait' => 'Spatie\\MediaLibrary\\HasMedia',
        ],
        'attachment' => [
            'packages' => ['spatie/laravel-medialibrary'],
            'suggestion' => 'Add media: true to enable file uploads with Media Library',
            'schema_key' => 'media',
            'trait' => 'Spatie\\MediaLibrary\\HasMedia',
        ],
        'file' => [
            'packages' => ['spatie/laravel-medialibrary'],
            'suggestion' => 'Add media: true to enable file uploads with Media Library',
            'schema_key' => 'media',
            'trait' => 'Spatie\\MediaLibrary\\HasMedia',
        ],

        // Slug fields -> spatie/laravel-sluggable
        'slug' => [
            'packages' => ['spatie/laravel-sluggable'],
            'suggestion' => 'Add sluggable: true to auto-generate slugs',
            'schema_key' => 'sluggable',
            'trait' => 'Spatie\\Sluggable\\HasSlug',
        ],

        // Stripe/billing fields -> laravel/cashier
        'stripe_id' => [
            'packages' => ['laravel/cashier', 'laravel/cashier-stripe'],
            'suggestion' => 'Add billable: true to enable Stripe billing',
            'schema_key' => 'billable',
            'trait' => 'Laravel\\Cashier\\Billable',
        ],
        'pm_type' => [
            'packages' => ['laravel/cashier', 'laravel/cashier-stripe'],
            'suggestion' => 'Add billable: true to enable Stripe billing',
            'schema_key' => 'billable',
            'trait' => 'Laravel\\Cashier\\Billable',
        ],
        'pm_last_four' => [
            'packages' => ['laravel/cashier', 'laravel/cashier-stripe'],
            'suggestion' => 'Add billable: true to enable Stripe billing',
            'schema_key' => 'billable',
            'trait' => 'Laravel\\Cashier\\Billable',
        ],

        // Tags -> spatie/laravel-tags
        'tags' => [
            'packages' => ['spatie/laravel-tags'],
            'suggestion' => 'Add tags: true to enable tagging',
            'schema_key' => 'tags',
            'trait' => 'Spatie\\Tags\\HasTags',
        ],
    ];

    /**
     * Model name patterns that suggest relationships.
     *
     * @var array<string, array{packages: array<string>, relationships: array<string, string>}>
     */
    private const MODEL_RELATIONSHIP_PATTERNS = [
        'User' => [
            'packages' => ['spatie/laravel-permission'],
            'relationships' => [
                'belongsToMany' => 'Role,Permission',
            ],
        ],
    ];

    /**
     * Schema keys that require specific packages.
     *
     * @var array<string, array{package: string, trait?: string, interface?: string}>
     */
    private const SCHEMA_PACKAGE_MAP = [
        'media' => [
            'package' => 'spatie/laravel-medialibrary',
            'trait' => 'Spatie\\MediaLibrary\\InteractsWithMedia',
            'interface' => 'Spatie\\MediaLibrary\\HasMedia',
        ],
        'searchable' => [
            'package' => 'laravel/scout',
            'trait' => 'Laravel\\Scout\\Searchable',
        ],
        'billable' => [
            'package' => 'laravel/cashier',
            'trait' => 'Laravel\\Cashier\\Billable',
        ],
        'filament' => [
            'package' => 'filament/filament',
        ],
        'sluggable' => [
            'package' => 'spatie/laravel-sluggable',
            'trait' => 'Spatie\\Sluggable\\HasSlug',
        ],
        'tags' => [
            'package' => 'spatie/laravel-tags',
            'trait' => 'Spatie\\Tags\\HasTags',
        ],
        'activity_log' => [
            'package' => 'spatie/laravel-activitylog',
            'trait' => 'Spatie\\Activitylog\\Traits\\LogsActivity',
        ],
        'roles' => [
            'package' => 'spatie/laravel-permission',
            'trait' => 'Spatie\\Permission\\Traits\\HasRoles',
        ],
        'permissions' => [
            'package' => 'spatie/laravel-permission',
            'trait' => 'Spatie\\Permission\\Traits\\HasRoles',
        ],
        'exportable' => [
            'package' => 'maatwebsite/excel',
        ],
        'broadcasting' => [
            'package' => 'laravel/reverb',
        ],
        'feature_flags' => [
            'package' => 'laravel/pennant',
        ],
        'api_tokens' => [
            'package' => 'laravel/sanctum',
            'trait' => 'Laravel\\Sanctum\\HasApiTokens',
        ],
        'oauth' => [
            'package' => 'laravel/passport',
            'trait' => 'Laravel\\Passport\\HasApiTokens',
        ],
    ];

    /**
     * Factory state suggestions based on packages.
     *
     * @var array<string, array{package: string, states: array<string, string>}>
     */
    private const FACTORY_STATES = [
        'User' => [
            'package' => 'spatie/laravel-permission',
            'states' => [
                'admin' => "->afterCreating(fn (User \$user) => \$user->assignRole('admin'))",
                'withRoles' => "->afterCreating(fn (User \$user) => \$user->assignRole(\$this->faker->randomElement(['user', 'editor', 'admin'])))",
            ],
        ],
    ];

    /**
     * Seeder templates based on packages.
     *
     * @var array<string, array{package: string, template: string, priority: int}>
     */
    private const SEEDER_TEMPLATES = [
        'RoleSeeder' => [
            'package' => 'spatie/laravel-permission',
            'template' => 'role-seeder',
            'priority' => 10,
        ],
        'PermissionSeeder' => [
            'package' => 'spatie/laravel-permission',
            'template' => 'permission-seeder',
            'priority' => 5,
        ],
    ];

    public function __construct(
        private readonly PackageDiscovery $packageDiscovery,
        private readonly PackageRegistry $packageRegistry,
    ) {}

    /**
     * Analyze a draft and return all suggestions.
     *
     * @return array{
     *     schema_suggestions: array<string, array<array{field: string, suggestion: string, schema_key: string, available: bool}>>,
     *     relationship_suggestions: array<string, array<array{type: string, targets: string, reason: string}>>,
     *     trait_suggestions: array<string, array<string>>,
     *     factory_state_suggestions: array<string, array<string, string>>,
     *     seeder_suggestions: array<string, array{template: string, priority: int}>,
     *     soft_delete_suggestions: array<string, string>,
     * }
     */
    public function analyze(Draft $draft): array
    {
        $installed = $this->packageDiscovery->installed();

        return [
            'schema_suggestions' => $this->getSchemaSuggestions($draft, $installed),
            'relationship_suggestions' => $this->getRelationshipSuggestions($draft, $installed),
            'trait_suggestions' => $this->getTraitSuggestions($draft, $installed),
            'factory_state_suggestions' => $this->getFactoryStateSuggestions($draft, $installed),
            'seeder_suggestions' => $this->getSeederSuggestions($installed),
            'soft_delete_suggestions' => $this->getSoftDeleteSuggestions($draft),
        ];
    }

    /**
     * Get schema key suggestions based on field names.
     *
     * @param  array<string, string>  $installed
     * @return array<string, array<array{field: string, suggestion: string, schema_key: string, available: bool}>>
     */
    public function getSchemaSuggestions(Draft $draft, array $installed): array
    {
        $suggestions = [];

        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            $modelSuggestions = [];
            foreach ($modelDef as $field => $type) {
                if (! is_string($type)) {
                    continue;
                }

                $fieldLower = strtolower($field);
                foreach (self::FIELD_PATTERNS as $pattern => $config) {
                    if (str_contains($fieldLower, $pattern)) {
                        $hasPackage = $this->hasAnyPackage($config['packages'], $installed);
                        $alreadyHasKey = isset($modelDef[$config['schema_key']]);

                        if (! $alreadyHasKey) {
                            $modelSuggestions[] = [
                                'field' => $field,
                                'suggestion' => $config['suggestion'],
                                'schema_key' => $config['schema_key'],
                                'available' => $hasPackage,
                            ];
                        }
                        break;
                    }
                }
            }

            if ($modelSuggestions !== []) {
                $suggestions[$modelName] = $modelSuggestions;
            }
        }

        return $suggestions;
    }

    /**
     * Get relationship suggestions based on model names and packages.
     *
     * @param  array<string, string>  $installed
     * @return array<string, array<array{type: string, targets: string, reason: string}>>
     */
    public function getRelationshipSuggestions(Draft $draft, array $installed): array
    {
        $suggestions = [];

        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            $existingRels = $modelDef['relationships'] ?? [];

            // Check model-specific patterns
            if (isset(self::MODEL_RELATIONSHIP_PATTERNS[$modelName])) {
                $pattern = self::MODEL_RELATIONSHIP_PATTERNS[$modelName];
                $hasPackage = $this->hasAnyPackage($pattern['packages'], $installed);

                if ($hasPackage) {
                    foreach ($pattern['relationships'] as $type => $targets) {
                        // Check if relationship already exists
                        if (! isset($existingRels[$type]) || ! str_contains($existingRels[$type], $targets)) {
                            $suggestions[$modelName][] = [
                                'type' => $type,
                                'targets' => $targets,
                                'reason' => 'Detected '.implode(' or ', $pattern['packages']).' package',
                            ];
                        }
                    }
                }
            }

            // Suggest User relationship for any model with user_id
            foreach ($modelDef as $field => $type) {
                if ($field === 'user_id' && ! isset($existingRels['belongsTo'])) {
                    $suggestions[$modelName][] = [
                        'type' => 'belongsTo',
                        'targets' => 'User',
                        'reason' => 'Detected user_id foreign key',
                    ];
                }
            }
        }

        return $suggestions;
    }

    /**
     * Get trait suggestions based on schema keys and packages.
     *
     * @param  array<string, string>  $installed
     * @return array<string, array<string>>
     */
    public function getTraitSuggestions(Draft $draft, array $installed): array
    {
        $suggestions = [];

        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            $existingTraits = $modelDef['traits'] ?? [];
            $modelTraits = [];

            foreach (self::SCHEMA_PACKAGE_MAP as $schemaKey => $config) {
                if (isset($modelDef[$schemaKey]) && $modelDef[$schemaKey]) {
                    if (isset($config['trait']) && ! in_array($config['trait'], $existingTraits, true)) {
                        $hasPackage = isset($installed[$config['package']]);
                        if ($hasPackage) {
                            $modelTraits[] = $config['trait'];
                        }
                    }
                }
            }

            if ($modelTraits !== []) {
                $suggestions[$modelName] = array_unique($modelTraits);
            }
        }

        return $suggestions;
    }

    /**
     * Get factory state suggestions based on model and packages.
     *
     * @param  array<string, string>  $installed
     * @return array<string, array<string, string>>
     */
    public function getFactoryStateSuggestions(Draft $draft, array $installed): array
    {
        $suggestions = [];

        foreach ($draft->modelNames() as $modelName) {
            if (isset(self::FACTORY_STATES[$modelName])) {
                $config = self::FACTORY_STATES[$modelName];
                if (isset($installed[$config['package']])) {
                    $suggestions[$modelName] = $config['states'];
                }
            }
        }

        return $suggestions;
    }

    /**
     * Get seeder suggestions based on installed packages.
     *
     * @param  array<string, string>  $installed
     * @return array<string, array{template: string, priority: int}>
     */
    public function getSeederSuggestions(array $installed): array
    {
        $suggestions = [];

        foreach (self::SEEDER_TEMPLATES as $seederName => $config) {
            if (isset($installed[$config['package']])) {
                $suggestions[$seederName] = [
                    'template' => $config['template'],
                    'priority' => $config['priority'],
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Get soft delete suggestions for models with deleted_at field.
     *
     * @return array<string, string>
     */
    public function getSoftDeleteSuggestions(Draft $draft): array
    {
        $suggestions = [];

        foreach ($draft->modelNames() as $modelName) {
            $modelDef = $draft->getModel($modelName);
            if ($modelDef === null) {
                continue;
            }

            $hasSoftDeletes = ! empty($modelDef['softDeletes']);
            $hasDeletedAt = isset($modelDef['deleted_at']);

            if ($hasDeletedAt && ! $hasSoftDeletes) {
                $suggestions[$modelName] = 'Add softDeletes: true to enable soft deletes (detected deleted_at field)';
            }
        }

        return $suggestions;
    }

    /**
     * Get the trait for a schema key.
     */
    public function getTraitForSchemaKey(string $schemaKey): ?string
    {
        return self::SCHEMA_PACKAGE_MAP[$schemaKey]['trait'] ?? null;
    }

    /**
     * Get the interface for a schema key.
     */
    public function getInterfaceForSchemaKey(string $schemaKey): ?string
    {
        return self::SCHEMA_PACKAGE_MAP[$schemaKey]['interface'] ?? null;
    }

    /**
     * Get the required package for a schema key.
     */
    public function getPackageForSchemaKey(string $schemaKey): ?string
    {
        return self::SCHEMA_PACKAGE_MAP[$schemaKey]['package'] ?? null;
    }

    /**
     * Check if any of the packages are installed.
     *
     * @param  array<string>  $packages
     * @param  array<string, string>  $installed
     */
    private function hasAnyPackage(array $packages, array $installed): bool
    {
        foreach ($packages as $package) {
            if (isset($installed[$package])) {
                return true;
            }
        }

        return false;
    }
}
