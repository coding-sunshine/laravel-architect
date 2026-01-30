# Laravel Architect Documentation

Documentation for the Laravel Architect package: YAML-driven scaffolding with AI-powered drafting.

## Documentation index

| Document | Description |
|----------|-------------|
| [Getting Started](getting-started.md) | Install, create your first draft, validate, plan, build, and status. |
| [Concepts](concepts.md) | Draft as source of truth, idempotent builds, state file, ownership, generators. |
| [Schema Reference](SCHEMA.md) | Full draft.yaml schema: models, columns, relationships, actions, pages, routes. |
| [Commands](commands.md) | Reference for all architect:* commands (draft, validate, plan, build, status, packages, explain, watch, fix, import, starter, why, check). |
| [Configuration](configuration.md) | All config keys: draft_path, stack, state_path, ai, orchestration, targets, ui, packages, ownership, conventions, hooks, history. |
| [Stacks](stacks.md) | Supported stacks (Inertia React, Inertia Vue, Livewire, Volt, Blade), detection, and override. |
| [Packages](packages.md) | Detected packages, registry, and how to extend. |
| [AI and Prism](ai-and-prism.md) | When AI is used, Prism setup, context minimisation, retries and fallback. |
| [AI Features](ai-features.md) | Advanced AI capabilities: assistant chat, smart suggestions, code generation, conflict detection. |
| [Studio API](studio-api.md) | API reference: draft-from-ai, simple-generate, and the four wizard endpoints with request/response shapes. |
| [Visual Designer](visual-designer.md) | Open the Studio, frontend stack (shadcn / Flux / Blade), command–UI parity. |
| [Templates](templates.md) | Starter drafts (blog, saas, api) and custom starters. |
| [Troubleshooting](troubleshooting.md) | Common errors and how to fix them. |
| [Publishing](PUBLISHING.md) | For maintainers: tagging and releasing the package. |

## Quick links

- **New to Architect?** Start with [Getting Started](getting-started.md), then read [Concepts](concepts.md) and [Schema Reference](SCHEMA.md).
- **Need a command reference?** See [Commands](commands.md).
- **Configuring the package?** See [Configuration](configuration.md).
- **Something broke?** See [Troubleshooting](troubleshooting.md).
