# Changelog

All notable changes to ContextWP will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
- `/wp-json/contextwp/v1/generate_context` endpoint for AI-powered content generation
- `/wp-json/mcp/v1/list_contexts` endpoint for listing available contexts
- `/wp-json/mcp/v1/get_context` endpoint for retrieving specific context data
- `/wp-json/mcp/v1/manifest` endpoint for MCP manifest information
- Admin settings page for AI provider configuration
- Caching system for AI responses (5-minute default TTL)
- Rate limiting on public endpoints
- Security features including API key protection and input validation 