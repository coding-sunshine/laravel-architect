# Draft Schema Reference

The `draft.yaml` file is the source of truth for Architect code generation. This document describes the schema and conventions.

## Top-Level Keys

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `schema_version` | string | No | Schema version (default: `"1.0"`) |
| `models` | object | No* | Model definitions keyed by singular StudlyCase name |
| `actions` | object | No* | Action definitions keyed by action name (e.g. `CreatePost`) |
| `pages` | object | No* | Page definitions keyed by model/page name |
| `routes` | object | No | Route hints (e.g. `resource: true` per model) |

\* At least one of `models`, `actions`, or `pages` must be present.

## Models

Each key is a **singular** model name in StudlyCase (e.g. `Post`, `User`). Values are either:

- **Column definitions**: `column_name: type` or `column_name: type:length modifiers`
- **Reserved keys**: `relationships`, `seeder`, `softDeletes`, `timestamps`, `traits`

### Column Format

- **Type**: `string`, `text`, `longtext`, `integer`, `bigInteger`, `decimal`, `boolean`, `date`, `datetime`, `timestamp`, `json`, `uuid`
- **Foreign key**: `column_id: id:RelatedModel` (e.g. `author_id: id:User`)
- **Modifiers** (space-separated): `nullable`, `unique`, `index`, `foreign`

### Shorthands and normalization

When parsing, Architect **normalizes** model definitions so you can use short forms:

- **Column list:** You can define columns as an array of names. The normalizer expands them using conventions:
  - `id` → `id: bigIncrements`
  - `timestamps` → `created_at: timestamp nullable`, `updated_at: timestamp nullable`
  - `softDeletes` → `deleted_at: timestamp nullable`
  - Other names get types inferred from the column name (e.g. `email` → string:255, `published_at` → timestamp nullable, `is_active` → boolean, `*_id` → foreignId).
- **belongsTo FK:** If a model has `relationships.belongsTo: User` (or `User:author`) but does not define the foreign key column (e.g. `user_id`), the normalizer adds it (e.g. `user_id: foreignId`). You can still define the column explicitly to override.

Example shorthand:

```yaml
models:
  Post:
    columns: [id, title, slug, body, published_at, timestamps]
    relationships:
      belongsTo: User:author
```

This is expanded to include `id`, `title`, `slug`, `body`, `published_at`, `created_at`, `updated_at`, and `user_id` (for the belongsTo) with appropriate types.

### Examples

```yaml
models:
  Post:
    title: string:400
    slug: string:255 unique
    body: longtext
    published_at: timestamp nullable
    author_id: id:User foreign
    relationships:
      belongsTo: User:author
      hasMany: Comment
    seeder:
      category: development
      count: 10
      json: true
    softDeletes: true
    timestamps: true
```

### Seeder

- `category`: `essential` | `development` | `production` (default: `development`)
- `count`: number of records to create via factory (default: 5)
- `json`: if `true`, also seed from `database/seeders/data/{table}.json`

## Actions

Keys are **action class names** in StudlyCase (e.g. `CreatePost`, `UpdatePost`, `DeletePost`). Values can include:

- `model`: related model name
- `params`: array of parameter names or `{ name, type }`
- `return`: return type (`void`, model name, or `model` for the related model)

### Examples

```yaml
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
```

## Pages

Keys are typically model names (used as the page slug). Values are usually `{}` to generate standard resource pages (index, create, show, edit) under `resources/js/pages/{slug}/`.

```yaml
pages:
  Post: {}
  User: {}
```

## Routes

Used by RouteGenerator. When `routes` is present and models exist, `routes/architect.php` is generated with `Route::resource(...)` for each model. Include it from `routes/web.php`:

```php
require base_path('routes/architect.php');
```

## Optional tests block

Per model or action you can add a `tests` block (when supported by TestGenerator) with expectations (e.g. store validates email, redirects to index). TestGenerator reads this and emits corresponding Pest tests. Schema support for `tests` is optional; see the generator for the expected shape.

## Validation

Run `php artisan architect:validate` to check syntax and schema. The validator ensures:

- At least one of `models`, `actions`, or `pages` is present
- Model keys match StudlyCase
- Reserved keys are used correctly

## Package extensions

When certain packages are installed (e.g. Filament, Spatie Media Library), the draft schema can be extended with package-specific keys (e.g. `filament: true` on a model for Filament resource generation, or `media: true` for HasMedia). See [Packages](packages.md) and [Configuration](configuration.md) (`packages` key).

## Stack-specific behaviour

Page and controller output depend on the detected or configured **stack** (Inertia React, Inertia Vue, Livewire, Volt, Blade). The same draft produces different artifact formats per stack. See [Stacks](stacks.md).

## JSON Schema

The package includes `src/Schema/draft-schema.json` for programmatic validation. Use it with a JSON Schema validator (e.g. Opis) for stricter validation if needed.
