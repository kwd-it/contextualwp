# ContextualWP Roadmap & Improvements

> **Note:** This is a living roadmap and feature tracker, not a changelog. For a history of changes, see [CHANGELOG.md](CHANGELOG.md).

## Roadmap (Status & Notes)
- [x] Admin settings screen (AI provider, API key, model, etc.)
  - Implemented in `admin/settings.php`.
- [x] Secure, authenticated REST endpoints
  - All endpoints use permission callbacks.
- [x] Fetch and format post/ACF data for context
  - Implemented in endpoints.
- [x] OpenAI integration for /generate_context
  - Implemented.
- [x] Extensibility via filters/hooks
  - Many filters/hooks present for customization.
- [x] Caching of AI responses (configurable TTL)
  - Implemented in endpoints with `wp_cache_set`/`wp_cache_get`.
- [~] Support for additional AI providers (Mistral, Anthropic, etc.)
  - Claude: Supported. Mistral: Scaffolded in code (commented out), ready for UI/API implementation.
- [~] Rate limiting and abuse prevention
  - Implemented for /manifest and via helper, but not global for all endpoints.
- [ ] Advanced error handling and logging (admin viewable)
  - Only `error_log` used; no admin UI for logs.
- [~] Admin UI enhancements (field validation, provider dropdown, help tooltips)
  - Some present, but not all (e.g., help tooltips missing).
- [ ] Automated tests (unit/integration)
  - No tests present.
- [ ] Documentation improvements (examples, troubleshooting)
  - Check README.md for completeness.
- [x] Use the right model for each request to reduce costs
  - Implemented via Smart Model Selector. Automatically selects optimal model (4o-mini, 4o, 4.1, Haiku, Sonnet, Opus) based on prompt size and complexity.

## Suggestions / Backlog
- Add a provider class/interface for easier extension
- Allow per-endpoint or per-context settings overrides
- Add usage analytics (opt-in)
- Add a debug mode for verbose logging
- Add a UI for viewing recent AI requests/responses (admin only)
- Add support for streaming/partial AI responses (where provider supports) 