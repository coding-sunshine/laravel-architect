# Concepts

This page explains the main ideas behind Laravel Architect so you can reason about how it works without implementation details.

## Draft as source of truth

The **draft** is a YAML file (by default `draft.yaml` at the project root) that describes your application structure: models and their columns/relationships, actions, pages, and route hints. Architect does not generate this file from your existing code by default; you create or edit it (or generate it from natural language with Prism). Everything that gets generated—models, migrations, factories, controllers, pages, tests—is derived from the draft. So the draft is the single source of truth: change the draft, then run `architect:build` to update generated code.

## Idempotent builds

Running `architect:build` multiple times with the same draft should not create duplicate migrations or overwrite files in an unpredictable way. Architect tracks what it generated in a **state file** (by default `.architect-state.json`). For migrations, it reuses the same migration file per table when the draft has not changed; for other artifacts, it overwrites only what it owns. So you can run build repeatedly (e.g. after pulling draft changes) and get a stable result.

## State file

The state file stores:

- Which draft file was last built and its hash
- For each generated file: path, content hash, and ownership

Architect uses this to:

- Skip building when the draft hash has not changed
- Reuse the same migration path for a given table (one migration per table)
- Report in `architect:status` what was generated

Do not edit the state file by hand unless you know what you are doing. Deleting it will cause the next build to regenerate everything and, for migrations, may create new timestamped files instead of reusing existing ones.

## Ownership (regenerate vs scaffold_only)

Generated files are classified by **ownership**:

- **regenerate**: Architect may overwrite these on every build (e.g. migrations, factories, seeders). Use when the file is fully derived from the draft.
- **scaffold_only**: Architect creates the file once; if you change it by hand, Architect does not overwrite it on subsequent builds (or you can use `--force` to overwrite). Use for app code you expect to customize (e.g. controllers, actions, pages).

Ownership is configured in `config/architect.php` under `ownership` (glob patterns). The same concept applies in the state file so that status can show who "owns" each path.

## Stack awareness

Architect detects your application's **frontend stack** (Inertia React, Inertia Vue, Livewire, Volt, or Blade) and generates pages and controllers to match. Detection is automatic when `config('architect.stack')` is `auto`; you can override it with `ARCHITECT_STACK` or in `config/architect.php`. The **Page** and **Controller** generators branch on the resolved stack (e.g. `.tsx` for Inertia React, Livewire component + Blade for Livewire). See [Stacks](stacks.md) for supported stacks, detection order, and override.

## Generators (what gets created from what)

Architect runs a set of **generators** during build. Each generator is responsible for one kind of artifact:

| Generator   | Input from draft      | Output |
|-------------|------------------------|--------|
| Model       | `models.*`            | `app/Models/*.php` |
| Migration   | `models.*` (columns)  | `database/migrations/*_create_*_table.php` |
| Factory     | `models.*`            | `database/factories/*Factory.php` |
| Seeder      | `models.*.seeder`     | `database/seeders/{Category}/*Seeder.php` |
| Action      | `actions.*`           | `app/Actions/*.php` |
| Controller  | `models.*` + actions  | `app/Http/Controllers/*Controller.php` (stack-specific: Inertia vs `view()`) |
| Request     | `models.*` + actions  | `app/Http/Requests/*Request.php` |
| Route       | `models.*` / routes   | `routes/architect.php` |
| Page        | `pages.*`             | Stack-specific: `.tsx` / `.vue` / Livewire / Volt / Blade (see [Stacks](stacks.md)) |
| TypeScript  | `models.*`            | `resources/js/types/architect.d.ts` |
| Test        | `models.*` / actions  | `tests/Feature/Controllers/*Test.php` |

Not all generators run for every draft; for example, the seeder generator runs only for models that have a `seeder` block. The plan command (`architect:plan`) shows what would be generated without writing files.

## Change detection

Before building, Architect compares the current draft file hash to the one stored in state. If they match and you do not use `--force`, the build is skipped (no changes). So you can run `architect:build` in CI or after pull without unnecessary file writes when the draft did not change.

## Command-first mode

When **command-first** orchestration is enabled (`config('architect.orchestration.command_first')` or `ARCHITECT_ORCHESTRATION_COMMAND_FIRST`), Architect can run Laravel Artisan commands (e.g. `make:model`, `make:migration`) first and then patch or fill the generated files from the draft, instead of generating entire files from scratch. This reduces reliance on full-file generation and keeps AI context minimal when AI is used elsewhere (draft from NL, explain, fix). A **build planner** produces a sequence of steps (artisan commands, then generator patches). Command-first is optional; when disabled, the build uses the standard generators only.

## How we use AI

Architect uses AI only for: (1) **draft generation** from natural language (when Prism is available), (2) **explain** draft (when implemented), and (3) **fix** validation or runtime errors with minimal snippet and schema rule (when implemented). Migrations, models, and other artifacts are generated by code generators or by running Artisan commands and patching—not by sending full file content to AI. You can set `architect.ai.max_context_tokens` to cap context size when using AI. See [AI and Prism](ai-and-prism.md).
