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
        'filament/filament' => [
            'draft_extensions' => ['filament: true on model for Filament resource'],
            'generator_variants' => ['Filament resource from model'],
            'suggested_commands' => ['php artisan make:filament-resource'],
        ],
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
            'draft_extensions' => array_merge($base['draft_extensions'] ?? [], $user['draft_extensions'] ?? []),
            'generator_variants' => array_merge($base['generator_variants'] ?? [], $user['generator_variants'] ?? []),
            'suggested_commands' => array_merge($base['suggested_commands'] ?? [], $user['suggested_commands'] ?? []),
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
