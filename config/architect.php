<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Draft File Location
    |--------------------------------------------------------------------------
    |
    | The default path to the draft.yaml file that defines your application
    | structure. This file is the source of truth for code generation.
    |
    */
    'draft_path' => base_path('draft.yaml'),

    /*
    |--------------------------------------------------------------------------
    | Application Stack
    |--------------------------------------------------------------------------
    |
    | The frontend stack used by the app (Breeze variants). Used by the
    | page generator to emit the correct format. Set to "auto" to detect
    | from the codebase (Inertia React, Inertia Vue, Livewire, Volt, Blade).
    |
    */
    'stack' => env('ARCHITECT_STACK', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | State File Location
    |--------------------------------------------------------------------------
    |
    | The path to the state file that tracks generated files, their hashes,
    | and ownership information for idempotent builds.
    |
    */
    'state_path' => base_path('.architect-state.json'),

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure AI integration for generating YAML drafts from natural
    | language descriptions. Requires echolabs/prism package.
    |
    */
    'ai' => [
        'enabled' => env('ARCHITECT_AI_ENABLED', true),
        'provider' => env('ARCHITECT_AI_PROVIDER', 'openrouter'),
        'model' => env('ARCHITECT_AI_MODEL'),
        'max_retries' => 2,
        'retry_with_feedback' => true,
        'max_context_tokens' => env('ARCHITECT_AI_MAX_CONTEXT_TOKENS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Orchestration (Command-first)
    |--------------------------------------------------------------------------
    |
    | When command_first is true, build runs Artisan commands (e.g. make:model,
    | make:migration) first and then patches or fills generated files from the
    | draft, minimising full-file AI generation. Reduces AI context usage.
    |
    */
    'orchestration' => [
        'command_first' => env('ARCHITECT_ORCHESTRATION_COMMAND_FIRST', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Generation Targets
    |--------------------------------------------------------------------------
    |
    | Configure which targets to generate code for. Each target can be
    | enabled/disabled independently.
    |
    */
    'targets' => [
        'web' => [
            'enabled' => true,
            'pages' => 'inertia',
            'routes_file' => 'routes/architect.php',
        ],
        'api' => [
            'enabled' => false,
            'version' => 'v1',
            'routes_file' => 'routes/api.php',
        ],
        'admin' => [
            'enabled' => false,
            'driver' => 'filament',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Ownership Strategy
    |--------------------------------------------------------------------------
    |
    | Define how each type of generated file should be handled on subsequent
    | builds. Options: 'regenerate' (always overwrite), 'scaffold_only'
    | (create once, warn on change).
    |
    */
    'ownership' => [
        'database/migrations/*' => 'regenerate',
        'database/factories/*' => 'regenerate',
        'database/seeders/data/*.json' => 'regenerate',
        'app/Models/*' => 'scaffold_only',
        'app/Actions/*' => 'scaffold_only',
        'app/Http/Controllers/*' => 'scaffold_only',
        'app/Http/Requests/*' => 'scaffold_only',
        'app/Policies/*' => 'scaffold_only',
        'resources/js/pages/*' => 'scaffold_only',
        'tests/*' => 'scaffold_only',
    ],

    /*
    |--------------------------------------------------------------------------
    | Code Conventions
    |--------------------------------------------------------------------------
    |
    | Configure the code generation conventions to match your project style.
    |
    */
    'conventions' => [
        'use_actions' => true,
        'controller_style' => 'thin',
        'generate_tests' => true,
        'test_framework' => 'pest',
        'generate_typescript_types' => true,
        'run_wayfinder' => true,
        'seeder_categories' => ['Essential', 'Development', 'Production'],
        'default_seeder_category' => 'Development',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules Mapping
    |--------------------------------------------------------------------------
    |
    | Map column types to default validation rules. These can be overridden
    | per-model or per-field in the draft.yaml file.
    |
    */
    'validation' => [
        'string' => ['required', 'string', 'max:255'],
        'text' => ['required', 'string'],
        'longtext' => ['required', 'string'],
        'integer' => ['required', 'integer'],
        'bigInteger' => ['required', 'integer'],
        'decimal' => ['required', 'numeric'],
        'boolean' => ['required', 'boolean'],
        'date' => ['required', 'date'],
        'datetime' => ['required', 'date'],
        'timestamp' => ['nullable', 'date'],
        'email' => ['required', 'string', 'email', 'max:255'],
        'password' => ['required', 'confirmed'],
        'uuid' => ['required', 'uuid'],
        'json' => ['required', 'array'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Post-Build Hooks
    |--------------------------------------------------------------------------
    |
    | Commands to run after a successful build. Useful for running code
    | formatters, static analysis, or other tools.
    |
    */
    'hooks' => [
        'before_build' => [],
        'after_build' => [
            // 'wayfinder:generate',
            // 'pint',
        ],
        'on_failure' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | History / Audit Trail
    |--------------------------------------------------------------------------
    |
    | Enable tracking of build history for debugging and rollback.
    |
    */
    'history' => [
        'enabled' => true,
        'driver' => 'file',
        'path' => storage_path('architect/history'),
        'max_entries' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Hints (Package Registry)
    |--------------------------------------------------------------------------
    |
    | Optional custom package hints. Keys are Composer package names; values
    | can include draft_extensions, generator_variants, and suggested_commands.
    | Merged with Architect's built-in known packages (Filament, Spatie, etc.).
    |
    */
    /*
    |--------------------------------------------------------------------------
    | UI Builder (Visual Schema Designer)
    |--------------------------------------------------------------------------
    |
    | Configure the Architect Studio route and frontend driver. When driver
    | is "auto", the package detects Inertia+shadcn, Livewire+Flux, Flux Pro,
    | or falls back to Blade.
    |
    */
    'ui' => [
        'driver' => env('ARCHITECT_UI_DRIVER', 'auto'),
        'route_prefix' => env('ARCHITECT_UI_ROUTE_PREFIX', 'architect'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Hints (Package Registry)
    |--------------------------------------------------------------------------
    |
    */
    'packages' => [
        // Example:
        // 'vendor/package' => [
        //     'draft_extensions' => ['Add "feature: true" on model for X'],
        //     'generator_variants' => ['Generator description'],
        //     'suggested_commands' => ['php artisan some:command'],
        // ],
    ],
];
