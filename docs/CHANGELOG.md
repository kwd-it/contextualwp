# Changelog

All notable changes to ContextualWP will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.11.1] – 2026-02-27

### Fixed
- **Multi-context content**: Multi-context is now built via the same rendered content path as single-context (`the_content`/format_content), so the AI receives real body copy. Items are formatted as `## {Label}: {Title} ({id})\n\n{body}` with `\n---\n` separators; per-item truncation defaults to 6000 chars (filter: `contextualwp_multi_context_item_max_chars`). Empty rendered content returns "No content found." Ordering is post_modified DESC, ID DESC.
- **OpenAI GPT-5.x**: GPT-5.2, gpt-5-mini, and gpt-5-nano now use the Responses API (`POST /v1/responses`) instead of chat/completions. `max_tokens` is mapped to `max_output_tokens` and clamped 256–4096. On empty or incomplete output the provider retries once with truncated context, then falls back to smaller model(s); reasoning tokens are never shown to the user; on total failure a generic user-safe message is returned (details in debug logs only).

## [0.11.0] – 2026-02-23

### Fixed
- **Admin Chat context on edit screens**: Floating chat and ACF AskAI now use the current post/page as `context_id` on wp-admin edit screens (including new posts as `post-0`). Multi is only used when explicitly selected or on non-edit screens.
- **Block-based page content in context**: Single-context `context.content` now includes rendered Gutenberg and ACF block output (via `the_content`), not just the title, so summaries use real page copy instead of generic filler.

### Changed
- **format_content**: Runs `post_content` through `the_content` before formatting; empty rendered content returns a concise “No content found.” note without falling back to multi.

## [0.10.2] – 2026-02-10

### Fixed
- AskAI popover layout improvements: tightened close button spacing and improved alignment with the triggering field.
- AskAI responses now correctly render markdown formatting (e.g. bold text).

## [0.10.1] – 2026-02-09

### Fixed
- AskAI popover now clamps to the viewport so it doesn't open off-screen or cause horizontal scrolling in wp-admin.

## [0.10.0] – 2026-02-08

### Changed
- **AskAI intent routing**: Prompts are now routed by intent (explain vs advise) for clearer guidance in the editor.
- **"What changes when…"**: Shown only when relevant to the user's question.
- **Field helper prompts**: Fixed schema routing errors when using AskAI on individual ACF fields.

## [0.9.0] – 2026-02-07

### Added
- **Editor-safe ACF schema endpoint** (`/wp-json/contextualwp/v1/acf_schema`): Returns ACF field metadata for AI and editor use, with conditional logic and controlled-fields summaries in plain terms. Excludes field keys, internal IDs, and file paths.

### Changed
- **AskAI icon coverage**: Expanded across more ACF field types for consistent inline AI assistance.
- **AskAI true/false fields**: Improved responses using ACF schema for better context and suggestions.

## [0.8.0] – 2026-01-31

### Added
- **Manifest schema section**: `/manifest` now exposes a `schema` section describing post types and taxonomies (metadata only, no content).
- **Schema relationships**: Support for declaring relationships via `schema.relationships`, with a documented filter (`contextualwp_manifest_schema_relationships`).
- **Field complexity signals**:
  - Global `schema.core_field_count` (number of wp_posts table columns).
  - Per-post-type `field_sources.acf_fields` (ACF field count when ACF is active).
- **Usage contract section** in `/manifest`, including:
  - Preferred entrypoint path.
  - Caching expectations.
  - Rate limiting guidance.
  - Explicit discouragement of HTML crawling in favour of MCP endpoints.
- **Admin-only `/site_diagnostics` endpoint** (`/wp-json/mcp/v1/site_diagnostics`), providing:
  - Site URLs and environment.
  - WordPress and PHP versions.
  - Active theme details (including parent theme when applicable).
  - Active plugins with versions.

## [0.7.0] – 2026-01-29

### Added
- **Markdown rendering for chat**: AI responses in the global chat widget now render as markdown (bold, italic, code blocks, lists, headings, blockquotes, links).
- **Maximize/restore toggle**: Chat window can be expanded to use most of the viewport and restored to default size.

### Changed
- **Larger chat window**: Default size increased to 520×560px for better readability.
- **Scrollable messages area**: Messages area scrolls correctly within the chat window; scroll no longer propagates to the page behind.

## [0.6.3] – 2026-01-28

### Added
- **Intent router for schema-based questions**: Structure answers now route by intent instead of one generic schema summary.
  - **ACF-by-post-type**: Queries like "ACF for plots", "List ACF assigned to plot cpt" return only field groups targeting that post type (param=post_type, value=slug); block groups excluded unless the user asks for blocks.
  - **Generic schema overview**: Queries like "What CPTs are on this site?" or "List post types and taxonomies" return CPTs + taxonomies (and optional ACF top-5 when ACF is mentioned).
  - **Unknown post type**: When the user asks for ACF by post type but the post type cannot be resolved, return a helpful message plus the list of available post types from the schema.
- Golden/fixture tests for the three intents (`tests/IntentRouterTest.php`); output is stable and deterministic.
- Single "Source: schema (generated at …)" footer per response (deduped).

### Changed
- Schema structure answers are built by intent-specific formatters; footer is appended once via `build_schema_footer()`.

## [0.6.2] – 2026-01-27

### Fixed
- Improve schema responses for "ACF for <post type>" queries by returning only field groups assigned to that post type and excluding blocks unless requested.

### Changed
- Remove IMPROVEMENTS.md; rely on DEVNOTES.md + CHANGELOG.md.

## [0.6.1] – 2026-01-27

### Changed
- **AI model catalog**: Updated built-in model mappings to current offerings:
  - OpenAI: gpt-4o-mini / 4o / 4.1 → gpt-5-nano, gpt-5-mini, gpt-5.2
  - Claude: claude-3-haiku / 3.5-sonnet / 3.5-opus → claude-haiku-4-5, claude-sonnet-4-5, claude-opus-4-5
  - Mistral: mistral-tiny / small / large → mistral-small-2506, mistral-medium-2508, mistral-large-2512 (Small/Medium/Large 3)

## [0.6.0] – 2026-01-26

### Added
- **Copy Context Pack**: Admin settings page now includes a "Copy Context Pack" section allowing admins to copy the site's ContextualWP schema JSON to clipboard for sharing with AI tools, debugging, audits, or external workflows.

## [0.5.0] – Schema Export Endpoint

### Added
- Admin-only schema export endpoint (`/contextualwp/v1/schema`) for site introspection.

## [0.4.0] - Provider Registry Unification

### Added
- **Provider Registry**: New `ContextualWP\Helpers\Providers` class as single source of truth for AI provider support
- **Mistral Support**: Mistral is now fully supported alongside OpenAI and Claude
- **Provider Normalization**: Centralized provider name normalization (UI labels ↔ internal slugs)
- **Manifest Providers**: Manifest endpoint now includes `providers` array listing all supported providers

### Changed
- **Unified Provider Lists**: All hard-coded provider lists replaced with `Providers::list()` throughout the plugin
- **Admin Settings**: Settings dropdown now uses centralized provider registry
- **Generate Context**: Provider mapping logic now uses `Providers::normalize()` for consistency
- **Smart Model Selector**: Added Mistral model mappings (mistral-tiny, mistral-small, mistral-large)
- **Cache Keys**: Cache keys now use normalized provider slugs for consistency

### Technical Details
- Provider registry is filterable via `contextualwp_ai_providers` filter
- Provider labels are filterable via `contextualwp_provider_labels` filter
- Maintains full backward compatibility with existing filters
- All provider names normalized to lowercase slugs internally
- UI labels mapped centrally in `Providers::get_labels()`

## [0.3.9] - Removed YAML Support from Manifest Endpoint

### Removed
- **YAML format support**: Removed YAML from the manifest endpoint parameters and enum list
- Removed unimplemented YAML error handling block
- Removed YAML-related validation branches and comments

### Changed
- Manifest endpoint now only supports JSON format
- Updated documentation to reflect JSON-only support
- Simplified manifest endpoint code by removing dead code paths

### Technical Details
- This is an internal cleanup removing an unimplemented feature
- The `format` parameter remains for future extensibility but now only accepts `json`
- No functional changes to existing JSON output behavior

## [0.3.8] - Aligned Post Type Validation

### Changed
- **Aligned post type validation**: Both `get_context` and `list_contexts` endpoints now use the same filterable list of allowed post types
- `get_context` endpoint now accepts all allowed post types (not just `post` and `page`), matching the behavior of `list_contexts`
- Both endpoints validate against the same `contextualwp_allowed_post_types` filter, ensuring consistency

### Added
- New `Utilities::get_allowed_post_types()` helper method that returns a filterable list of allowed post types (defaults to all public post types)
- New `contextualwp_allowed_post_types` filter hook for customizing allowed post types across both endpoints
- Enhanced validation in `list_contexts` endpoint to check against allowed post types list

### Technical Details
- Improved consistency between endpoints by using shared validation logic
- Better extensibility through centralized post type filtering
- Default behavior now supports all public post types instead of being limited to `post` and `page`

## [0.3.7] - Code Refactoring & Cleanup

### Changed
- **Refactored duplicate `format_content()` methods**: Removed duplicate `format_content()` methods from `Get_Context` and `Generate_Context` endpoint classes. Both now use the centralized `Utilities::format_content()` method, following DRY principles.
- **Refactored `is_public_content()` method**: Replaced private `is_public_content()` method in `Get_Context` class with `Utilities::is_public_post()` for better consistency and more comprehensive public post checking.

### Removed
- Removed debug `error_log()` statements from `Generate_Context` endpoint that were logging context_id and multi-context block entry.

### Fixed
- Updated hardcoded version fallback in `manifest.php` from `0.3.3` to current version.

### Technical Details
- Improved code maintainability by consolidating duplicate formatting logic
- Better consistency across endpoints using shared utility methods
- All endpoints now use centralized helper methods from `Utilities` class

## [0.3.6] - Documentation Updates

### Changed
- Updated README.md with current AI model names (gpt-4o-mini, gpt-4o, gpt-4.1, claude-3-haiku, claude-3.5-sonnet, claude-3.5-opus)
- Added missing changelog entries for versions 0.3.1 and 0.3.5

## [0.3.5] - Code Cleanup

### Removed
- Removed unused `get_model_info()` and `get_model_description()` methods from `Smart_Model_Selector` class
- Cleaned up dead code to improve maintainability

## [0.3.4] - Refactored Complexity Analysis

### Improved
- **Scoring-based complexity analysis**: Replaced keyword-based complexity detection with a comprehensive multi-factor scoring system
- Complexity now calculated using:
  - Word count (1 point per 10 words)
  - Conjunctions and connecting words (+1 each)
  - Analytical verbs (+2 each)
  - Sentence count (1 point per additional sentence beyond first)
  - Question marks (1 point per question mark beyond first)
  - WH-words at beginning (-1 each, weak simple indicator)
- More accurate complexity assessment leading to better model selection

### Added
- Filter hooks for extensibility: `contextualwp_complexity_wh_words`, `contextualwp_complexity_conjunctions`, `contextualwp_complexity_analytical_verbs`
- PHPUnit test suite for complexity analysis with comprehensive test coverage

### Technical Details
- Refactored `Smart_Model_Selector::analyze_complexity()` method with scoring algorithm
- Maintains backward compatibility (returns same values: "simple", "medium", "complex")
- Improved documentation with comprehensive PHPDoc and inline comments

## [0.3.3] - Improved Token Estimation

### Improved
- **Enhanced token estimation algorithm**: Replaced simple character-based estimation with a more sophisticated algorithm that:
  - Accounts for word boundaries and sub-word tokenization patterns
  - Handles punctuation and special characters more accurately
  - Distinguishes between letters, numbers, and symbols for better estimation
  - Strips HTML tags before estimation for text-only accuracy
  - Normalizes whitespace for consistent results
  - Includes fallback mechanism for edge cases
- Token estimation now uses word-based calculation (~0.75 tokens per word) with adjustments for punctuation, numbers, and special characters
- More accurate model selection due to improved token counting

### Technical Details
- Updated `Smart_Model_Selector::estimate_tokens()` method with multi-factor estimation
- Maintains backward compatibility with existing filter hooks
- Improved accuracy without requiring external tokenizer libraries

## [0.3.2] - Model Selection Cleanup & UI Sync

### Changed
- Removed Mistral references entirely.
- Admin model dropdown now dynamically reflects backend Smart Model Selector.
- JavaScript no longer contains hardcoded model lists.
- Model mappings now come from a single backend source of truth.
- Improved consistency between provider selection and available models.

## [0.3.1] - 2025-11-22

### Added
- Added missing `is_public_post()` helper function to `utilities.php`
- Helper function checks if a post is publicly accessible (published and not password-protected)
- Used by manifest endpoint to properly filter public contexts

## [0.3.0] - 2025-11-21

### Changed
- Renamed plugin from ContextWP to ContextualWP.
- Updated namespaces, constants, hooks, option keys, script handles, and REST route prefixes to use the `ContextualWP`/`contextualwp` naming.
- Updated documentation, Composer metadata, and version numbers to `0.3.0`.

## [0.2.0] - 2024-12-19

### Added
- **Smart Model Selector**: Automatically selects the most efficient AI model based on prompt length and complexity
- New helper class `Smart_Model_Selector` with intelligent model selection logic
- Admin toggle for enabling/disabling smart model selection (default: enabled)
- Support for GPT-3.5 Turbo, GPT-4, Claude Sonnet, and Claude Opus model variants
- Token-based model selection with complexity analysis
- Developer filter `contextualwp_smart_model_select` for custom model selection logic
- Additional filters for thresholds and model mapping customization

### Technical Features
- Smart model selection integrated into `/generate_context` endpoint
- Automatic model selection based on:
  - Short/simple prompts (< 200 tokens): GPT-3.5 Turbo
  - Medium prompts (200-1000 tokens): GPT-3.5 Turbo  
  - Long/complex prompts (1000+ tokens): GPT-4
  - Claude provider uses Claude Sonnet for nano/mini, Claude Opus for large
- Complexity analysis using keyword and pattern matching
- Extensible architecture for future premium features

## [0.1.0] - 2024-12-19

### Added
- Initial release
- WordPress plugin that exposes structured post and ACF field data via REST API
- Follows Model Context Protocol (MCP) for AI integration
- Supports OpenAI and Claude AI providers
- Includes global floating chat widget for admin area
- ACF AskAI field helper for content generation
- Multi-context aggregation feature (`context_id: "multi"`)
- Comprehensive REST API endpoints for context retrieval and AI generation
- Extensible architecture with filters and hooks for customization

### Technical Features
- `/wp-json/contextualwp/v1/generate_context` endpoint for AI-powered content generation
- `/wp-json/mcp/v1/list_contexts` endpoint for listing available contexts
- `/wp-json/mcp/v1/get_context` endpoint for retrieving specific context data
- `/wp-json/mcp/v1/manifest` endpoint for MCP manifest information
- Admin settings page for AI provider configuration
- Caching system for AI responses (5-minute default TTL)
- Rate limiting on public endpoints
- Security features including API key protection and input validation 