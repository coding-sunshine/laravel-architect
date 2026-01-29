# Getting Started

This guide walks you through installing Laravel Architect and generating your first scaffold from a draft.

## Prerequisites

- PHP 8.2+
- Laravel 11 or 12
- Composer

## Installation

Install the package as a dev dependency:

```bash
composer require --dev coding-sunshine/laravel-architect
```

Publish the config (optional but recommended):

```bash
php artisan vendor:publish --tag=architect-config
```

This creates `config/architect.php` where you can set the draft path, state path, AI options, and conventions.

## Create your first draft

You can create a draft in two ways.

### Option 1: From natural language (when Prism is installed)

If you have [echolabs/prism](https://github.com/echolabs/prism) installed and configured:

```bash
php artisan architect:draft "blog with posts and comments"
```

This generates a `draft.yaml` (or the path in `config('architect.draft_path')`) with models, actions, and pages inferred from your description. You can then edit the file to refine columns and relationships.

### Option 2: Start from a template

Use a bundled starter draft:

```bash
php artisan architect:starter blog
```

This writes the `blog` starter (posts, comments, actions, pages) to your draft path. Other starters: `saas`, `api`. See [Templates](templates.md).

### Option 3: Create draft.yaml manually

Create a file at the project root named `draft.yaml` (or the path in `config('architect.draft_path')`). Example:

```yaml
schema_version: "1.0"

models:
  Post:
    title: string:400
    body: longtext
    published_at: timestamp nullable
    relationships:
      hasMany: Comment
    seeder:
      category: development
      count: 10

  Comment:
    body: text
    post_id: id:Post foreign
    relationships:
      belongsTo: Post

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
  Comment: {}
```

## Validate the draft

Check that your draft is valid YAML and passes the schema:

```bash
php artisan architect:validate
```

If there are errors, fix them in `draft.yaml` and run validate again.

## Plan (dry run)

See what would be generated without writing files:

```bash
php artisan architect:plan
```

This shows a summary of models, actions, and pages that will be generated.

## Build

Generate code from the draft:

```bash
php artisan architect:build
```

On success, you get models, migrations, factories, seeders (if configured), actions, controllers, form requests, routes, pages, TypeScript types, and tests. The build is idempotent: running it again without changing the draft will not create duplicate files.

## Check status

Inspect which files were generated and their ownership:

```bash
php artisan architect:status
```

## Watch (iterative workflow)

For iterative editing, you can watch the draft file and run `architect:build` when it changes:

```bash
php artisan architect:watch
```

Use `--poll` for environments where native file watchers are limited (e.g. some CI or remote setups). See [Commands](commands.md) for `architect:watch` and `architect:explain`.

## Next steps

- Read [Concepts](concepts.md) to understand draft as source of truth, state, and ownership.
- Read [Schema Reference](SCHEMA.md) for the full draft schema (columns, relationships, seeder, actions, pages).
- Use [Commands](commands.md) for detailed command options and examples.
