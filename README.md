# ContextualWP

ContextualWP is a WordPress plugin that exposes structured post and ACF field data via a REST API, following the Model Context Protocol (MCP). It enables AI models to retrieve contextual content and generate new content using AI providers like OpenAI and Claude.

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
  "model": "gpt-4",
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
- Go to **Settings > ContextualWP** in wp-admin.
- Configure:
  - **AI Provider**: OpenAI, Claude, or Mistral
  - **API Key**: Your provider's API key (never exposed in API)
  - **Model**: Automatically filtered based on selected provider
    - OpenAI: gpt-5-nano, gpt-5-mini, gpt-5.2
    - Claude: claude-haiku-4-5, claude-sonnet-4-5, claude-opus-4-5
    - Mistral: mistral-small-2506, mistral-medium-2508, mistral-large-2512
  - **Advanced Settings**: Max tokens (default: 1024) and temperature (default: 1.0)

## Extensibility

### Filters/Hooks
- `contextualwp_context_data`: Filter the context data before sending to AI
- `contextualwp_ai_provider`: Override or add new AI providers
- `contextualwp_ai_payload`: Modify the AI API payload before sending
- `contextualwp_ai_response`: Modify the AI response before returning
- `contextualwp_prompt_templates`: Customize prompt templates for the global chat
- `contextualwp_available_providers`: Add new AI providers to the settings dropdown
- `contextualwp_provider_models`: Add new models for existing or custom providers
- `contextualwp_allowed_post_types`: Filter the list of allowed post types for `list_contexts` and `get_context` endpoints (defaults to all public post types)
- `contextualwp_manifest_schema`: Filter the full schema object in the manifest response (post types and taxonomies metadata)
- `contextualwp_manifest_schema_post_types`: Filter the post types array in the manifest schema (includes `taxonomies` relationship)
- `contextualwp_manifest_schema_taxonomies`: Filter the taxonomies array in the manifest schema (includes `object_types` relationship)

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
- **Description:** Returns metadata about this context provider for AI agents (MCP manifest). Includes a `schema` section with public post types and taxonomies (metadata only, no content).
- **Parameters:**
  - `format` (string, optional): `json` (default) - JSON is the only supported format
- **Authentication:** Public, but rate-limited

### `/wp-json/contextualwp/v1/schema`
- **Method:** GET
- **Description:** Returns schema information about the site including plugin details, post types, taxonomies, and ACF field groups (if ACF is active).
- **Authentication:** Requires `manage_options` capability (admin-protected)
- **Caching:** Responses are cached for 5 minutes by default. Adjust TTL using the `contextualwp_schema_cache_ttl` filter.

#### Example Response
```json
{
  "plugin": {
    "name": "ContextualWP",
    "version": "0.7.0"
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

## Admin Features

### Global Floating Chat
- A floating chat icon appears in the WordPress admin area, allowing you to ask questions or generate content about the current screen or post.
- Click the icon to open a chat modal, enter your prompt, and receive AI-powered responses.
- Prompt templates are available for common tasks (e.g., summarization, SEO suggestions).
- Supports multi-context queries using the `multi` context ID.

### ACF AskAI (Field Helper)
- On post/page edit screens, each ACF field displays an "Ask AI" icon.
- Click the icon to ask the AI about the field's content, get suggestions, or improve writing.
- You can insert or replace post content with the AI's response directly from the tooltip.

## Supported AI Providers

### OpenAI
- Models: gpt-5-nano, gpt-5-mini, gpt-5.2
- Endpoint: https://api.openai.com/v1/chat/completions

### Claude (Anthropic)
- Models: claude-haiku-4-5, claude-sonnet-4-5, claude-opus-4-5
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
return `0` to disable caching.

## Contributing
- PRs and issues welcome!
- Follow WordPress coding standards

## Documentation

- [CHANGELOG.md](docs/CHANGELOG.md) - Complete list of changes and version history
- [DEVNOTES.md](docs/DEVNOTES.md) - Development notes and technical details

