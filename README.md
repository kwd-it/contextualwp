# ContextualWP

ContextualWP is a WordPress plugin that exposes structured post and ACF field data via a REST API in an MCP-oriented pattern. It enables AI agents to retrieve contextual content and, where permitted, generate new content using providers such as OpenAI, Claude, and Mistral. **v1.0** established the first stable release line for production use. **v1.1** adds core support for optional sector pack plugins (runtime registration, compatibility checks, and a read-only admin list) without changing behaviour when no packs are active. **v1.2** adds a **schema interpretation layer** on `/contextualwp/v1/schema` (`interpretation.contextualwp`): AI-friendly summaries, relationship guidance (including **fallback edges inferred from ACF** when the manifest relationships filter is empty), and ACF 6.8 structured-data capability notes—**without** emitting Schema.org JSON-LD from ContextualWP. **v1.3.1** refines the admin “Ask AI” icon (neutral chat/context styling) and improves single-post `generate_context` for CPTs with sparse body copy by including ACF/meta summaries in provider context when present.

## Working alongside ACF 6.8

**ACF 6.8+** can emit **automatic Schema.org JSON-LD** on singular front-end views when the site enables it (see ACF’s `acf/settings/enable_schema` and per–post type settings). **ContextualWP does not output Schema.org JSON-LD** (not in REST responses, not mirrored from ACF). Instead it exposes an **interpretation layer** plus existing structured endpoints so agents get **AI-friendly summaries and field/relationship hints** without scraping HTML or depending on front-end JSON-LD.

- **`/wp-json/contextualwp/v1/schema`**: Raw-ish site structure (post types, taxonomies, ACF group metadata) plus an optional **`interpretation`** object. The default **`interpretation.contextualwp`** block (present when ACF is active, when `contextualwp_manifest_schema_relationships` returns edges, when **fallback relationship edges** are inferred from ACF `post_object` / `relationship` fields (and group location rules) because the manifest filter returned none, or when a sector pack is registered) gives **AI-friendly summaries**, explicit **relationship-field hints** (for example which fields link which post types), and **detection notes** for whether ACF 6.8’s JSON-LD stack *may* be in use on the site. **Raw JSON-LD graphs are intentionally omitted** from `interpretation`.
- **`/wp-json/contextualwp/v1/acf_schema`**: **Editor-safe** ACF field metadata (labels, types, conditional logic in plain language). This remains the right surface for “what does this field mean in the editor?”—orthogonal to Schema.org property mapping.
- **`/wp-json/mcp/v1/manifest` (`schema`)**: Public **discovery** metadata (post types, taxonomies, optional `relationships`). Same relationship filter as above keeps manifest and `/schema` interpretation aligned.

Extensions that use **`contextualwp_schema_interpretation`** should add **their own top-level keys** (for example a pack slug). Core merges those keys with the default `contextualwp` block; **avoid overwriting `contextualwp`** unless you intend to replace the built-in layer.

## Endpoints

### `/wp-json/contextualwp/v1/generate_context`
- **Method:** POST
- **Description:** Generate AI-powered content using a WordPress post/page as context.
- **Parameters:**
  - `context_id` (string, required): e.g., `post-123`, `page-2`, or `multi` for multiple recent posts
  - `prompt` (string, optional): The prompt/question for the AI
  - `format` (string, optional): `markdown` (default), `plain`, or `html`
- **Authentication:** Requires Application Passwords or cookie auth (user must have `edit_posts` capability)

#### Special Context IDs
- `multi`: Aggregates content from the 5 most recent posts and pages for broader context

#### Example Request (curl)
```sh
curl -X POST "https://your-site.test/wp-json/contextualwp/v1/generate_context" \
  -u username:application-password \
  -H "Content-Type: application/json" \
  -d '{
    "context_id": "post-123",
    "prompt": "Summarize this post.",
    "format": "markdown"
  }'
```

#### Example Response
```json
{
  "message": "AI response generated.",
  "provider": "OpenAI",
  "model": "gpt-5.5",
  "context_id": "post-123",
  "prompt": "Summarize this post.",
  "format": "markdown",
  "context": {
    "id": "post-123",
    "content": "...formatted post content...",
    "meta": { "title": "...", "acf": { ... } }
  },
  "ai": {
    "output": "...AI-generated content...",
    "raw": { ... }
  }
}
```

## Settings
- Go to the **ContextualWP** menu in wp-admin.
- Configure:
  - **AI Provider**: OpenAI, Claude, or Mistral
  - **API Key**: Your provider's API key (never exposed in API)
  - **Model**: Recommended models in the dropdown depend on provider; previously saved legacy IDs remain valid and may appear as “(legacy)”.
    - OpenAI (recommended): `gpt-5.5`, `gpt-5.4-mini`, `gpt-5.4-nano` (e.g. `gpt-5.2`, `gpt-5-mini`, `gpt-5-nano`, `gpt-5` still accepted if already saved)
    - Claude (recommended): `claude-opus-4-7`, `claude-sonnet-4-6`, `claude-haiku-4-5` (`claude-opus-4-5`, `claude-sonnet-4-5` still accepted if already saved)
    - Mistral: mistral-small-2506, mistral-medium-2508, mistral-large-2512
  - **Advanced Settings**: Max tokens (default: 1024) and temperature (default: 1.0)

## Extensibility

### Sector packs

ContextualWP supports **optional sector packs**: separate WordPress plugins that register with core at runtime via `contextualwp_register_sector_pack()` (or by implementing `ContextualWP\SectorPacks\Sector_Pack_Interface` and passing metadata into that function). Core behaviour is unchanged when no packs are installed. **ContextualWP > ContextualWP Packs** lists registered packs, compatibility with the running ContextualWP version, and any pack settings URL they provide. Packs are not installed or uploaded from that screen. See [docs/PACK-SPEC.md](docs/PACK-SPEC.md) for responsibilities and boundaries. Versioning rules for core releases are in [docs/COMPATIBILITY.md](docs/COMPATIBILITY.md).

### Filters/Hooks
- `contextualwp_sector_packs_init`: Action. Register sector packs here (call `contextualwp_register_sector_pack()`). Runs on `plugins_loaded` at priority 20. You may also call `contextualwp_register_sector_pack()` later in the same request once ContextualWP is loaded.
- `contextualwp_sector_pack_registered`: Action. Fires with `( string $slug, array $record )` after a successful registration.
- `contextualwp_registered_sector_packs`: Filter. Adjust the array of registered pack records (slug to metadata including `compatibility`) after compatibility is computed.
- `contextualwp_schema_interpretation`: Filter. Return optional **top-level** keys to **merge** into the `interpretation` object on `/contextualwp/v1/schema`. Core may add a default **`contextualwp`** key (AI summaries, relationship hints from manifest or ACF-derived fallbacks, ACF 6.8 structured-data capability notes—no JSON-LD). The `interpretation` key is omitted only when both the core layer and this filter contribute nothing.
- `contextualwp_sector_pack_admin_links`: Filter. Append extra `{ label, url }` items on the ContextualWP Packs admin screen (optional; pack `settings_url` is shown in the table without this).
- `contextualwp_sector_packs_admin_page_after_table`: Action. Fires on the ContextualWP Packs admin screen after the table (and optional additional links).
- `contextualwp_context_data`: Filter the context data before sending to AI
- `contextualwp_ai_provider`: Override or add new AI providers
- `contextualwp_ai_payload`: Modify the AI API payload before sending
- `contextualwp_ai_response`: Modify the AI response before returning
- `contextualwp_prompt_templates`: Customize prompt templates for the global chat
- `contextualwp_available_providers`: Add new AI providers to the settings dropdown
- `contextualwp_provider_models`: Add new models for existing or custom providers
- `contextualwp_allowed_post_types`: Filter the list of allowed post types for `list_contexts` and `get_context` endpoints (defaults to all public post types)
- `contextualwp_manifest_schema`: Filter the full schema object in the manifest response (post types and taxonomies metadata)
- `contextualwp_manifest_schema_post_types`: Filter the post types array in the manifest schema (includes `taxonomies` and optional `field_sources.acf_fields`)
- `contextualwp_manifest_schema_taxonomies`: Filter the taxonomies array in the manifest schema (includes `object_types` relationship)
- `contextualwp_manifest_schema_relationships`: Populate `schema.relationships` in the manifest (empty by default)

### Adding a New AI Provider
1. Use the `contextualwp_ai_provider` filter to return your provider slug (e.g., 'anthropic')
2. Use the `contextualwp_ai_payload` filter to build the payload for your provider
3. Use the `contextualwp_ai_response` filter to handle the response

#### Example (in a custom plugin):
```php
// Add a new provider to the settings dropdown
add_filter('contextualwp_available_providers', function($providers) {
    $providers['CustomAI'] = 'Custom AI Provider';
    return $providers;
});

// Add models for the new provider
add_filter('contextualwp_provider_models', function($models, $provider) {
    if ($provider === 'CustomAI') {
        $models['CustomAI'] = ['custom-model-1', 'custom-model-2'];
    }
    return $models;
}, 10, 2);

// Handle the custom provider logic
add_filter('contextualwp_ai_provider', function($provider, $settings, $context, $request) {
    if ($settings['ai_provider'] === 'CustomAI') return 'customai';
    return $provider;
}, 10, 4);

add_filter('contextualwp_ai_payload', function($payload, $settings, $context, $request) {
    if ($settings['ai_provider'] === 'CustomAI') {
        // Build custom AI payload here
    }
    return $payload;
}, 10, 4);

add_filter('contextualwp_ai_response', function($response, $provider, $settings, $context, $request) {
    if ($provider === 'customai') {
        // Parse and return custom AI response here
    }
    return $response;
}, 10, 5);
```

## Additional Endpoints

### `/wp-json/mcp/v1/list_contexts`
- **Method:** GET
- **Description:** List available contexts (posts/pages) with pagination and search.
- **Parameters:**
  - `post_type` (string, optional): Any allowed post type (default: `post`). Both endpoints use the same filterable list of allowed post types via the `contextualwp_allowed_post_types` filter (defaults to all public post types).
  - `limit` (int, optional): Number of items per page (default: 10, max: 100)
  - `page` (int, optional): Page number (default: 1)
  - `search` (string, optional): Search term for post titles/content
- **Authentication:** Requires user with `read` capability

### `/wp-json/mcp/v1/get_context`
- **Method:** GET
- **Description:** Retrieve the content and meta for a specific context (post/page).
- **Parameters:**
  - `id` (string, required): e.g., `post-123`, `page-2`, or any allowed post type (e.g., `product-456`, `event-789`). Both endpoints use the same filterable list of allowed post types via the `contextualwp_allowed_post_types` filter (defaults to all public post types).
  - `format` (string, optional): `markdown` (default), `plain`, or `html`
- **Authentication:** Public for published content, otherwise requires login

### `/wp-json/mcp/v1/manifest`
- **Method:** GET
- **Description:** Returns metadata about this context provider for AI agents (MCP manifest). Includes a `schema` section with public post types and taxonomies (metadata only, no content), and a `usage_contract` section (preferred entrypoint, caching expectations, rate limiting guidance, discouragement of HTML crawling).
- **Parameters:**
  - `format` (string, optional): `json` (default) - JSON is the only supported format
- **Authentication:** Public, but rate-limited

#### Schema structure
`schema` includes:
- `core_field_count` (int): Number of wp_posts table columns (same for all post types).
- `post_types`: Each post type may include `field_sources.acf_fields` (int) when ACF is active—count of ACF fields assigned to that post type.
- `taxonomies`, `relationships`: See filters below.

#### Schema relationships
`schema.relationships` is empty by default and can be populated via the `contextualwp_manifest_schema_relationships` filter. Each relationship should include `source_type`, `target_type`, and `description`.

```php
add_filter( 'contextualwp_manifest_schema_relationships', function ( $relationships ) {
    $relationships[] = [
        'source_type' => 'book',
        'target_type' => 'author',
        'description' => 'Books reference their author.',
    ];
    $relationships[] = [
        'source_type' => 'event',
        'target_type' => 'venue',
        'description' => 'Events are held at a venue.',
    ];
    return $relationships;
} );
```

### `/wp-json/mcp/v1/site_diagnostics`
- **Method:** GET
- **Description:** Admin-only endpoint returning a structured snapshot: site URLs and environment, WordPress and PHP versions, active theme details (including parent when applicable), and active plugins with versions.
- **Authentication:** Requires `manage_options` capability

### `/wp-json/contextualwp/v1/schema`
- **Method:** GET
- **Description:** Returns schema information about the site including plugin details, post types, taxonomies, and ACF field groups (if ACF is active). When applicable, an **`interpretation`** object adds the **interpretation layer**: a core **`interpretation.contextualwp`** block with **AI-friendly summaries**, **relationship guidance** (manifest edges from `contextualwp_manifest_schema_relationships`, or **fallback edges derived from ACF** post_object/relationship fields when that filter returns none), and **ACF 6.8+ structured data capability** notes (whether ACF’s automatic JSON-LD feature may apply—**no JSON-LD payload** is included). ContextualWP does **not** output Schema.org JSON-LD; use the front end or ACF’s own behaviour when you need live JSON-LD.
- **Authentication:** Requires `manage_options` capability (admin-protected)
- **Caching:** Responses are cached for 5 minutes by default. Adjust TTL using the `contextualwp_schema_cache_ttl` filter.

#### Example Response
The `plugin.version` field matches the **Version** value in the main plugin file header.

```json
{
  "plugin": {
    "name": "ContextualWP",
    "version": "1.x.y"
  },
  "site": {
    "home_url": "https://example.com",
    "wp_version": "6.4.2"
  },
  "post_types": [
    {
      "slug": "post",
      "label": "Posts",
      "supports": ["title", "editor", "thumbnail"],
      "taxonomies": ["category", "post_tag"]
    },
    {
      "slug": "page",
      "label": "Pages",
      "supports": ["title", "editor", "thumbnail"],
      "taxonomies": []
    }
  ],
  "taxonomies": [
    {
      "slug": "category",
      "label": "Categories",
      "object_types": ["post"]
    },
    {
      "slug": "post_tag",
      "label": "Tags",
      "object_types": ["post"]
    }
  ],
  "acf_field_groups": [
    {
      "title": "Page Settings",
      "key": "group_123abc",
      "location": [[{
        "param": "post_type",
        "operator": "==",
        "value": "page"
      }]],
      "fields": [
        {
          "label": "Hero Image",
          "name": "hero_image",
          "key": "field_456def",
          "type": "image"
        },
        {
          "label": "Related Posts",
          "name": "related_posts",
          "key": "field_789ghi",
          "type": "relationship",
          "post_type": ["post"]
        }
      ]
    }
  ],
  "generated_at": "2024-01-15T10:30:00+00:00"
}
```

### `/wp-json/contextualwp/v1/acf_schema`
- **Method:** GET
- **Description:** Returns editor-safe ACF field metadata derived from ACF's loaded field definitions (local JSON + DB). Structured for AI/editor use; excludes field keys, internal IDs, file paths, and raw JSON. Includes conditional logic and controlled-fields summaries in plain terms. **Not** a copy of ACF 6.8’s automatic Schema.org JSON-LD (which is front-end markup when enabled).
- **Authentication:** Requires `edit_posts` capability
- **Caching:** Responses are cached for 5 minutes by default. Adjust TTL using the `contextualwp_acf_schema_cache_ttl` filter.

#### Example Response
```json
{
  "field_groups": [
    {
      "title": "Page Settings",
      "location_summary": "page",
      "fields": [
        {
          "label": "Hero Image",
          "name": "hero_image",
          "type": "image",
          "instructions": "Upload a featured image for the page header.",
          "required": false,
          "default": null
        },
        {
          "label": "Show extra content",
          "name": "show_extra",
          "type": "true_false",
          "instructions": null,
          "required": false,
          "default": null,
          "conditional_logic_summary": null,
          "controlled_fields_summary": "Extra content: shown when ON"
        }
      ]
    }
  ],
  "generated_at": "2024-01-15T10:30:00+00:00"
}
```

## Admin Features

### Global Floating Chat
- A floating chat icon appears in the WordPress admin area, allowing you to ask questions or generate content about the current screen or post.
- On post and page edit screens, the chat uses the current item as context when appropriate; `multi` is available for broader site context.
- Click the icon to open a chat modal, enter your prompt, and receive AI-powered responses.
- Prompt templates are available for common tasks (e.g., summarization, SEO suggestions).
- Supports multi-context queries using the `multi` context ID.

### ACF AskAI (Field Helper)
- On post/page edit screens, each ACF field displays an "Ask AI" icon.
- Click the icon to ask the AI about the field's content, get suggestions, or improve writing.
- You can insert or replace post content with the AI's response directly from the tooltip.

## Supported AI Providers

### OpenAI
- Models (recommended in settings): `gpt-5.5`, `gpt-5.4-mini`, `gpt-5.4-nano`. Smart selection uses `gpt-5.4-nano` / `gpt-5.4-mini` / `gpt-5.5` for nano/mini/large. Older GPT‑5.x IDs remain valid if already saved.
- API: default OpenAI models use the Responses API (`POST https://api.openai.com/v1/responses`). Other model IDs use Chat Completions (`POST https://api.openai.com/v1/chat/completions`) when not listed for Responses (see `contextualwp_openai_responses_api_models`).

### Claude (Anthropic)
- Models (recommended in settings): `claude-opus-4-7`, `claude-sonnet-4-6`, `claude-haiku-4-5`. Smart selection uses the same three tiers. Older Claude 4.5 IDs remain valid if already saved.
- Endpoint: https://api.anthropic.com/v1/messages

### Mistral
- Models: mistral-small-2506 (Small 3.2), mistral-medium-2508 (Medium 3.1), mistral-large-2512 (Large 3)
- Endpoint: https://api.mistral.ai/v1/chat/completions

### Custom Providers
- Support for custom AI providers through the extensibility hooks
- Configure provider name and model in settings

## Security & Best Practices
- API keys are never exposed in REST responses or logs
- Only authenticated users with `edit_posts` can use `/generate_context`
- All inputs are validated and sanitized
- Rate limiting on public endpoints

## Caching
`/generate_context` responses are cached for a short period (default 5 minutes)
to reduce API calls. Adjust the TTL using the `contextualwp_ai_cache_ttl` filter or
return `0` to disable caching. The `/schema` and `/acf_schema` endpoints are also
cached (default 5 minutes); use `contextualwp_schema_cache_ttl` and
`contextualwp_acf_schema_cache_ttl` to adjust.

## Contributing
- PRs and issues welcome!
- Follow WordPress coding standards

## Documentation

- [CHANGELOG.md](docs/CHANGELOG.md) - Complete list of changes and version history
- [DEVNOTES.md](docs/DEVNOTES.md) - Development notes and technical details

