# AI and Prism

Laravel Architect can generate draft YAML from natural language when [echolabs/prism](https://github.com/echolabs/prism) is installed and configured. This page explains when AI is used, how to configure it, and how context is kept small.

## When AI is used

AI (via Prism) is used in these cases:

1. **Draft from natural language** — When you run `architect:draft "description"`, the command calls Prism to generate a full draft YAML from your description. The output is validated against the draft schema; if validation fails, Architect can retry with the validation errors sent back to the model (when `ai.retry_with_feedback` is true).
2. **Explain** (when implemented) — A future `architect:explain` could send the draft to the model and return a short summary.
3. **Fix** — `architect:fix` runs validation; when it fails, you can run `architect:fix --ai` to use Prism to suggest a fix (AI fix application is planned; for now it outputs the error and suggests manual fix).

Architect does **not** use AI to generate raw PHP (e.g. migrations or controller bodies). Those are produced by the generators from the structured draft. So AI is only for drafting and, in the future, explaining and fixing.

## Prism requirement

- Install Prism in your Laravel app: `composer require echolabs/prism` (or your preferred provider).
- Configure your Prism provider and model in your app (e.g. OpenRouter, OpenAI). Architect reads `config('architect.ai')` for `enabled`, `provider`, `model`, `max_retries`, and `retry_with_feedback`; the actual API keys and provider setup are in your app’s Prism/config.
- If Prism is not installed or `architect.ai.enabled` is false, `architect:draft` falls back to a **stub** draft: a minimal YAML template based on the first word of your description (e.g. "blog" → model `Blog` with placeholder columns and actions).

## Configuration

In `config/architect.php` under `ai`:

| Key | Description |
|-----|-------------|
| `enabled` | Set to `false` to disable AI and always use the stub. |
| `provider` | Prism provider name (e.g. `openrouter`). |
| `model` | Optional model name; if not set, Prism uses its default. |
| `max_retries` | After a validation failure, how many times to retry with feedback (default 2). |
| `retry_with_feedback` | If true, validation errors are included in the follow-up prompt so the model can fix the YAML. |
| `max_context_tokens` | Optional cap on context size sent to the model (e.g. 4096). When set, input is limited to stay within the cap. |

Environment variables (optional): `ARCHITECT_AI_ENABLED`, `ARCHITECT_AI_PROVIDER`, `ARCHITECT_AI_MODEL`, `ARCHITECT_AI_MAX_CONTEXT_TOKENS`.

## Context minimisation

To keep token usage and cost low:

- The prompt for draft generation contains your description, optional existing draft (when extending), and a short system prompt that describes the schema. It does **not** send the entire codebase.
- On retry, only the validation error messages (and possibly the invalid YAML snippet) are added. Full file contents are not repeated unnecessarily.
- Future explain/fix flows will send only the draft (or a subset) and the error message, not generated PHP or the full app.
- You can set **max_context_tokens** (`architect.ai.max_context_tokens` or `ARCHITECT_AI_MAX_CONTEXT_TOKENS`) to cap the context size sent to the model. When set, Architect (or Prism) will truncate or limit input to stay within the limit.

## Command-first orchestration

When **command-first** is enabled (`config('architect.orchestration.command_first')` or `ARCHITECT_ORCHESTRATION_COMMAND_FIRST`), the build runs Laravel Artisan commands (e.g. `make:model`, `make:migration`) first and then patches or fills the generated files from the draft, instead of generating entire files in one go. This keeps AI out of migration/model generation: no full-file AI generation for code artifacts. AI is used only for draft generation, explain, and fix (minimal snippet). See [Concepts](concepts.md) for "Command-first mode".

## Retries and validation feedback

When AI generates YAML:

1. Architect parses the YAML and runs the schema validator.
2. If validation fails and `retry_with_feedback` is true and retries remain, Architect sends the same request again with the validation errors appended (e.g. "Previous attempt failed: Draft must contain at least one of: models, actions, pages.").
3. This repeats up to `max_retries` times. If validation still fails, Architect falls back to the stub draft so you always get a valid file.

## Fallback when Prism is missing or disabled

If Prism is not available or AI is disabled:

- `architect:draft` returns a stub draft: `schema_version`, one model (name inferred from the first word of the description), and placeholder actions and pages. You can then edit the file manually or use a different tool to refine it.
