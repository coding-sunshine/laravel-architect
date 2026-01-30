# Studio API Reference

This document lists the main Studio API endpoints used for draft generation and wizards, with request and response shapes. Use it as a single reference for frontend and API consumers.

Base path: `/architect/api` (or your configured `architect.api.route_prefix`). All endpoints expect `Accept: application/json` and, for mutating requests, `Content-Type: application/json`. CSRF token is required for state-changing requests when using the web UI.

---

## Draft generation (AI)

### POST `/architect/api/draft-from-ai`

Generate a full draft from a natural language description. Optionally apply the result to the draft file.

**Request body**

| Field         | Type    | Required | Description                                      |
|---------------|---------|----------|--------------------------------------------------|
| `description` | string  | Yes      | Natural language description of the app/schema. |
| `apply`       | boolean | No       | If `true`, write the generated YAML to the draft file. Default `false`. |

**Response (200)**

| Field    | Type    | Description                          |
|----------|---------|--------------------------------------|
| `yaml`   | string  | Generated draft YAML.                |
| `applied`| boolean | Whether the draft file was updated.  |

**Errors:** `422` if `description` is missing or empty; `500` on generation failure.

**Example**

```json
// Request
{ "description": "A blog with Post and Comment models.", "apply": false }

// Response
{ "yaml": "schema_version: '1.0'\nmodels:\n  Post: ...", "applied": false }
```

---

## Simple generate

### POST `/architect/api/simple-generate`

Generate a draft from a description and return a **summary** (model/action/page counts) plus the full YAML. Does not write to the draft file. Intended for “import then edit” flow: show summary first, optionally show YAML via a “Show YAML” toggle, then apply or merge.

**Request body**

| Field         | Type   | Required | Description                                      |
|---------------|--------|----------|--------------------------------------------------|
| `description` | string | Yes      | Natural language description of the app/schema. |

**Response (200)**

| Field     | Type   | Description                                      |
|-----------|--------|--------------------------------------------------|
| `summary` | object | `{ models: number, actions: number, pages: number }` |
| `yaml`    | string | Full generated draft YAML.                      |

**Errors:** `422` if `description` is missing or empty; `500` on generation failure.

**One-shot flow:** In the Studio, after using **Summary first** (this endpoint), you can click **Expand to full draft** to call `POST /architect/api/draft-from-ai` with the same description and receive the full YAML in one step (no summary). That replaces the proposed draft with the complete schema.

**Example**

```json
// Request
{ "description": "A blog with Post and Comment." }

// Response
{ "summary": { "models": 2, "actions": 6, "pages": 2 }, "yaml": "..." }
```

---

## Plan

### POST `/architect/api/plan`

Compute the build plan for the current draft file (no body). Returns a sequence of steps (artisan commands and generators) with optional path hints showing where files will be created or modified.

**Request:** No body. Uses the draft file from `config('architect.draft_path')`.

**Response (200)**

| Field   | Type   | Description |
|---------|--------|--------------|
| `steps` | array  | List of step objects (see below). |
| `summary` | object | `{ models: number, actions: number, pages: number }`. |

Each step in `steps` has:

| Field         | Type   | Description |
|---------------|--------|-------------|
| `type`        | string | `artisan` or `generate`. |
| `name`        | string | Step name (e.g. `model:Post`, `patch_model:Post`). |
| `description` | string | Human-readable description. |
| `command`     | string | (optional) Artisan command when `type === 'artisan'`. |
| `generator`   | string | (optional) Generator name when `type === 'generate'`. |
| `path_hint`   | string | (optional) Path or glob where output will go (e.g. `app/Models/Post.php`, `database/migrations/*_create_posts_table.php`). |

**Errors:** `404` if draft file not found; `422` if draft is invalid (with `error` message).

---

## Revert last build

### POST `/architect/api/revert`

Restore files that were overwritten by the last successful build. Uses a backup stored in state when the build ran. After reverting, the backup is cleared.

**Request:** No body.

**Response (200)**

| Field      | Type     | Description |
|------------|----------|-------------|
| `success`  | boolean  | `true` if all backed-up files were restored. |
| `restored` | string[] | Paths that were restored. |
| `errors`   | string[] | Error messages if any path could not be restored. |

**Example**

```json
{ "success": true, "restored": ["app/Models/Post.php", "database/migrations/2024_01_01_000000_create_posts_table.php"], "errors": [] }
```

---

## Wizards

All wizard endpoints accept an optional `yaml` string in the request body. If present, the wizard uses that as the current draft; otherwise it uses the draft file from disk. Responses return the updated draft YAML and a short summary.

### POST `/architect/api/wizard/add-model`

Add a new model and default CRUD actions, pages, and route.

**Request body**

| Field           | Type    | Required | Description                                      |
|-----------------|--------|----------|--------------------------------------------------|
| `name`         | string | Yes      | Model name (e.g. `Post`).                        |
| `table`        | string | No       | Table name; default inferred (e.g. `posts`).     |
| `columns`      | object | No       | Column definitions (e.g. `{ "name": "string" }`). Default `{ "name": "string" }`. |
| `infer_from_db`| boolean| No       | If `true`, infer columns from DB schema for `table`. |
| `yaml`         | string | No       | Current draft YAML; if omitted, draft file is used. |

**Response (200)**

| Field     | Type   | Description                                                       |
|-----------|--------|-------------------------------------------------------------------|
| `summary` | object | `{ models: number, actions: number, pages: number }`              |
| `yaml`    | string | Updated draft YAML.                                               |

**Errors:** `422` if `name` is missing or validation fails (with `errors` array).

---

### POST `/architect/api/wizard/add-crud-resource`

Add full CRUD (actions, pages, resource route) for an existing model.

**Request body**

| Field        | Type   | Required | Description                          |
|--------------|--------|----------|--------------------------------------|
| `model_name`| string | Yes      | Model name (e.g. `Post`).            |
| `yaml`      | string | No       | Current draft YAML; if omitted, draft file is used. |

**Response (200)**

| Field     | Type   | Description                                                       |
|-----------|--------|-------------------------------------------------------------------|
| `summary` | object | `{ models: number, actions: number, pages: number }`             |
| `yaml`    | string | Updated draft YAML.                                               |

**Errors:** `422` if `model_name` is missing or validation fails.

---

### POST `/architect/api/wizard/add-relationship`

Add a relationship between two models.

**Request body**

| Field        | Type   | Required | Description                          |
|--------------|--------|----------|--------------------------------------|
| `from_model`| string | Yes      | Source model name (e.g. `Comment`).   |
| `type`      | string | Yes      | One of: `belongsTo`, `hasMany`, `hasOne`, `belongsToMany`. |
| `to_model`  | string | Yes      | Target model name (e.g. `Post`).     |
| `yaml`      | string | No       | Current draft YAML; if omitted, draft file is used. |

**Response (200)**

| Field     | Type   | Description                          |
|-----------|--------|--------------------------------------|
| `summary` | object | `{ relationship: "FromModel type ToModel" }` |
| `yaml`    | string | Updated draft YAML.                 |

**Errors:** `422` if any of `from_model`, `type`, `to_model` is missing or invalid, or validation fails.

---

### POST `/architect/api/wizard/add-page`

Add a page (and route) to the draft.

**Request body**

| Field   | Type   | Required | Description                                      |
|---------|--------|----------|--------------------------------------------------|
| `name`  | string | Yes      | Page name (e.g. `Dashboard`).                    |
| `type`  | string | No       | One of: `index`, `show`, `create`, `edit`. Default `index`. |
| `model` | string | No       | Optional model to attach the page to.            |
| `yaml`  | string | No       | Current draft YAML; if omitted, draft file is used. |

**Response (200)**

| Field     | Type   | Description                    |
|-----------|--------|--------------------------------|
| `summary` | object | `{ pages: number }`            |
| `yaml`    | string | Updated draft YAML.           |

**Errors:** `422` if `name` is missing or validation fails.

---

## Other relevant endpoints

| Endpoint              | Method | Description |
|-----------------------|--------|-------------|
| `GET /architect/api/context`  | GET    | Full Studio context (stack, packages, existing_models, app_model, fingerprint, etc.). |
| `GET /architect/api/draft`    | GET    | Read current draft file; returns `{ yaml, exists }`. |
| `PUT /architect/api/draft`    | PUT    | Write draft file; body `{ yaml }` or raw body. Returns `{ valid, saved }`. |
| `POST /architect/api/plan`    | POST   | Build plan for current draft; returns `{ steps, summary }` with `path_hint` per step. |
| `POST /architect/api/build`   | POST   | Run build; body optional `{ only?: string[], force?: boolean }`. Returns `{ success, generated, skipped, warnings, errors }`. |
| `POST /architect/api/revert`  | POST   | Revert last build by restoring backed-up file contents. Returns `{ success, restored, errors }`. |
| `POST /architect/api/import`  | POST   | Import from codebase; body optional `{ models?: string[], merge_schema_columns?: boolean }`. Returns draft object (models, actions, pages). |

For AI-specific endpoints (chat, suggestions, validate, generate-code, etc.), see [AI features](ai-features.md).
