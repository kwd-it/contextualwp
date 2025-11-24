# Changelog

All notable changes to ContextualWP will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.2] - Model Selection Cleanup & UI Sync

### Changed
- Removed Mistral references entirely.
- Admin model dropdown now dynamically reflects backend Smart Model Selector.
- JavaScript no longer contains hardcoded model lists.
- Model mappings now come from a single backend source of truth.
- Improved consistency between provider selection and available models.

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