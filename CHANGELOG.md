# Changelog

All notable changes to `laravel-architect` will be documented in this file.

## [1.0.0] - 2025-01-29

### Added

- YAML draft schema and parser with validation
- State manager for idempotent builds and migration path tracking
- Generators: Model, Migration, Factory, Seeder, Action, Controller, Request, Route, Page, TypeScript, Test
- MigrationGenerator: one migration per table, overwrite on rebuild (fingerprinting)
- Commands: `architect:draft`, `architect:validate`, `architect:plan`, `architect:build`, `architect:status`, `architect:import` (stub)
- AI draft generation via Prism (optional) with config-driven retries and validation feedback
- Config: draft_path, state_path, ai (enabled, max_retries, retry_with_feedback), ownership, conventions, hooks
- Schema reference (docs/SCHEMA.md) and publishing guide (docs/PUBLISHING.md)
- Full unit and feature test suite (43 tests)
