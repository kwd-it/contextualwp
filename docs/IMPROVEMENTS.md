# ContextualWP Roadmap & Improvements

> **Note:** This is a living roadmap and feature tracker, not a changelog. For a history of changes, see the commit log or a future CHANGELOG.md.

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
  - Claude: Supported. Mistral: Not yet implemented.
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
- [ ] Use the right model for each request to reduce costs
  - Currently, all requests may use expensive models (e.g., GPT-4). Add logic to select cheaper models (e.g., GPT-3.5) where appropriate to reduce per-request cost.

## Suggestions / Backlog
- Add a provider class/interface for easier extension
- Allow per-endpoint or per-context settings overrides
- Add usage analytics (opt-in)
- Add a debug mode for verbose logging
- Add a UI for viewing recent AI requests/responses (admin only)
- Add support for streaming/partial AI responses (where provider supports) 