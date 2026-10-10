# Repository instructions

ContextualWP is a WordPress plugin: a structured context layer that exposes MCP-compatible REST endpoints (`/wp-json/mcp/v1/*`, `/wp-json/contextualwp/v1/*`), schema and ACF metadata, a sector pack registry, and optional AI-assisted admin features. Ecosystem-wide governance (authority model, cross-project rules, AI provider currency) lives in [kwd-it/contextual-stewardship](https://github.com/kwd-it/contextual-stewardship). This file covers only what is local to this repository.

## Architecture and boundaries

- `contextwp.php` is the entry point. Its `Version` header is the single source of the version; `CONTEXTUALWP_VERSION` is derived from it at runtime.
- Code lives in `includes/` (procedural helpers and endpoints, plus PSR-4 `ContextualWP\` classes such as `includes/SectorPacks/`); admin UI in `admin/`.
- `README.md` is the authoritative user-facing contract (endpoints, authentication, caching, hooks). A change that alters any of those updates `README.md` in the same change and is classified per `docs/COMPATIBILITY.md`.
- Core stays sector-agnostic. Sector packs integrate only through `contextualwp_register_sector_pack()` and documented hooks (`docs/PACK-SPEC.md`). The Housebuilder Pack (kwd-it/contextualwp-housebuilder-pack) declares a minimum core version and relies on the registry, the endpoint shapes and `Utilities::get_safe_modified_author_display_name()`; check it before changing any of them.
- AI provider and model handling is in `includes/helpers/smart-model-selector.php` and `includes/endpoints/generate-context.php`. Model changes follow the stewardship AI provider currency policy; the WordPress AI Client migration in `docs/AI-MIGRATION-PLAN.md` is planned, not started, and needs approval.

## Platform

- PHP 8.3 or later, declared in `composer.json` and the `Requires PHP` plugin header. Keep both in agreement.
- No WordPress minimum is declared. Do not add one without a recorded decision.

## Conventions

- Branch prefixes `docs/`, `feat/`, `fix/`, `refactor/`, `test/`, `chore/`; one intent per branch; Conventional Commits.
- `docs/CHANGELOG.md` follows Keep a Changelog; new entries go under `[Unreleased]`.
- `docs/DEVNOTES.md` describes the product owner's full development and release process, including the version bump, merge and tag steps that are reserved below. `docs/QA.md` is the manual regression baseline.

## Validation

- `composer install`, then `vendor/bin/phpunit` (PHPUnit 10, stub bootstrap in `tests/bootstrap.php`; no WordPress install is needed). Run `composer validate` after any `composer.json` change.
- There is no CI. The local test run is the gate.

## Git and release boundaries

- Default branch `main`; no staging branch. This repository is public: never commit API keys, site-specific data or private operational details.
- Landing rule for Claude and Codex sessions: work on a dedicated task branch; implement and validate; run the final Codex review of the actual diff; resolve material findings; push the branch and open a pull request against `main`.
- Do not merge the pull request. Do not bump the version, tag or publish a release unless separately authorised. Releases are `chore(release): prepare vX.Y.Z` commits merged and tagged on `main` by the product owner.
