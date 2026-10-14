# Codex review guidance

Repository-specific priorities for reviewing changes to ContextualWP. Universal review behaviour is defined outside this repository; ecosystem governance is in [kwd-it/contextual-stewardship](https://github.com/kwd-it/contextual-stewardship).

## Review priorities

1. **Public contract.** REST routes under `/mcp/v1/` and `/contextualwp/v1/`, response shapes, permission callbacks (`read`, `edit_posts`, `manage_options`, the public manifest), cache TTLs and documented filters. Any change must be reflected in `README.md` and classified as patch, minor or major per `docs/COMPATIBILITY.md`.
2. **Security.** Capability checks on every route; sanitisation of settings input; no leakage of user IDs, logins, emails or roles (see `Utilities::get_safe_modified_author_display_name()`); rate limiting on the public manifest; no secrets or site data in code, tests or docs.
3. **Sector pack contract.** `includes/SectorPacks/` registry behaviour and `docs/PACK-SPEC.md`. The Housebuilder Pack depends on them; flag any change that would alter registration, compatibility checks or helper signatures.
4. **AI provider handling.** Model ID catalogues, fallback lists and per-provider request and response parsing in `includes/helpers/smart-model-selector.php` and `includes/endpoints/generate-context.php`. A model change must not alter the default model or admin UX unless the task authorised it.
5. **Tests.** Behaviour changes come with PHPUnit coverage in `tests/` using the stub bootstrap, and the suite passes under the declared PHP minimum.

## Metadata consistency

The PHP minimum appears in `composer.json` and the `Requires PHP` header; the licence (GPL-3.0-only) appears in `LICENSE`, `composer.json`, the plugin header and `README.md`. Flag any change that leaves them disagreeing.

## Normally ignore

`vendor/`, `.phpunit.cache/`, and `composer.lock` beyond its content hash when `composer.json` metadata changes.

## Landing

A change is complete when it sits on a task branch with a pull request against `main`. Merging belongs to the product owner, except documentation and test-only maintenance merged by the steward under the stewardship policy D-016 through its `tooling/merge/autonomous-merge.mjs`; flag any pull request presented as eligible for that policy whose diff touches anything outside Markdown, `docs/` and `tests/`, weakens a test, or whose description lacks a `Reviewed head:` line matching the head commit. Version bumps, release headings in `docs/CHANGELOG.md` and tags belong to the product owner; flag any diff that performs them without explicit authorisation in the task.
