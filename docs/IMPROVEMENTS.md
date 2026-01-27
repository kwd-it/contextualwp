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
- [x] Support for additional AI providers (Mistral, Anthropic, etc.)
  - Claude: Supported. Mistral: Fully supported with UI and API integration.
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
  - Implemented via Smart Model Selector. Automatically selects optimal model (5-nano, 5-mini, 5.2 / Haiku/Sonnet/Opus 4.5 / Mistral Small/Medium/Large 3) based on prompt size and complexity.

## Suggestions / Backlog
- Add a provider class/interface for easier extension
- Allow per-endpoint or per-context settings overrides
- Add usage analytics (opt-in)
- Add a debug mode for verbose logging
- Add a UI for viewing recent AI requests/responses (admin only)
- Add support for streaming/partial AI responses (where provider supports)

## Recent Improvements

### Intent-Aware ACF Post Type Queries (v0.6.2)

**Problem:** When users asked for "ACF assigned to <post type>", the system would return a generic overview (CPTs/taxonomies/top 5 groups) and ask the user to specify a post type even when they already did.

**Solution:** Implemented intent-aware filtering that:
- Detects when users request ACF field groups for a specific post type
- Resolves post type slugs from various input patterns and synonyms (e.g., "plot", "plots", "plot cpt")
- Filters ACF field groups to only those targeting the requested post type
- Provides detailed field information (label, name, key, type) instead of just counts
- Skips generic overview sections when a post type is detected
- Handles unknown post types with helpful error messages listing available types
- Optionally includes block groups when explicitly requested

**Supported Query Patterns:**
- "List all acf assigned to plot cpt"
- "ACF for plots"
- "Show ACF field groups for plots"
- "acf assigned to post type plots"
- "ACF blocks for plots" (includes block groups)
- "ACF for plots and include blocks"

**Output Format:**
When a post type is detected, the response includes:
- Group title and key
- Field count
- Complete field list with: label, name, key, type
- Note about block exclusion (unless blocks are requested)

**Examples:**

Query: `"List all acf assigned to plot cpt"`

Response:
```
ACF Field Groups for "plots"

### Plot Details
Group Key: group_plot_details
Field Count: 5

Fields:
  - Plot Name (plot_name) [text] — Key: field_plot_name
  - Plot Size (plot_size) [number] — Key: field_plot_size
  - Location (location) [google_map] — Key: field_location
  - Description (description) [textarea] — Key: field_description
  - Images (images) [gallery] — Key: field_images

Blocks excluded unless requested.
```

**Error Handling:**
If an unknown post type is requested, the response lists all available post types:
```
Error: Post type "unknown_type" not found.

Available post types: plots, developments, pages, posts
```

**Technical Details:**
- Uses improved pattern matching with support for singular/plural variants
- Filters ACF location rules to find groups with `param == "post_type"` and `value == "<slug>"`
- Block groups are identified by `param == "block"` and optionally matched by post type slug in block name or title
- All filtering happens server-side without AI calls for deterministic, fast responses

## Design Decisions

### YAML Support Removal (v0.3.9)
YAML format support was removed from the manifest endpoint as it was never implemented and added unnecessary complexity. The endpoint now exclusively supports JSON format, which is the standard for REST API responses and aligns with WordPress REST API conventions. The `format` parameter remains in the API for potential future extensibility, but currently only accepts `json`. 