# Templates and starters

Architect ships with **starter drafts** you can load with `architect:starter`. You can also add custom starters.

## Bundled starters

| Starter | Description |
|---------|-------------|
| **blog** | Posts and comments, with author, slug, and seeder. |
| **saas** | Teams and projects (minimal multi-tenant style). |
| **api** | API keys model and actions (no pages). |

## Using a starter

**Write to draft path (default):**

```bash
php artisan architect:starter blog
```

This copies the `blog` starter to `config('architect.draft_path')` (default `draft.yaml`).

**Write to a specific file:**

```bash
php artisan architect:starter blog --output=my-draft.yaml
```

**Output to stdout (e.g. to pipe or inspect):**

```bash
php artisan architect:starter saas --stdout
```

Then run `architect:validate`, `architect:plan`, and `architect:build` as usual.

## Profiles (config)

You can define **profiles** in config: preset "stack + packages" combinations (e.g. `breeze-inertia-react-filament`) that set default stack and package hints. Choose a profile once or use "detect from app." Profiles are optional; when implemented, they are configured under `config('architect.profiles')`. For now, use `architect.stack` and `architect.packages` to match your app.

## Adding custom starters

1. Create a YAML file that follows the [Schema Reference](SCHEMA.md) (e.g. `my-app.yaml`).
2. Place it in a directory your app can read (e.g. `storage/architect/starters/` or a published path).
3. To use it via a command, you would need to extend Architect or run: `cp /path/to/my-app.yaml draft.yaml`.

Bundled starters live in the package under `resources/starters/`. To ship your own starters with a package or repo, publish or copy them into your project and run `architect:starter` with a path option if you add one, or copy the file manually.

## See also

- [Getting Started](getting-started.md) – "Start from a template" with `architect:starter blog`.
- [Schema Reference](SCHEMA.md) – Draft structure for custom starters.
