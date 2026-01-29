# Packages

Architect discovers Composer-installed packages and maintains a **package registry** of "known" packages. Known packages can have draft schema extensions, generator variants, and suggested Artisan commands. You can extend the registry via config.

## Detected packages

Architect reads installed packages from:

- `vendor/composer/installed.json` (Composer 2.2+)
- `vendor/composer/installed.php`
- `composer.lock` (fallback)

The list is used by `architect:packages` and (in future) by package-aware generators.

## architect:packages command

List detected packages and whether they are known to Architect:

```bash
php artisan architect:packages
```

Output includes: package name, version, "Known to Architect" (Yes/No), and suggested commands for known packages.

**Options:**

- `--json`: Output as JSON (packages array with `name`, `version`, `known`, `hints`).

## Built-in known packages

Architect ships with hints for these packages:

| Package | Draft extensions | Generator variants | Suggested commands |
|---------|------------------|--------------------|--------------------|
| **filament/filament** | Add `filament: true` on model for Filament resource | Filament resource from model | `php artisan make:filament-resource` |
| **spatie/laravel-medialibrary** | Add `media: true` on model for HasMedia | Media conversions and registration | — |
| **spatie/laravel-permission** | Roles/permissions on model | Permission setup | `php artisan permission:cache-reset` |
| **inertiajs/inertia-laravel** | — | Inertia page generation | — |
| **livewire/livewire** | — | Livewire component generation | `php artisan make:livewire` |
| **livewire/volt** | — | Volt single-file generation | — |

When you run `architect:packages`, any of these that are installed appear as "Known to Architect" with their hints. Future phases may use these hints to suggest draft additions (e.g. "You have Filament; add `filament: true` to model X") or to enable generator variants.

## Extending the registry

Add custom package hints in `config/architect.php` under `packages`:

```php
'packages' => [
    'vendor/custom-package' => [
        'draft_extensions' => [
            'Add "feature: true" on model to generate a feature resource.',
        ],
        'generator_variants' => [
            'Custom resource generator',
        ],
        'suggested_commands' => [
            'php artisan custom:setup',
        ],
    ],
],
```

Keys are Composer package names. Values are merged with built-in hints for that package (if any); custom entries are additive.

## See also

- [Configuration](configuration.md) – `packages` config key.
- [Commands](commands.md) – `architect:packages` reference.
