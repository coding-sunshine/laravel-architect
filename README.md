# Laravel Architect

[![Latest Version on Packagist](https://img.shields.io/packagist/v/coding-sunshine/laravel-architect.svg)](https://packagist.org/packages/coding-sunshine/laravel-architect)
[![License](https://img.shields.io/badge/license-GPL--3.0-blue.svg)](LICENSE.md)

AI-powered, YAML-driven scaffolding for Laravel applications.

Define your application structure in YAML. Use natural language (with Prism) to draft it. Generate models, migrations, actions, controllers, pages, and tests—idempotent and trackable.

## Features

- **YAML as source of truth** – Define models, relationships, actions, and pages in `draft.yaml`
- **AI-powered drafting** – Generate YAML from natural language when [Prism](https://github.com/echolabs/prism) is available
- **Idempotent builds** – Run `architect:build` repeatedly without duplicates
- **Change detection** – Only regenerate what changed
- **Convention-based** – Generates code that follows Laravel and your project conventions (Actions, thin controllers, Inertia)
- **Stack-aware** – Detects Inertia React, Inertia Vue, Livewire, Volt, or Blade and generates the right pages and controllers

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Symfony YAML

## Installation

```bash
composer require --dev coding-sunshine/laravel-architect
```

Publish the config (optional):

```bash
php artisan vendor:publish --tag=architect-config
```

## Quick Start

### 1. Create a draft

**From natural language** (when Prism is installed):

```bash
php artisan architect:draft "blog with posts, comments, and tags"
```

**Or create `draft.yaml` manually:**

```yaml
schema_version: "1.0"

models:
  Post:
    title: string:400
    content: longtext
    published_at: timestamp nullable
    author_id: id:User foreign
    relationships:
      belongsTo: User:author
      hasMany: Comment
    seeder:
      category: development
      count: 10

actions:
  CreatePost:
    model: Post
    return: Post
  UpdatePost:
    model: Post
    params: [Post, attributes]
    return: void
  DeletePost:
    model: Post
    params: [Post]
    return: void

pages:
  Post: {}
```

### 2. Validate

```bash
php artisan architect:validate
```

### 3. Plan (dry run)

```bash
php artisan architect:plan
```

### 4. Build

```bash
php artisan architect:build
```

### 5. Status

```bash
php artisan architect:status
```

## Commands

| Command | Description |
|---------|-------------|
| `architect:draft` | Generate draft.yaml from natural language |
| `architect:validate` | Validate draft syntax and schema |
| `architect:plan` | Show what would be generated (dry run) |
| `architect:build` | Generate code from draft (idempotent) |
| `architect:status` | Show current state and generated files |
| `architect:packages` | List detected packages and Architect-known hints |
| `architect:explain` | Output draft summary (models, actions, pages) |
| `architect:watch` | Watch draft and run build on change |
| `architect:fix` | Suggest or apply fix when validation fails |
| `architect:starter` | Load a starter draft (blog, saas, api) |
| `architect:why` | Show which generator produced a file |
| `architect:check` | Run validate + plan checklist |
| `architect:import` | Reverse-engineer codebase to draft YAML |

## Documentation

Full documentation lives in the [docs/](docs/) directory. Start with the [Documentation index](docs/README.md).

| Document | Description |
|----------|-------------|
| [Getting Started](docs/getting-started.md) | Install, first draft, validate, plan, build, status. |
| [Concepts](docs/concepts.md) | Draft as source of truth, idempotent builds, state, ownership, generators. |
| [Schema Reference](docs/SCHEMA.md) | Full draft.yaml schema (models, columns, actions, pages, routes). |
| [Commands](docs/commands.md) | Reference for all architect:* commands. |
| [Configuration](docs/configuration.md) | All config keys and options. |
| [Stacks](docs/stacks.md) | Supported stacks, detection, and override. |
| [Packages](docs/packages.md) | Detected packages, registry, and how to extend. |
| [AI and Prism](docs/ai-and-prism.md) | AI drafting, Prism setup, context minimisation. |
| [Visual Designer](docs/visual-designer.md) | Open the Studio, frontend stack, command–UI parity. |
| [Templates](docs/templates.md) | Starter drafts (blog, saas, api) and custom starters. |
| [Troubleshooting](docs/troubleshooting.md) | Common errors and fixes. |

## Configuration

See `config/architect.php` for:

- Draft and state file paths
- AI provider settings (Prism)
- File ownership (regenerate vs scaffold_only)
- Conventions (use_actions, test_framework, etc.)
- Validation rule mappings
- Post-build hooks

## Development

When developing the package locally, link it from your app:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../laravel-architect",
            "options": { "symlink": true }
        }
    ],
    "require-dev": {
        "coding-sunshine/laravel-architect": "dev-main"
    }
}
```

Then run `composer update coding-sunshine/laravel-architect`.

## Publishing

Maintainers: see [docs/PUBLISHING.md](docs/PUBLISHING.md) for tagging and Packagist release steps.

## License

GPL-3.0-or-later. See [LICENSE.md](LICENSE.md).

## Credits

- [Hardik Shah](https://github.com/coding-sunshine)
- [All Contributors](https://github.com/coding-sunshine/laravel-architect/contributors)
