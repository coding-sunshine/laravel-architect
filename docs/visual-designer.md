# Visual Schema Designer (Architect Studio)

The Architect Studio is a web UI for working with your draft and running Architect commands. This page describes how to open it, how the frontend stack is chosen, and how it maps to CLI commands.

## Opening the Studio

After installing Architect, open the Studio in your browser:

```
/architect
```

By default the route prefix is `architect`. You can change it in config:

```php
// config/architect.php
'ui' => [
    'driver' => env('ARCHITECT_UI_DRIVER', 'auto'),
    'route_prefix' => env('ARCHITECT_UI_ROUTE_PREFIX', 'architect'),
],
```

So if you set `ARCHITECT_UI_ROUTE_PREFIX=studio`, the URL becomes `/studio`.

## Frontend stack (UI driver)

The Studio view is chosen by **UI driver** detection. The package detects your app’s frontend stack and serves the matching Studio experience when `architect.ui.driver` is `auto`.

**Detection order:**

1. **Inertia React + shadcn** – If the app has Inertia Laravel and React, and shadcn UI (`components.json` or `resources/js/components/ui` with Button, etc.), the Studio uses an Inertia page with React + shadcn components.
2. **Livewire + Flux Pro** – If Livewire and Flux are present and a Flux Pro license key is set (`FLUX_PRO_LICENSE_KEY` or config), the Studio uses Livewire + Flux Pro components.
3. **Livewire + Flux** – If Livewire and Flux (or Flux UI) are present, the Studio uses Livewire + Flux components.
4. **Blade** – Fallback: a minimal Blade view with Tailwind or bundled CSS.

When no Inertia+shadcn or Livewire+Flux stack is available, the Studio is shown as a simple Blade page that lists CLI commands and links to the documentation.

## Overriding the UI driver

Set the driver explicitly so the package does not auto-detect:

**Environment**

```env
ARCHITECT_UI_DRIVER=blade
```

**Config** (`config/architect.php`)

```php
'ui' => [
    'driver' => 'blade',
    'route_prefix' => 'architect',
],
```

Allowed values: `auto`, `inertia-react`, `livewire-flux`, `livewire-flux-pro`, `blade`.

## Command–UI parity

Every CLI command has (or will have) an equivalent action in the Studio:

| Command / capability           | UI equivalent                                      |
|--------------------------------|----------------------------------------------------|
| `architect:draft` (NL → YAML)  | "Generate from description" + AI; result in editor |
| `architect:validate`           | Live validation panel; errors inline               |
| `architect:plan`               | "What will be generated" panel or modal            |
| `architect:build`              | "Save and build" / "Build" button                  |
| `architect:status`             | "Generated files" / "State" panel                   |
| `architect:packages`           | "Detected packages" panel                          |
| `architect:import`             | "Import from codebase" button                      |
| `architect:explain` (planned)  | "Explain this draft" button                        |
| `architect:fix` (planned)     | "Fix with AI" when errors exist                    |
| `architect:starter` (planned)  | "Templates" dropdown or page                      |

Full parity is implemented in the Inertia/shadcn and Livewire/Flux Studio views when those drivers are available. The Blade fallback currently shows a command list and links to the CLI and documentation.

## See also

- [Configuration](configuration.md) – `architect.ui` (driver, route_prefix).
- [Commands](commands.md) – CLI reference.
- [Getting Started](getting-started.md) – First-run flow.
