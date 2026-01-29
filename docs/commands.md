# Commands

Reference for all Architect Artisan commands. For configuration options, see [Configuration](configuration.md).

## architect:draft

**Purpose:** Generate a draft YAML file from a natural language description (when Prism is available) or create a stub.

**Signature:**

```bash
php artisan architect:draft [description] [--extend=] [--output=]
```

- `description` (optional): Short description of the app or feature (e.g. "blog with posts and comments"). If omitted, the command may prompt.
- `--extend`: Path to an existing draft file to extend (merge new content into it).
- `--output`: Path where to write the generated YAML. Defaults to `config('architect.draft_path')`.

**Example:**

```bash
php artisan architect:draft "product catalog with categories"
php artisan architect:draft "add orders and order items" --extend=draft.yaml --output=draft.yaml
```

**Exit codes:** 0 on success, 1 on failure (e.g. missing Prism when AI is enabled, or write error).

**Notes:** If Prism is not installed or AI is disabled in config, the command outputs a minimal stub draft based on the first word of the description. See [AI and Prism](ai-and-prism.md).

---

## architect:validate

**Purpose:** Validate the draft file for syntax (YAML) and schema (models, actions, pages).

**Signature:**

```bash
php artisan architect:validate [draft]
```

- `draft` (optional): Path to the draft file. Defaults to `config('architect.draft_path')`.

**Example:**

```bash
php artisan architect:validate
php artisan architect:validate /path/to/draft.yaml
```

**Exit codes:** 0 when valid, 1 when file not found or validation fails.

---

## architect:plan

**Purpose:** Show what would be generated (dry run) without writing files. Displays counts of models, actions, and pages.

**Signature:**

```bash
php artisan architect:plan [draft]
```

- `draft` (optional): Path to the draft file. Defaults to `config('architect.draft_path')`.

**Example:**

```bash
php artisan architect:plan
```

**Exit codes:** 0 when draft is valid and plan is shown, 1 when draft not found or invalid.

---

## architect:build

**Purpose:** Generate code from the draft. Idempotent: running again with the same draft will not create duplicate migrations and will skip work when the draft hash has not changed.

**Signature:**

```bash
php artisan architect:build [draft] [--only=*] [--force]
```

- `draft` (optional): Path to the draft file. Defaults to `config('architect.draft_path')`.
- `--only`: Comma-separated list of generator names to run (e.g. `model`, `migration`, `factory`). If omitted, all applicable generators run.
- `--force`: Overwrite files even when draft hash unchanged (use with care).

**Example:**

```bash
php artisan architect:build
php artisan architect:build --only=model,migration
php artisan architect:build --force
```

**Exit codes:** 0 on success, 1 when draft not found or a generator throws.

**Notes:** Generated file paths and hashes are stored in the state file. See [Concepts](concepts.md) for ownership and change detection.

---

## architect:status

**Purpose:** Show current Architect state and the list of generated files (path, hash, ownership).

**Signature:**

```bash
php artisan architect:status
```

**Example:**

```bash
php artisan architect:status
```

**Exit codes:** 0. If no files have been generated yet, the command reports that and suggests running `architect:build`.

---

## architect:packages

**Purpose:** List detected Composer packages and whether they are known to Architect. Shows suggested commands and draft extensions for known packages.

**Signature:**

```bash
php artisan architect:packages [--json]
```

- `--json`: Output as JSON (packages array with `name`, `version`, `known`, `hints`).

**Example:**

```bash
php artisan architect:packages
php artisan architect:packages --json
```

**Exit codes:** 0. If no Composer packages are detected, suggests running `composer install`.

**Notes:** See [Packages](packages.md) for the registry and how to extend it.

---

## architect:explain

**Purpose:** Output a short summary of the draft (models, actions, pages, what would be generated). Useful for tooling or to answer "what will this draft do?"

**Signature:**

```bash
php artisan architect:explain [draft] [--json]
```

- `draft` (optional): Path to the draft file. Defaults to `config('architect.draft_path')`.
- `--json`: Output as JSON (draft_path, models, actions, pages, counts).

**Example:**

```bash
php artisan architect:explain
php artisan architect:explain draft.yaml --json
```

**Exit codes:** 0 when draft is valid and summary is shown, 1 when draft not found or invalid.

**Notes:** See [Troubleshooting](troubleshooting.md) for "what will this draft do?".

---

## architect:watch

**Purpose:** Watch the draft file and run `architect:build` when it changes. Useful for iterative workflow.

**Signature:**

```bash
php artisan architect:watch [--poll] [--interval=1]
```

- `--poll`: Use polling instead of file system events (for environments where native watchers are limited).
- `--interval`: Polling interval in seconds when using `--poll` (default 1).

**Example:**

```bash
php artisan architect:watch
php artisan architect:watch --poll --interval=2
```

**Exit codes:** 0 when starting (runs until Ctrl+C). 1 when draft file not found or removed.

**Notes:** See [Getting Started](getting-started.md) for iterative workflow.

---

## architect:fix

**Purpose:** When draft validation or build fails, suggest or apply a fix. With `--ai`, use Prism to suggest YAML/code changes (when implemented). Without `--ai`, outputs the validation error and suggests manual fix.

**Signature:**

```bash
php artisan architect:fix [draft] [--dry-run] [--ai]
```

- `draft` (optional): Path to the draft file.
- `--dry-run`: Show suggested fix without applying (when AI fix is implemented).
- `--ai`: Use AI (Prism) to suggest fix when validation fails.

**Example:**

```bash
php artisan architect:fix
php artisan architect:fix draft.yaml --ai
```

**Exit codes:** 0 when draft is valid. 1 when validation fails.

**Notes:** See [AI and Prism](ai-and-prism.md) and [Troubleshooting](troubleshooting.md).

---

## architect:import

**Purpose:** Reverse-engineer the existing codebase into a draft YAML. Scans `app/Models`, `app/Actions`, and `resources/js/pages` (or `resources/views/pages`) to infer models, actions, and pages.

**Signature:**

```bash
php artisan architect:import [--models=] [--output=] [--from-database]
```

- `--models`: Comma-separated model names to import (default: all models except User).
- `--output`: Path to write draft YAML (default: stdout).
- `--from-database`: Import schema from the database (not yet implemented).

**Example:**

```bash
php artisan architect:import
php artisan architect:import --output=draft.yaml
php artisan architect:import --models=Post,Comment --output=draft.yaml
```

**Exit codes:** 0 on success. 1 when write to file fails.

**Notes:** Import infers a minimal draft (fillable columns from models, action names from Actions, page names from pages directories). Limitations: only standard Laravel structure; relationships and validation rules are not inferred. See [Troubleshooting](troubleshooting.md).

---

## architect:starter

**Purpose:** Copy a bundled starter draft (blog, saas, api) to the draft path or output to stdout.

**Signature:**

```bash
php artisan architect:starter {name} [--output=] [--stdout]
```

- `name`: Starter name (`blog`, `saas`, `api`).
- `--output`: Path to write draft (default: `config('architect.draft_path')`).
- `--stdout`: Output YAML to stdout instead of writing a file.

**Example:**

```bash
php artisan architect:starter blog
php artisan architect:starter saas --output=draft.yaml
php artisan architect:starter api --stdout
```

**Exit codes:** 0 on success. 1 when starter not found or write fails.

**Notes:** See [Templates](templates.md).

---

## architect:why

**Purpose:** Report which draft section and generator produced a generated file (for debugging).

**Signature:**

```bash
php artisan architect:why {path}
```

- `path`: File path (relative to project root or absolute), e.g. `app/Models/Post.php`.

**Example:**

```bash
php artisan architect:why app/Models/Post.php
```

**Exit codes:** 0 when file is in state. 1 when file not tracked.

**Notes:** See [Troubleshooting](troubleshooting.md) — "Why was this file generated?".

---

## architect:check

**Purpose:** Run validate + plan and show a checklist (draft exists, draft valid). CI-friendly.

**Signature:**

```bash
php artisan architect:check [draft]
```

- `draft` (optional): Path to draft file.

**Example:**

```bash
php artisan architect:check
```

**Exit codes:** 0 when all checks pass. 1 when draft missing or invalid.

**Notes:** Run `architect:plan` or `architect:build --dry-run` in CI; use `architect:check` for a quick checklist.
