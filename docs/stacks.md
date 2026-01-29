# Stacks

Architect detects your application's frontend stack (Breeze-style variants) and generates pages and controllers in the correct format. You can override detection with configuration.

## Supported stacks

| Stack | Description |
|-------|-------------|
| **inertia-react** | Inertia.js with React; pages are `.tsx` in `resources/js/pages/`. |
| **inertia-vue** | Inertia.js with Vue 3; pages are `.vue` in `resources/js/pages/`. |
| **livewire** | Livewire; full-page components (PHP class + Blade view) in `app/Livewire/` and `resources/views/livewire/`. |
| **volt** | Livewire Volt; single-file Blade with Volt attributes in `resources/views/livewire/`. |
| **blade** | Plain Blade views in `resources/views/pages/`; controllers return `view()`. |

## Detection order

When `architect.stack` is set to `auto` (the default), Architect detects the stack in this order:

1. **Volt** – if `livewire/volt` is in Composer dependencies.
2. **Livewire** – if `livewire/livewire` is in Composer dependencies.
3. **Inertia Vue** – if `@inertiajs/vue3` (or Inertia Laravel) is present and `resources/js/pages/**/*.vue` exist.
4. **Inertia React** – if Inertia Laravel (or `@inertiajs/react`) is present and `resources/js/pages/**/*.tsx` or `*.jsx` exist.
5. **Blade** – fallback when none of the above are detected.

Detection runs at boot (when running Artisan commands or the app). The resolved stack is stored in `config('architect.stack')` for the request.

## Overriding the stack

Set the stack explicitly so Architect does not auto-detect:

**Environment**

```env
ARCHITECT_STACK=inertia-react
```

**Config** (`config/architect.php`)

```php
'stack' => env('ARCHITECT_STACK', 'inertia-react'),
```

Allowed values: `auto`, `inertia-react`, `inertia-vue`, `livewire`, `volt`, `blade`.

## Stack → generated artifacts

| Stack | Page output | Controller style |
|-------|-------------|-----------------|
| **inertia-react** | `resources/js/pages/{slug}/{index,create,show,edit}.tsx` | `Inertia::render('{slug}/view')` |
| **inertia-vue** | `resources/js/pages/{slug}/{index,create,show,edit}.vue` | `Inertia::render('{slug}/view')` |
| **livewire** | `app/Livewire/{SlugView}.php` + `resources/views/livewire/{slug}-{view}.blade.php` | `return view('livewire.{slug}-{view}')` |
| **volt** | `resources/views/livewire/{slug}-{view}.blade.php` (Volt single-file) | `return view('livewire.{slug}-{view}')` |
| **blade** | `resources/views/pages/{slug}/{view}.blade.php` | `return view('pages.{slug}.{view}')` |

Other generators (models, migrations, actions, requests, routes, tests) are stack-agnostic. Only the **Page** and **Controller** generators change their output per stack.

## Package requirements

- **Inertia React**: `inertiajs/inertia-laravel` and React; pages under `resources/js/pages` with `.tsx`/`.jsx`.
- **Inertia Vue**: `inertiajs/inertia-laravel` and `@inertiajs/vue3`; pages under `resources/js/pages` with `.vue`.
- **Livewire**: `livewire/livewire`.
- **Volt**: `livewire/volt` (typically with Livewire).

If you force a stack (e.g. `ARCHITECT_STACK=inertia-vue`) in an app that doesn’t have that stack installed, generation will still run; ensure the app has the right packages and structure to use the generated files.

## See also

- [Configuration](configuration.md) – `stack` and other options.
- [Concepts](concepts.md) – How generators and the draft relate to the stack.
