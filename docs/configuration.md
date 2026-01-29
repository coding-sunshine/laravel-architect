# Configuration

All configuration lives in `config/architect.php`. You can publish it with:

```bash
php artisan vendor:publish --tag=architect-config
```

## draft_path

**Type:** string (path)

**Default:** `base_path('draft.yaml')`

Path to the YAML draft file that defines your application structure. This file is the source of truth for code generation.

---

## stack

**Type:** string

**Default:** `env('ARCHITECT_STACK', 'auto')`

Frontend stack used by the page and controller generators. Set to `auto` to detect from the codebase (Inertia React, Inertia Vue, Livewire, Volt, Blade). Allowed values: `auto`, `inertia-react`, `inertia-vue`, `livewire`, `volt`, `blade`. See [Stacks](stacks.md).

---

## state_path

**Type:** string (path)

**Default:** `base_path('.architect-state.json')`

Path to the JSON state file that tracks the last built draft hash and all generated files (path, hash, ownership). Used for idempotent builds and migration path reuse.

---

## ai

**Type:** array

AI integration for generating YAML drafts from natural language. Requires [echolabs/prism](https://github.com/echolabs/prism).

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | `env('ARCHITECT_AI_ENABLED', true)` | Whether to use AI when generating drafts. |
| `provider` | string | `env('ARCHITECT_AI_PROVIDER', 'openrouter')` | Prism provider name. |
| `model` | string\|null | `env('ARCHITECT_AI_MODEL')` | Model to use (optional). |
| `max_retries` | int | 2 | Number of retries when validation fails after AI generation. |
| `retry_with_feedback` | bool | true | When true, send validation errors back to the model on retry. |
| `max_context_tokens` | int\|null | null | Optional cap on context size sent to the model (e.g. 4096). |

---

## orchestration

**Type:** array

Command-first orchestration. When enabled, build runs Artisan commands (e.g. `make:model`, `make:migration`) first and then patches generated files from the draft.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `command_first` | bool | `env('ARCHITECT_ORCHESTRATION_COMMAND_FIRST', false)` | When true, use command-first build (run Artisan, then patch). Reduces full-file generation. |

See [Concepts](concepts.md) and [AI and Prism](ai-and-prism.md).

---

## targets

**Type:** array

Generation targets (web, api, admin). Each can be enabled/disabled.

- **web**: `enabled`, `pages` (e.g. `inertia`), `routes_file` (e.g. `routes/architect.php`).
- **api**: `enabled`, `version`, `routes_file`. When enabled, API routes and controllers can be generated from the draft.
- **admin**: `enabled`, `driver` (e.g. `filament`). When enabled, admin resources (e.g. Filament) can be generated.

---

## ownership

**Type:** array (glob pattern => strategy)

Maps file patterns to ownership strategy:

- **regenerate**: Architect may overwrite on every build (migrations, factories, seeders).
- **scaffold_only**: Create once; do not overwrite unless `--force` is used (models, actions, controllers, pages, tests).

Example:

```php
'ownership' => [
    'database/migrations/*' => 'regenerate',
    'app/Models/*' => 'scaffold_only',
],
```

---

## conventions

**Type:** array

Code generation conventions:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `use_actions` | bool | true | Generate and use Action classes. |
| `controller_style` | string | `thin` | Controllers delegate to actions. |
| `generate_tests` | bool | true | Generate Pest/PHPUnit tests. |
| `test_framework` | string | `pest` | `pest` or `phpunit`. |
| `generate_typescript_types` | bool | true | Emit `resources/js/types/architect.d.ts`. |
| `run_wayfinder` | bool | true | Whether to run Wayfinder after build (if applicable). |
| `seeder_categories` | array | `['Essential', 'Development', 'Production']` | Allowed seeder category names. |
| `default_seeder_category` | string | `Development` | Default when `seeder.category` is omitted. |

---

## validation

**Type:** array (column type => rules)

Maps column types to default validation rules (used by the Request generator). Keys are types (e.g. `string`, `email`, `password`); values are arrays of rule strings.

---

## hooks

**Type:** array

Commands to run around builds:

- **before_build**: array of commands run before `architect:build`.
- **after_build**: array of commands run after a successful build (e.g. `wayfinder:generate`, `pint`).
- **on_failure**: array of commands run when build fails.

---

## ui (Studio)

**Type:** array

Architect Studio (Visual Schema Designer) route and frontend driver.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `driver` | string | `env('ARCHITECT_UI_DRIVER', 'auto')` | UI driver: `auto`, `inertia-react`, `livewire-flux`, `livewire-flux-pro`, `blade`. When `auto`, the package detects Inertia+shadcn, Livewire+Flux, or Flux Pro. |
| `route_prefix` | string | `env('ARCHITECT_UI_ROUTE_PREFIX', 'architect')` | URL prefix for the Studio (e.g. `architect` → `/architect`). |

See [Visual Designer](visual-designer.md).

---

## packages

**Type:** array (package name => hints)

Optional custom package hints. Keys are Composer package names (e.g. `vendor/package`). Values can include:

- **draft_extensions**: array of strings describing draft schema extensions (e.g. "Add `filament: true` on model for Filament resource").
- **generator_variants**: array of strings describing generator variants.
- **suggested_commands**: array of Artisan command strings.

Merged with Architect's built-in known packages (Filament, Spatie Media Library, Spatie Permission, Inertia, Livewire, Volt). Used by `architect:packages` and future package-aware generators. See [Packages](packages.md).

---

## history

**Type:** array

Audit trail for builds:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | true | Whether to record history. |
| `driver` | string | `file` | Storage driver. |
| `path` | string | `storage_path('architect/history')` | Where to store history entries. |
| `max_entries` | int | 100 | Maximum number of entries to keep. |
