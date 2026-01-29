# Troubleshooting

Common issues and how to resolve them.

## Draft file not found

**Symptom:** `architect:validate`, `architect:plan`, or `architect:build` reports "Draft file not found".

**Cause:** The draft path does not exist or is wrong.

**Fix:**

- Create a `draft.yaml` at the project root, or
- Set the path in `config/architect.php` under `draft_path` and create the file there, or
- Pass the path explicitly: `php artisan architect:validate /path/to/draft.yaml`.

---

## Validation failed

**Symptom:** `architect:validate` fails with "Draft validation failed" or a YAML parse error.

**Cause:** The draft is invalid YAML or does not satisfy the schema (e.g. missing models/actions/pages, wrong structure).

**Fix:**

- Check the error message for the first failing rule.
- Ensure at least one of `models`, `actions`, or `pages` is present and is an object.
- Ensure model keys are singular StudlyCase (e.g. `Post`, not `posts`).
- Validate YAML syntax (indentation, colons, quotes). Use an online YAML validator if needed.
- See [Schema Reference](SCHEMA.md) for the full schema.

---

## Build failed

**Symptom:** `architect:build` exits with an error (e.g. generator threw an exception).

**Cause:** A generator could not write a file (e.g. permission, missing directory) or the draft structure is invalid for that generator.

**Fix:**

- Run `architect:validate` first and fix any validation errors.
- Try `architect:fix` or `architect:fix --ai` when validation fails (AI-suggested fix is planned).
- Ensure `app/Models`, `database/migrations`, `app/Actions`, etc. exist or are writable.
- Check the error message for the generator name and the exception. Fix the draft or the filesystem and try again.
- If you use `--only`, run without it to see if a specific generator is failing.

---

## File already exists / overwrite

**Symptom:** You edited a generated file (e.g. a controller) and the next build overwrote it, or you expect it to be overwritten but it is not.

**Cause:** Ownership. Files under `scaffold_only` are not overwritten unless you pass `--force`. Files under `regenerate` are overwritten every time.

**Fix:**

- To avoid overwriting your customizations: leave the file as-is; it is tracked as scaffold_only. Or move your logic elsewhere (e.g. an Action) and let the controller be regenerated.
- To force overwrite: run `php artisan architect:build --force`. Use with care.
- To regenerate only one kind of file: `php artisan architect:build --only=migration` (or the generator name).

---

## No changes detected

**Symptom:** You changed `draft.yaml` but `architect:build` says "No changes" or does not update files.

**Cause:** Change detection uses a hash of the draft file. If the path or state is wrong, or the state file was reverted, Architect may think nothing changed.

**Fix:**

- Run `php artisan architect:build --force` to force a full build.
- Check that you are editing the same file as `config('architect.draft_path')`.
- Check `.architect-state.json` (or `config('architect.state_path')`): ensure it is not from another branch or machine that had a different draft.

---

## Where state and draft live

- **Draft:** By default `draft.yaml` at the project root. Override with `config('architect.draft_path')`.
- **State:** By default `.architect-state.json` at the project root. Override with `config('architect.state_path')`. This file is created/updated by `architect:build`. Do not edit it by hand unless you know what you are doing.

---

## architect:status shows nothing

**Symptom:** `architect:status` reports "No generated files tracked yet".

**Cause:** You have not run `architect:build` successfully yet, or the state file was deleted.

**Fix:** Run `php artisan architect:build` first. After a successful build, status will list the generated files.

---

## Why was this file generated?

**Symptom:** You want to know which generator or draft section produced a given file.

**Fix:** Run `php artisan architect:why {path}` (e.g. `architect:why app/Models/Post.php`). The command reports which generator produced the file. The file must be in Architect state (see `architect:status`).

---

## What will this draft do?

**Symptom:** You want a short summary of the draft (models, actions, pages) without running a full plan or build.

**Fix:** Run `php artisan architect:explain` (or `architect:explain draft.yaml --json` for JSON). This outputs a summary of models, actions, and pages that would be generated. Use `architect:plan` for a dry run with counts.

---

## Import limitations

**Symptom:** `architect:import` produces a draft that is incomplete or wrong.

**Cause:** Import infers a minimal draft from `app/Models`, `app/Actions`, and page directories. It does not infer relationships, validation rules, or non-standard structure.

**Fix:** Run `architect:import --output=draft.yaml` then edit the generated draft to add relationships, validation, and seeder config. Or create a draft from scratch with `architect:draft "description"` (when Prism is installed).

---

## Getting help

If you hit an issue not covered here:

- Check the [Schema Reference](SCHEMA.md) and [Commands](commands.md).
- Open an issue on the [GitHub repository](https://github.com/coding-sunshine/laravel-architect) with your draft snippet (redact secrets), the command you ran, and the full error message.
