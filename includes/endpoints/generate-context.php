<?php
namespace ContextualWP\Endpoints;

use ContextualWP\Helpers\Utilities;
use ContextualWP\Helpers\Providers;
use ContextualWP\Helpers\Smart_Model_Selector;
use ContextualWP\Helpers\ACF_Schema_Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Generate_Context {

    /**
     * Default max characters per item in multi-context aggregated content.
     * Filterable via contextualwp_multi_context_item_max_chars.
     */
    const MULTI_CONTEXT_ITEM_MAX_CHARS = 6000;

    /**
     * OpenAI models that use the Responses API (not /v1/chat/completions).
     * Filterable via contextualwp_openai_responses_api_models.
     *
     * @var string[]
     */
    const OPENAI_RESPONSES_API_MODELS = [ 'gpt-5.2', 'gpt-5-mini', 'gpt-5-nano', 'gpt-5' ];

    /** Min/max clamp for max_output_tokens (Responses API) and safe bounds for legacy. */
    const OPENAI_MAX_OUTPUT_TOKENS_MIN = 256;
    const OPENAI_MAX_OUTPUT_TOKENS_MAX = 4096;

    /**
     * Fallback OpenAI models when primary returns no visible output (reasoning exhausted etc).
     * Order: prefer gpt-5-mini then gpt-5-nano. Filterable via contextualwp_openai_fallback_models.
     *
     * @var string[]
     */
    const OPENAI_FALLBACK_MODELS = [ 'gpt-5-mini', 'gpt-5-nano' ];

    public function register_route() {
        register_rest_route( 'contextualwp/v1', '/generate_context', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_request' ],
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
            'args' => [
                'context_id'  => [ 'required' => true, 'type' => 'string' ],
                'prompt'      => [ 'required' => false, 'type' => 'string' ],
                'format'      => [ 'required' => false, 'type' => 'string', 'default' => 'markdown' ],
                'source'      => [ 'required' => false, 'type' => 'string' ],
                'field_name'  => [ 'required' => false, 'type' => 'string' ],
                'field_type'  => [ 'required' => false, 'type' => 'string' ],
            ],
        ] );
    }

    public function handle_request( $request ) {
        $context_id = $request->get_param( 'context_id' );
        $prompt     = $request->get_param( 'prompt' );
        $format     = $request->get_param( 'format' );

        // Schema-aware structure answers for floating admin chat (multi): no AI call.
        // Field helper AskAI requests must bypass schema routing (prevents "post type 'ai' could not be found" etc.).
        if ( $this->should_use_schema_routing( $request ) ) {
            return $this->handle_structure_question( $request );
        }

        $prompt = $this->enhance_prompt_for_true_false_schema( $prompt, $request );

        $settings = get_option( 'contextualwp_settings', [] );
        $ai_provider = $settings['ai_provider'] ?? '';
        $api_key     = $settings['api_key'] ?? '';
        $model       = $settings['model'] ?? '';
        $max_tokens  = $settings['max_tokens'] ?? 1024;
        $temperature = $settings['temperature'] ?? 1.0;

        if ( empty( $ai_provider ) || empty( $api_key ) || empty( $model ) ) {
            return new \WP_Error( 'contextualwp_missing_settings', __( 'AI provider, API key, and model must be configured in ContextualWP settings.', 'contextualwp' ), [ 'status' => 400 ] );
        }

        // Apply smart model selection if enabled
        $provider_slug = Providers::normalize( $ai_provider );
        $model = Smart_Model_Selector::select_model( $prompt, '', $provider_slug, $model, $settings );

        // Robust multi-post context aggregation: rendered content only (no schema/ACF/media).
        if ( strtolower( trim( $context_id ) ) === 'multi' ) {
            $content = $this->build_multi_context_aggregated_content( $format, $request );
            $count   = $content === '' ? 0 : substr_count( $content, "\n---\n" ) + 1;
            $context_data = [
                'id'      => 'multi',
                'content' => $content,
                'meta'    => [
                    'type'    => 'multi',
                    'format'  => $format,
                    'count'   => $count,
                ],
            ];
            
            // Apply smart model selection for multi-context
            $model = Smart_Model_Selector::select_model( $prompt, $content, $provider_slug, $model, $settings );
            
            $is_field_helper = ( $request->get_param( 'source' ) === 'acf_field_helper' );
            if ( $is_field_helper ) {
                ACF_Schema_Helper::get_schema();
            }
            $system_message = $is_field_helper
                ? $this->get_askai_system_message( $this->detect_askai_intent( $prompt, $request ), $request )
                : 'You are a helpful assistant. Use the following context to answer.';

            // AI call (OpenAI/Mistral/Claude)
            $provider = apply_filters( 'contextualwp_ai_provider', Providers::normalize( $ai_provider ), $settings, $context_data, $request );
            $ai_response = null;
            $ai_error = null;
            switch ( $provider ) {
                case 'openai':
                    \ContextualWP\Helpers\Utilities::log_debug( [ 'model' => $model, 'prompt_length' => strlen( $prompt ), 'content_length' => strlen( $content ) ], 'generate_payload_openai_multi' );
                    $ai_response = $this->execute_openai_request( $model, $system_message, $prompt, $content, $max_tokens, $temperature, $api_key, $is_field_helper, $settings, $context_data, $request );
                    \ContextualWP\Helpers\Utilities::log_debug( $ai_response, 'generate_response_openai_multi' );
                    if ( is_wp_error( $ai_response ) ) {
                        $ai_error = $ai_response->get_error_message();
                        $ai_response = null;
                    }
                    break;
                case 'mistral':
                    $ai_payload = apply_filters( 'contextualwp_ai_payload', [
                        'model' => $model,
                        'messages' => [
                            [ 'role' => 'system', 'content' => $system_message ],
                            [ 'role' => 'user', 'content' => $prompt . "\n\nContext:\n" . $content ]
                        ],
                        'max_tokens' => $max_tokens,
                        'temperature' => $temperature,
                    ], $settings, $context_data, $request );
                    \ContextualWP\Helpers\Utilities::log_debug( $ai_payload, 'generate_payload_mistral_multi' );
                    $ai_response = $this->call_mistral_api( $ai_payload, $api_key );
                    \ContextualWP\Helpers\Utilities::log_debug( $ai_response, 'generate_response_mistral_multi' );
                    if ( is_wp_error( $ai_response ) ) {
                        $ai_error = $ai_response->get_error_message();
                        $ai_response = null;
                    }
                    break;
                case 'claude':
                    $ai_payload = apply_filters( 'contextualwp_ai_payload', [
                        'model' => $model,
                        'max_tokens' => $max_tokens,
                        'system' => $system_message,
                        'messages' => [
                            [ 'role' => 'user', 'content' => $prompt . "\n\nContext:\n" . $content ]
                        ],
                    ], $settings, $context_data, $request );
                    \ContextualWP\Helpers\Utilities::log_debug( $ai_payload, 'generate_payload_claude_multi' );
                    $ai_response = $this->call_claude_api( $ai_payload, $api_key );
                    \ContextualWP\Helpers\Utilities::log_debug( $ai_response, 'generate_response_claude_multi' );
                    if ( is_wp_error( $ai_response ) ) {
                        $ai_error = $ai_response->get_error_message();
                        $ai_response = null;
                    }
                    break;
                default:
                    $ai_error = 'Unsupported AI provider: ' . esc_html( $provider );
                    break;
            }
            $ai_response = apply_filters( 'contextualwp_ai_response', $ai_response, $provider, $settings, $context_data, $request );
            if ( $ai_error ) {
                error_log( 'ContextualWP AI error: ' . $ai_error );
            }
            $response = [
                'message'    => $ai_error ? 'AI provider error: ' . $ai_error : 'AI response generated.',
                'provider'   => $ai_provider,
                'model'      => $model,
                'max_tokens' => $max_tokens,
                'temperature'=> $temperature,
                'context_id' => 'multi',
                'prompt'     => $prompt,
                'format'     => $format,
                'context'    => $context_data,
                'ai'         => $ai_response,
            ];
            return $response;
        }

        // Parse context_id (e.g., post-123 or post-0 for new post)
        $parsed = Utilities::parse_post_id( $context_id );
        if ( ! $parsed ) {
            return new \WP_Error( 'invalid_context_id', 'Invalid context_id format', [ 'status' => 400 ] );
        }

        $post = null;
        if ( $parsed['id'] > 0 ) {
            $post = get_post( $parsed['id'] );
            if ( ! $post ) {
                return new \WP_Error( 'not_found', 'Context not found', [ 'status' => 404 ] );
            }
            // Ensure the requested type matches the actual post type (case-insensitive).
            if ( strtolower( $post->post_type ) !== strtolower( $parsed['type'] ) ) {
                $valid_types = [ $post->post_type ];
                $registered_types = get_post_types( [], 'names' );
                if ( in_array( strtolower( $parsed['type'] ), array_map( 'strtolower', $registered_types ), true ) ) {
                    return new \WP_Error( 'invalid_post_type', sprintf( 'Post type mismatch: requested type "%s" does not match actual post type "%s" for ID %d.', $parsed['type'], $post->post_type, $post->ID ), [ 'status' => 400 ] );
                }
                return new \WP_Error( 'invalid_post_type', sprintf( 'Post type "%s" is not valid for post ID %d. Actual type: "%s".', $parsed['type'], $post->ID, $post->post_type ), [ 'status' => 400 ] );
            }
            if ( ! Utilities::can_access_post( $post ) ) {
                return new \WP_Error( 'rest_forbidden', 'Access denied', [ 'status' => 403 ] );
            }
        } else {
            // context_id is e.g. post-0 (new post screen): ensure post type exists and user can create it
            $post_type_obj = get_post_type_object( $parsed['type'] );
            if ( ! $post_type_obj ) {
                return new \WP_Error( 'invalid_context_id', 'Invalid post type for new post context.', [ 'status' => 400 ] );
            }
            if ( ! current_user_can( $post_type_obj->cap->create_posts ) ) {
                return new \WP_Error( 'rest_forbidden', 'Access denied', [ 'status' => 403 ] );
            }
        }

        // Format content and build context_data (for id 0 use minimal placeholder)
        if ( $post ) {
            $content = Utilities::format_content( $post, $format );
            $acf_fields = [];
            if ( function_exists( 'get_fields' ) ) {
                try {
                    $acf_fields = get_fields( $post->ID ) ?: [];
                } catch ( \Exception $e ) {
                    error_log( 'ContextualWP: ACF fields error for post ' . $post->ID . ': ' . $e->getMessage() );
                }
            }
            $context_data = apply_filters( 'contextualwp_context_data', [
                'id'      => $context_id,
                'content' => $content,
                'meta'    => [
                    'title'     => get_the_title( $post ),
                    'type'      => $post->post_type,
                    'status'    => $post->post_status,
                    'modified'  => $post->post_modified,
                    'modified_gmt' => $post->post_modified_gmt,
                    'format'    => $format,
                    'acf'       => $acf_fields ?: new \stdClass(),
                ],
            ], $post, $request );
        } else {
            $content = sprintf( __( '(New %s – no content yet.)', 'contextualwp' ), $parsed['type'] );
            $context_data = apply_filters( 'contextualwp_context_data', [
                'id'      => $context_id,
                'content' => $content,
                'meta'    => [
                    'title'     => '',
                    'type'      => $parsed['type'],
                    'status'    => 'draft',
                    'modified'  => '',
                    'modified_gmt' => '',
                    'format'    => $format,
                    'acf'       => new \stdClass(),
                ],
            ], null, $request );
        }

        // Apply smart model selection for single post context
        $model = Smart_Model_Selector::select_model( $prompt, $content, $provider_slug, $model, $settings );

        $is_field_helper = ( $request->get_param( 'source' ) === 'acf_field_helper' );
        if ( $is_field_helper ) {
            ACF_Schema_Helper::get_schema();
        }
        $system_message = $this->get_system_message_for_single_context( $context_data, $prompt, $request );

        // Cache key uses provider, model and context parameters
        $cache_key = apply_filters( 'contextualwp_ai_cache_key', \ContextualWP\Helpers\Utilities::get_cache_key(
            'contextualwp_generate', [
                'provider'   => Providers::normalize( $ai_provider ),
                'model'      => $model,
                'context_id' => $context_id,
                'prompt'     => $prompt,
                'format'     => $format,
                'modified'   => $post ? $post->post_modified_gmt : $context_id,
            ]
        ), $request, $context_data, $settings );
        $cached = wp_cache_get( $cache_key, 'contextualwp' );
        if ( $cached !== false ) {
            return rest_ensure_response( array_merge( $cached, [ 'cached' => true ] ) );
        }

        // Provider extensibility: allow other plugins to register/override AI providers
        $ai_response = null;
        $ai_error = null;
        $provider = apply_filters( 'contextualwp_ai_provider', Providers::normalize( $ai_provider ), $settings, $context_data, $request );
        switch ( $provider ) {
            case 'openai':
                \ContextualWP\Helpers\Utilities::log_debug( [ 'model' => $model, 'prompt_length' => strlen( $prompt ), 'content_length' => strlen( $content ) ], 'generate_payload_openai' );
                $ai_response = $this->execute_openai_request( $model, $system_message, $prompt, $content, $max_tokens, $temperature, $api_key, $is_field_helper, $settings, $context_data, $request );
                \ContextualWP\Helpers\Utilities::log_debug( $ai_response, 'generate_response_openai' );
                if ( is_wp_error( $ai_response ) ) {
                    $ai_error = $ai_response->get_error_message();
                    $ai_response = null;
                }
                break;
            case 'mistral':
                $ai_payload = apply_filters( 'contextualwp_ai_payload', [
                    'model' => $model,
                    'messages' => [
                        [ 'role' => 'system', 'content' => $system_message ],
                        [ 'role' => 'user', 'content' => $prompt . "\n\nContext:\n" . $content ]
                    ],
                    'max_tokens' => $max_tokens,
                    'temperature' => $temperature,
                ], $settings, $context_data, $request );
                \ContextualWP\Helpers\Utilities::log_debug( $ai_payload, 'generate_payload_mistral' );
                $ai_response = $this->call_mistral_api( $ai_payload, $api_key );
                \ContextualWP\Helpers\Utilities::log_debug( $ai_response, 'generate_response_mistral' );
                if ( is_wp_error( $ai_response ) ) {
                    $ai_error = $ai_response->get_error_message();
                    $ai_response = null;
                }
                break;
            case 'claude':
                $ai_payload = apply_filters( 'contextualwp_ai_payload', [
                    'model' => $model,
                    'max_tokens' => $max_tokens,
                    'system' => $system_message,
                    'messages' => [
                        [ 'role' => 'user', 'content' => $prompt . "\n\nContext:\n" . $content ]
                    ],
                ], $settings, $context_data, $request );
                \ContextualWP\Helpers\Utilities::log_debug( $ai_payload, 'generate_payload_claude' );
                $ai_response = $this->call_claude_api( $ai_payload, $api_key );
                \ContextualWP\Helpers\Utilities::log_debug( $ai_response, 'generate_response_claude' );
                if ( is_wp_error( $ai_response ) ) {
                    $ai_error = $ai_response->get_error_message();
                    $ai_response = null;
                }
                break;
            // Add more providers here (e.g., 'anthropic')
            default:
                $ai_error = 'Unsupported AI provider: ' . esc_html( $provider );
                break;
        }

        // Allow filtering/modification of the AI response before returning
        $ai_response = apply_filters( 'contextualwp_ai_response', $ai_response, $provider, $settings, $context_data, $request );

        // Log errors for debugging (never expose API keys)
        if ( $ai_error ) {
            error_log( 'ContextualWP AI error: ' . $ai_error );
        }

        $response = [
            'message'    => $ai_error ? 'AI provider error: ' . $ai_error : 'AI response generated.',
            'provider'   => $ai_provider,
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'temperature'=> $temperature,
            'context_id' => $context_id,
            'prompt'     => $prompt,
            'format'     => $format,
            'context'    => $context_data,
            'ai'         => $ai_response,
        ];

        $cache_ttl = apply_filters( 'contextualwp_ai_cache_ttl', 5 * MINUTE_IN_SECONDS, $provider, $context_data, $request );
        if ( ! $ai_error && $cache_ttl > 0 ) {
            wp_cache_set( $cache_key, $response, 'contextualwp', $cache_ttl );
        }

        return $response;
    }

    /**
     * Build aggregated context content for context_id=multi from rendered post/page body copy.
     * Uses the same the_content rendering path as single-context (Utilities::format_content).
     * No schema, ACF field lists, or attachment metadata; minimal meta (title, stable id, type).
     *
     * @param string             $format  Output format: markdown, plain, html.
     * @param \WP_REST_Request   $request Request (for filter).
     * @return string Aggregated content with "## {Label}: {Title} ({id})\n\n{body}" per item, separated by "\n---\n".
     */
    protected function build_multi_context_aggregated_content( $format, $request ) {
        $query = new \WP_Query( [
            'post_type'      => [ 'post', 'page' ],
            'posts_per_page' => 5,
            'post_status'    => 'publish',
            'orderby'        => [ 'post_modified' => 'DESC', 'ID' => 'DESC' ],
            'order'          => 'DESC',
        ] );
        $posts = $query->posts;
        if ( empty( $posts ) ) {
            return '';
        }

        $max_chars = (int) apply_filters( 'contextualwp_multi_context_item_max_chars', self::MULTI_CONTEXT_ITEM_MAX_CHARS, $request );
        $max_chars = $max_chars > 0 ? $max_chars : self::MULTI_CONTEXT_ITEM_MAX_CHARS;
        $blocks    = [];

        foreach ( $posts as $post ) {
            $pt_obj   = get_post_type_object( $post->post_type );
            $label    = $pt_obj && isset( $pt_obj->labels->singular_name ) ? $pt_obj->labels->singular_name : $post->post_type;
            $stable_id = $post->post_type . '-' . $post->ID;
            $title_safe = get_the_title( $post );

            $full = Utilities::format_content( $post, $format );
            $body = $this->extract_body_from_formatted_content( $full, $format );
            if ( strlen( $body ) > $max_chars ) {
                $body = substr( $body, 0, $max_chars ) . '…(truncated)';
            }

            if ( $format === 'html' ) {
                $header = '<h2>' . esc_html( $label . ': ' . $title_safe . ' (' . $stable_id . ')' ) . '</h2>';
                $blocks[] = $header . ( $body !== '' ? wp_kses_post( $body ) : '<p>' . esc_html__( 'No content found.', 'contextualwp' ) . '</p>' );
            } else {
                $blocks[] = '## ' . esc_html( $label ) . ': ' . esc_html( $title_safe ) . ' (' . $stable_id . ")\n\n" . $body;
            }
        }

        return implode( "\n---\n", $blocks );
    }

    /**
     * Extract body (content after title) from Utilities::format_content output.
     *
     * @param string $full   Full formatted output (markdown/plain: "Title\n\nbody", html: "<h2>Title</h2><div>body</div>").
     * @param string $format One of markdown, plain, html.
     * @return string Body only.
     */
    private function extract_body_from_formatted_content( $full, $format ) {
        if ( $format === 'html' ) {
            $body = preg_replace( '/^<h2>.*?<\/h2>/s', '', $full );
            return trim( $body );
        }
        $pos = strpos( $full, "\n\n" );
        return $pos !== false ? trim( substr( $full, $pos + 2 ) ) : $full;
    }

    /**
     * Enhance AskAI prompt for true_false fields when schema metadata is available.
     * Injects instructions, controlled_fields_summary, and conditional_logic_summary
     * so the AI can explain ON/OFF behaviour in practical terms without hedging.
     *
     * @param string         $prompt  Original prompt.
     * @param \WP_REST_Request $request Request.
     * @return string Enhanced prompt (unchanged if not true_false or no schema).
     */
    private function enhance_prompt_for_true_false_schema( $prompt, $request ) {
        if ( ! is_string( $prompt ) ) {
            return $prompt;
        }
        $source     = $request->get_param( 'source' );
        $field_type = strtolower( trim( (string) $request->get_param( 'field_type' ) ) );
        $field_name = $request->get_param( 'field_name' );
        if ( $source !== 'acf_field_helper' || $field_type !== 'true_false' || empty( $field_name ) || ! is_string( $field_name ) ) {
            return $prompt;
        }
        $field_name = trim( $field_name );
        $schema     = ACF_Schema_Helper::get_field_by_name( $field_name );
        if ( ! $schema || ! is_array( $schema ) ) {
            return $prompt;
        }
        $parts = [];
        if ( ! empty( $schema['instructions'] ) ) {
            $parts[] = 'Instructions: ' . $schema['instructions'];
        }
        if ( ! empty( $schema['controlled_fields_summary'] ) ) {
            $parts[] = 'What turning ON/OFF does: ' . $schema['controlled_fields_summary'];
        }
        if ( ! empty( $schema['conditional_logic_summary'] ) ) {
            $parts[] = 'This field is shown when: ' . $schema['conditional_logic_summary'];
        }
        if ( empty( $parts ) ) {
            return $prompt;
        }
        $schema_block = "---\nAuthoritative schema for this true/false field (use this to explain ON vs OFF behaviour):\n" . implode( "\n", $parts ) . "\n---\n\n";
        $instruction  = "\n\nUse the schema above to explain what turning this field ON vs OFF does. Be direct and editor-focused. Do not hedge or say 'depends on implementation'.";
        return $schema_block . $prompt . $instruction;
    }

    /**
     * Detect AskAI intent from user question text (explain vs advise).
     * Used to route responses to different styles. Returns null if intent cannot be confidently determined.
     *
     * @param string         $prompt  Full prompt (may include field metadata).
     * @param \WP_REST_Request $request Request.
     * @return string|null 'explain'|'advise'|null
     */
    private function detect_askai_intent( $prompt, $request ) {
        if ( $request->get_param( 'source' ) !== 'acf_field_helper' || ! is_string( $prompt ) ) {
            return null;
        }
        $user_text = $this->extract_askai_user_question( $prompt );
        if ( $user_text === '' ) {
            return null;
        }
        $lower = strtolower( $user_text );

        $advise_phrases = [
            'what should', 'what\'s best', 'whats best', 'what is the best', 'best way', 'what to put',
            'what size', 'what image', 'what dimension', 'what format', 'what value',
            'recommend', 'suggest', 'what content', 'how long should', 'how many',
        ];
        foreach ( $advise_phrases as $p ) {
            if ( strpos( $lower, $p ) !== false ) {
                return 'advise';
            }
        }

        $explain_phrases = [
            'what does', 'what is', 'what are', 'why is', 'why does', 'why are',
            'how does', 'how is', 'explain', 'meaning of', 'purpose of', 'describe',
            'what\'s this', 'whats this', 'what is this', 'what does this',
            'why is this', 'why is this here',
        ];
        foreach ( $explain_phrases as $p ) {
            if ( strpos( $lower, $p ) !== false ) {
                return 'explain';
            }
        }

        return null;
    }

    /**
     * Extract the user's question from an AskAI prompt (before ACF metadata block).
     *
     * @param string $prompt Full prompt.
     * @return string
     */
    private function extract_askai_user_question( $prompt ) {
        if ( ! is_string( $prompt ) ) {
            return '';
        }
        $idx = strpos( $prompt, 'ACF Field context:' );
        if ( $idx === false ) {
            return trim( $prompt );
        }
        $before = substr( $prompt, 0, $idx );
        $before = preg_replace( '/^---\s*\n.*?---\s*\n\s*/s', '', $before );
        return trim( $before );
    }

    /**
     * Get system message for single-context requests. Applies strict grounding only for CPTs.
     * Post, page and multi use the default or AskAI message.
     *
     * @param array             $context_data Context data with meta.type.
     * @param string            $prompt       User prompt.
     * @param \WP_REST_Request  $request      Request.
     * @return string
     */
    private function get_system_message_for_single_context( array $context_data, $prompt, $request ) {
        if ( $this->is_single_cpt_context( $context_data ) ) {
            return $this->get_strict_grounding_system_message();
        }
        $is_field_helper = ( $request->get_param( 'source' ) === 'acf_field_helper' );
        return $is_field_helper
            ? $this->get_askai_system_message( $this->detect_askai_intent( $prompt, $request ), $request )
            : 'You are a helpful assistant. Use the following context to answer.';
    }

    /**
     * Whether this is a single-CPT context (not post, page, or multi).
     * Used to apply strict grounding so AI responses do not infer or embellish.
     *
     * @param array $context_data Context data with meta.type.
     * @return bool
     */
    private function is_single_cpt_context( array $context_data ) {
        $type = isset( $context_data['meta']['type'] ) ? strtolower( trim( (string) $context_data['meta']['type'] ) ) : '';
        if ( $type === '' ) {
            return false;
        }
        return $type !== 'post' && $type !== 'page';
    }

    /**
     * System message for strict grounding: single CPT only. Responses must use only
     * facts explicitly in context; say "Not stated in the content." when missing.
     *
     * @return string
     */
    private function get_strict_grounding_system_message() {
        return 'You are a helpful assistant. You must answer ONLY using facts explicitly stated in the provided context. '
            . 'Do not infer, assume, or add details that are not in the context. '
            . 'If the context does not contain the answer or the information is not stated, respond with exactly: Not stated in the content.';
    }

    /**
     * Get system message for AskAI based on detected intent and field type.
     *
     * @param string|null $intent 'explain'|'advise'|null
     * @param \WP_REST_Request $request Request (for field_type).
     * @return string
     */
    private function get_askai_system_message( $intent, $request ) {
        if ( $intent === 'explain' ) {
            return 'You are a helpful assistant. Use the following context to answer. Be factual and descriptive only. Do not give recommendations or advice.';
        }
        if ( $intent === 'advise' ) {
            $field_type = strtolower( trim( (string) ( $request->get_param( 'field_type' ) ?? '' ) ) );
            $text_field_types = [ 'text', 'textarea', 'wysiwyg' ];
            if ( in_array( $field_type, $text_field_types, true ) ) {
                return 'You are a helpful assistant for content editors. Use the following context to answer. For text/textarea fields: return 2–4 concise bullets with practical guidance (format, what to include/exclude, example pattern). Avoid repeating the field definition. Do not invent site-specific content (place names, etc).';
            }
            return 'You are a helpful assistant for content editors. Use the following context to answer. Give concise, opinionated guidance. Do not explain field mechanics, toggle labels, or metadata—focus on actionable recommendations.';
        }
        return 'You are a helpful assistant. Use the following context to answer.';
    }

    /**
     * Whether to route this request to schema-based structure answers (no AI call).
     * Field helper AskAI requests are always bypassed to avoid mis-routing (e.g. "post type 'ai' could not be found").
     *
     * @param \WP_REST_Request $request Request.
     * @return bool
     */
    private function should_use_schema_routing( $request ) {
        if ( strtolower( trim( (string) $request->get_param( 'context_id' ) ) ) !== 'multi' ) {
            return false;
        }
        if ( $request->get_param( 'source' ) === 'acf_field_helper' ) {
            return false;
        }
        $prompt = $request->get_param( 'prompt' );
        return $this->is_structure_question( is_string( $prompt ) ? $prompt : '' );
    }

    /**
     * Lightweight intent detection for "structure" questions (CPTs, taxonomies, ACF, schema).
     * Used to serve deterministic schema-based answers without an AI call.
     *
     * @param string $prompt User prompt.
     * @return bool
     */
    private function is_structure_question( $prompt ) {
        if ( ! is_string( $prompt ) || trim( $prompt ) === '' ) {
            return false;
        }
        $k = [ 'cpt', 'cpts', 'post type', 'post types', 'taxonomy', 'taxonomies', 'acf', 'field group', 'fields', 'schema' ];
        $lower = strtolower( $prompt );
        foreach ( $k as $keyword ) {
            if ( strpos( $lower, $keyword ) !== false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Intent constants for schema-based structure answers.
     */
    const INTENT_ACF_BY_POST_TYPE   = 'acf_by_post_type';
    const INTENT_GENERIC_OVERVIEW  = 'generic_schema_overview';
    const INTENT_UNKNOWN_POST_TYPE = 'unknown_post_type';

    /**
     * Detect schema answer intent from prompt and schema.
     *
     * @param string $prompt User prompt.
     * @param array  $schema Schema from Schema::get_schema_data().
     * @return array { intent: string, post_type_slug: string|null, requested_slug: string|null }
     */
    private function get_schema_intent( $prompt, array $schema ) {
        $all_pt = isset( $schema['post_types'] ) && is_array( $schema['post_types'] ) ? $schema['post_types'] : [];
        $all_slugs = array_filter( array_map( function ( $p ) {
            return isset( $p['slug'] ) ? $p['slug'] : '';
        }, $all_pt ) );
        $custom_pt_slugs = array_values( array_filter( $all_slugs, function ( $s ) {
            return ! in_array( $s, [ 'post', 'page', 'attachment' ], true );
        } ) );

        if ( $this->acf_requested( $prompt ) ) {
            $resolved = $this->extract_requested_post_type_for_acf( $prompt, array_values( $all_slugs ) );
            if ( $resolved !== null ) {
                return [
                    'intent'          => self::INTENT_ACF_BY_POST_TYPE,
                    'post_type_slug'  => $resolved,
                    'requested_slug'  => null,
                ];
            }
            $candidate = $this->extract_candidate_post_type_from_prompt( $prompt );
            if ( $candidate !== null && $candidate !== '' ) {
                return [
                    'intent'          => self::INTENT_UNKNOWN_POST_TYPE,
                    'post_type_slug'  => null,
                    'requested_slug'  => $candidate,
                ];
            }
        }

        return [
            'intent'          => self::INTENT_GENERIC_OVERVIEW,
            'post_type_slug'  => null,
            'requested_slug'  => null,
        ];
    }

    /**
     * Extract a candidate post type phrase from prompt (e.g. "ACF for plotz" → "plotz").
     * Used to detect unknown post type when resolved slug is null.
     *
     * @param string $prompt User prompt.
     * @return string|null
     */
    private function extract_candidate_post_type_from_prompt( $prompt ) {
        if ( ! is_string( $prompt ) || trim( $prompt ) === '' ) {
            return null;
        }
        $lower = strtolower( trim( $prompt ) );
        $stop_re = $this->get_stopwords_regex();
        $stopwords = array_fill_keys( $this->get_post_type_stopwords(), true );
        // Allow optional stopwords between preposition and slug: "assigned to the plot CPT"
        if ( preg_match( '/\b(?:for|assigned\s+to)\s+' . $stop_re . '(?:post\s+type\s+)?([a-z0-9_-]+)\b/i', $lower, $m ) ) {
            $candidate = $m[1];
            if ( ! isset( $stopwords[ $candidate ] ) ) {
                return $candidate;
            }
        }
        if ( preg_match( '/\b(?:acf|field\s+groups?|fields)\s+([a-z0-9_-]+)\b/i', $lower, $m ) ) {
            $candidate = $m[1];
            if ( ! isset( $stopwords[ $candidate ] ) ) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Build the single schema source footer line. Call once per response to avoid duplication.
     *
     * @param array $schema Schema with generated_at.
     * @return string
     */
    private function build_schema_footer( array $schema ) {
        $gen = isset( $schema['generated_at'] ) ? $schema['generated_at'] : '-';
        return 'Source: schema (generated at ' . $gen . ').';
    }

    /**
     * Handle structure questions: fetch schema, route by intent, build answer, return chat-shaped response.
     * Reuses schema cache/TTL. No AI call.
     *
     * @param \WP_REST_Request $request
     * @return array|\WP_Error
     */
    private function handle_structure_question( $request ) {
        try {
            $schema = ( new Schema() )->get_schema_data();
        } catch ( \Exception $e ) {
            Utilities::log_debug( $e->getMessage(), 'schema_structure' );
            return new \WP_Error(
                'schema_structure_failed',
                __( 'Could not load schema for structure answer.', 'contextualwp' ),
                [ 'status' => 500 ]
            );
        }

        $prompt = is_string( $request->get_param( 'prompt' ) ) ? $request->get_param( 'prompt' ) : '';
        $output = $this->build_structure_answer( $schema, $prompt );
        $generated_at = isset( $schema['generated_at'] ) ? $schema['generated_at'] : current_time( 'c', true );

        $response = [
            'message'    => 'Structure answer from schema.',
            'provider'   => '',
            'model'      => '',
            'max_tokens' => 0,
            'temperature' => 0,
            'context_id' => 'multi',
            'prompt'     => $request->get_param( 'prompt' ),
            'format'     => $request->get_param( 'format' ),
            'context'    => [
                'id'      => 'multi',
                'content' => '',
                'meta'    => [ 'type' => 'multi', 'structure' => true ],
            ],
            'ai' => [
                'output' => $output,
                'raw'    => null,
            ],
            'sources' => [
                'used_schema'        => true,
                'schema_generated_at' => $generated_at,
            ],
        ];
        return $response;
    }

    /**
     * Whether the prompt explicitly requests ACF (field groups / fields).
     *
     * @param string $prompt User prompt.
     * @return bool
     */
    private function acf_requested( $prompt ) {
        if ( ! is_string( $prompt ) || trim( $prompt ) === '' ) {
            return false;
        }
        $k = [ 'acf', 'field group', 'field groups', 'fields', 'relationship fields' ];
        $lower = strtolower( $prompt );
        foreach ( $k as $keyword ) {
            if ( strpos( $lower, $keyword ) !== false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Common stopwords to ignore when extracting post type from prompt (e.g. "ACF assigned to the plot CPT").
     *
     * @return array
     */
    private function get_post_type_stopwords() {
        return [ 'the', 'a', 'an', 'to', 'for', 'of', 'in', 'on', 'at', 'by', 'with' ];
    }

    /**
     * Regex fragment for optional stopwords before a post type mention (e.g. "the ", "a ").
     *
     * @return string
     */
    private function get_stopwords_regex() {
        $stop = $this->get_post_type_stopwords();
        $parts = array_map( function ( $w ) {
            return preg_quote( $w, '/' ) . '\s+';
        }, $stop );
        return '(?:' . implode( '|', $parts ) . ')*';
    }

    /**
     * Extract post type slug from prompt when asking for ACF (e.g. "ACF for plots", "field groups for post type developments").
     * Ignores stopwords (the, a, an, etc.). Prefers exact slug match. Handles singular/plural (plot -> plots).
     *
     * @param string $prompt User prompt.
     * @param array  $custom_pt_slugs Known custom post type slugs.
     * @return string|null Slug or null if none specified.
     */
    private function extract_requested_post_type_for_acf( $prompt, array $custom_pt_slugs ) {
        if ( ! is_string( $prompt ) || trim( $prompt ) === '' || empty( $custom_pt_slugs ) ) {
            return null;
        }
        $lower = strtolower( trim( $prompt ) );
        $stop_re = $this->get_stopwords_regex();

        // Build a map of slugs to their variants: exact slug first, then singular/plural, then label-style.
        $slug_variants = [];
        foreach ( $custom_pt_slugs as $slug ) {
            if ( $slug === '' ) {
                continue;
            }
            $variants = [ $slug ]; // Prefer exact match
            if ( substr( $slug, -1 ) === 's' ) {
                $variants[] = substr( $slug, 0, -1 ); // "plots" -> "plot"
            } else {
                $variants[] = $slug . 's'; // "plot" -> "plots"
            }
            $variants[] = $slug . ' cpt';
            $variants[] = $slug . ' post type';
            $variants[] = 'post type ' . $slug;
            $slug_variants[ $slug ] = $variants;
        }

        foreach ( $slug_variants as $slug => $variants ) {
            foreach ( $variants as $variant ) {
                $escaped = preg_quote( $variant, '/' );
                // Optional stopwords between preposition and slug: "assigned to the plot CPT"
                $re = $stop_re . '(?:post\s+type\s+)?' . $escaped . '\b';
                if ( preg_match( '/\b(?:acf|field\s+group|field\s+groups|fields)\s+(?:assigned\s+to|for)\s+' . $re . '/i', $lower ) ) {
                    return $slug;
                }
                if ( preg_match( '/\bfor\s+' . $re . '/i', $lower ) ) {
                    return $slug;
                }
                if ( preg_match( '/\b' . $escaped . '\s+(?:cpt\s+)?(?:acf|field\s+group|field\s+groups|fields)\b/i', $lower ) ) {
                    return $slug;
                }
                if ( preg_match( '/\b(?:acf|field\s+group|field\s+groups|fields)\s+' . $escaped . '\b/i', $lower ) ) {
                    return $slug;
                }
                if ( preg_match( '/\bshow\s+(?:acf\s+)?(?:field\s+group|field\s+groups|fields)\s+for\s+' . $re . '/i', $lower ) ) {
                    return $slug;
                }
                if ( preg_match( '/\blist\s+(?:all\s+)?(?:acf|field\s+group|field\s+groups|fields)\s+(?:assigned\s+to|for)\s+' . $re . '/i', $lower ) ) {
                    return $slug;
                }
            }
        }

        return null;
    }

    /**
     * Extract post types from ACF location rules (param = post_type, operator = ==).
     *
     * @param array $location ACF location array.
     * @return array Post type slugs.
     */
    private function post_types_from_acf_location( $location ) {
        $out = [];
        if ( ! is_array( $location ) ) {
            return $out;
        }
        foreach ( $location as $group ) {
            if ( ! is_array( $group ) ) {
                continue;
            }
            foreach ( $group as $rule ) {
                if ( ! is_array( $rule ) ) {
                    continue;
                }
                $param = isset( $rule['param'] ) ? $rule['param'] : '';
                $op    = isset( $rule['operator'] ) ? $rule['operator'] : '';
                $val   = isset( $rule['value'] ) ? $rule['value'] : '';
                if ( $param === 'post_type' && $op === '==' && $val !== '' ) {
                    $out[] = is_string( $val ) ? $val : (string) $val;
                }
            }
        }
        return array_unique( $out );
    }

    /**
     * Check if prompt requests ACF blocks to be included.
     *
     * @param string $prompt User prompt.
     * @return bool
     */
    private function blocks_requested( $prompt ) {
        if ( ! is_string( $prompt ) || trim( $prompt ) === '' ) {
            return false;
        }
        $lower = strtolower( trim( $prompt ) );
        $keywords = [ 'block', 'blocks', 'include block', 'include blocks', 'and include', 'with blocks' ];
        foreach ( $keywords as $keyword ) {
            if ( strpos( $lower, $keyword ) !== false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if an ACF field group is a block group (location param == "block").
     *
     * @param array $group ACF field group.
     * @param string $post_type_slug Post type slug to match block name against.
     * @return bool
     */
    private function is_block_group( array $group, $post_type_slug = '' ) {
        $location = isset( $group['location'] ) ? $group['location'] : [];
        if ( ! is_array( $location ) ) {
            return false;
        }
        foreach ( $location as $group_rules ) {
            if ( ! is_array( $group_rules ) ) {
                continue;
            }
            foreach ( $group_rules as $rule ) {
                if ( ! is_array( $rule ) ) {
                    continue;
                }
                $param = isset( $rule['param'] ) ? $rule['param'] : '';
                if ( $param === 'block' ) {
                    // If post_type_slug provided, check if block name matches
                    if ( $post_type_slug !== '' ) {
                        $value = isset( $rule['value'] ) ? $rule['value'] : '';
                        $title = isset( $group['title'] ) ? strtolower( $group['title'] ) : '';
                        // Check if block name contains post type slug or title starts with "Block: <PostType>"
                        if ( is_string( $value ) && ( strpos( strtolower( $value ), $post_type_slug . '-' ) === 0 || strpos( strtolower( $value ), $post_type_slug ) !== false ) ) {
                            return true;
                        }
                        if ( strpos( $title, 'block:' ) === 0 && strpos( $title, $post_type_slug ) !== false ) {
                            return true;
                        }
                    }
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Filter ACF field groups to those that apply to the given post type.
     * Optionally includes or excludes block groups.
     *
     * @param array  $groups Schema acf_field_groups.
     * @param string $post_type Post type slug.
     * @param bool   $include_blocks Whether to include block groups.
     * @return array Filtered groups with 'field_count' key added.
     */
    private function acf_groups_for_post_type( array $groups, $post_type, $include_blocks = false ) {
        $filtered = [];
        foreach ( $groups as $g ) {
            $loc = isset( $g['location'] ) ? $g['location'] : [];
            $is_block = $this->is_block_group( $g, '' ); // Check if it's any block group
            
            // Skip block groups unless explicitly requested
            if ( $is_block && ! $include_blocks ) {
                continue;
            }
            
            // Check if this is a post_type-targeted group
            $pts = $this->post_types_from_acf_location( $loc );
            if ( in_array( $post_type, $pts, true ) ) {
                // This group targets the post type
                $count = isset( $g['fields'] ) && is_array( $g['fields'] ) ? count( $g['fields'] ) : 0;
                $g['field_count'] = $count;
                $filtered[] = $g;
            } elseif ( $is_block && $include_blocks ) {
                // Check if block group matches the post type (e.g., "plot-*" blocks or "Block: Plot")
                if ( $this->is_block_group( $g, $post_type ) ) {
                    $count = isset( $g['fields'] ) && is_array( $g['fields'] ) ? count( $g['fields'] ) : 0;
                    $g['field_count'] = $count;
                    $filtered[] = $g;
                }
            }
        }
        usort( $filtered, function ( $a, $b ) {
            return ( $b['field_count'] ?? 0 ) - ( $a['field_count'] ?? 0 );
        } );
        return $filtered;
    }

    /**
     * Build short plain-text structure answer from schema. Routes by intent; appends schema footer once.
     *
     * @param array  $schema From Schema::get_schema_data().
     * @param string $prompt User prompt.
     * @return string
     */
    private function build_structure_answer( array $schema, $prompt = '' ) {
        $intent_data = $this->get_schema_intent( $prompt, $schema );
        switch ( $intent_data['intent'] ) {
            case self::INTENT_ACF_BY_POST_TYPE:
                $body = $this->format_intent_acf_by_post_type( $schema, $prompt, $intent_data['post_type_slug'] );
                break;
            case self::INTENT_UNKNOWN_POST_TYPE:
                $body = $this->format_intent_unknown_post_type( $schema, $intent_data['requested_slug'] );
                break;
            default:
                $body = $this->format_intent_generic_overview( $schema, $prompt );
        }
        return rtrim( $body ) . "\n\n" . $this->build_schema_footer( $schema );
    }

    /**
     * Format body for ACF-by-post-type intent. Only groups with location param=post_type value=slug; blocks excluded unless requested.
     *
     * @param array  $schema Schema data.
     * @param string $prompt User prompt.
     * @param string $post_type_slug Resolved post type slug.
     * @return string Body only (no footer).
     */
    private function format_intent_acf_by_post_type( array $schema, $prompt, $post_type_slug ) {
        $acf_groups = isset( $schema['acf_field_groups'] ) && is_array( $schema['acf_field_groups'] ) ? $schema['acf_field_groups'] : [];
        $include_blocks = $this->blocks_requested( $prompt );
        $for_pt = $this->acf_groups_for_post_type( $acf_groups, $post_type_slug, $include_blocks );

        $lines = [];
        $lines[] = 'ACF Field Groups for "' . esc_html( $post_type_slug ) . '"';
        $lines[] = '';

        if ( ! empty( $for_pt ) ) {
            foreach ( $for_pt as $g ) {
                $title = isset( $g['title'] ) ? $g['title'] : '(unnamed)';
                $key = isset( $g['key'] ) ? $g['key'] : '';
                $field_count = isset( $g['field_count'] ) ? (int) $g['field_count'] : 0;
                $fields = isset( $g['fields'] ) && is_array( $g['fields'] ) ? $g['fields'] : [];
                $lines[] = '### ' . esc_html( $title );
                if ( $key !== '' ) {
                    $lines[] = 'Group Key: ' . esc_html( $key );
                }
                $lines[] = 'Field Count: ' . $field_count;
                $lines[] = '';
                if ( ! empty( $fields ) ) {
                    $lines[] = 'Fields:';
                    foreach ( $fields as $field ) {
                        $field_label = isset( $field['label'] ) ? $field['label'] : '';
                        $field_name = isset( $field['name'] ) ? $field['name'] : '';
                        $field_key = isset( $field['key'] ) ? $field['key'] : '';
                        $field_type = isset( $field['type'] ) ? $field['type'] : '';
                        $field_line = '  - ' . esc_html( $field_label );
                        if ( $field_name !== '' && $field_name !== $field_label ) {
                            $field_line .= ' (' . esc_html( $field_name ) . ')';
                        }
                        $field_line .= ' [' . esc_html( $field_type ) . ']';
                        if ( $field_key !== '' ) {
                            $field_line .= ' — Key: ' . esc_html( $field_key );
                        }
                        $lines[] = $field_line;
                    }
                } else {
                    $lines[] = '  (No fields)';
                }
                $lines[] = '';
            }
        } else {
            $lines[] = 'No ACF field groups found for this post type.';
            if ( ! $include_blocks ) {
                $lines[] = '';
                $lines[] = 'Note: Block groups are excluded. Include them by asking for "ACF blocks for ' . esc_html( $post_type_slug ) . '".';
            }
        }
        if ( ! $include_blocks ) {
            $lines[] = '';
            $lines[] = 'Blocks excluded unless requested.';
        }
        return implode( "\n", $lines );
    }

    /**
     * Format body for unknown post type intent: helpful message + list of available post types from schema.
     *
     * @param array       $schema Schema data.
     * @param string|null $requested_slug Unresolved slug from prompt (e.g. "plotz").
     * @return string Body only (no footer).
     */
    private function format_intent_unknown_post_type( array $schema, $requested_slug ) {
        $all_pt = isset( $schema['post_types'] ) && is_array( $schema['post_types'] ) ? $schema['post_types'] : [];
        $available_pts = array_filter( array_map( function ( $p ) {
            return isset( $p['slug'] ) ? $p['slug'] : '';
        }, $all_pt ) );
        $lines = [];
        $lines[] = 'Post type "' . esc_html( (string) $requested_slug ) . '" could not be found.';
        $lines[] = '';
        $lines[] = 'Available post types: ' . implode( ', ', $available_pts );
        return implode( "\n", $lines );
    }

    /**
     * Format body for generic schema overview: CPTs, taxonomies, optional ACF top-5 when ACF requested.
     *
     * @param array  $schema Schema data.
     * @param string $prompt User prompt.
     * @return string Body only (no footer).
     */
    private function format_intent_generic_overview( array $schema, $prompt = '' ) {
        $builtin_pt = [ 'post', 'page', 'attachment' ];
        $builtin_tax = [ 'category', 'post_tag', 'post_format' ];
        $all_pt = isset( $schema['post_types'] ) && is_array( $schema['post_types'] ) ? $schema['post_types'] : [];
        $all_tax = isset( $schema['taxonomies'] ) && is_array( $schema['taxonomies'] ) ? $schema['taxonomies'] : [];
        $custom_pt = array_values( array_filter( $all_pt, function ( $p ) use ( $builtin_pt ) {
            $s = isset( $p['slug'] ) ? $p['slug'] : '';
            return $s !== '' && ! in_array( $s, $builtin_pt, true );
        } ) );
        $custom_tax = array_values( array_filter( $all_tax, function ( $t ) use ( $builtin_tax ) {
            $s = isset( $t['slug'] ) ? $t['slug'] : '';
            return $s !== '' && ! in_array( $s, $builtin_tax, true );
        } ) );

        $lines = [];
        $lines[] = 'Custom Post Types';
        $lines[] = '';
        if ( ! empty( $custom_pt ) ) {
            foreach ( $custom_pt as $pt ) {
                $slug  = isset( $pt['slug'] ) ? $pt['slug'] : '';
                $label = isset( $pt['label'] ) ? $pt['label'] : '';
                $lines[] = '- ' . ( $slug !== '' ? $slug . ' — ' . $label : $label );
            }
        } else {
            $lines[] = '- None.';
        }
        $lines[] = '';
        $lines[] = 'Custom Taxonomies';
        $lines[] = '';
        if ( ! empty( $custom_tax ) ) {
            foreach ( $custom_tax as $tax ) {
                $slug   = isset( $tax['slug'] ) ? $tax['slug'] : '';
                $label  = isset( $tax['label'] ) ? $tax['label'] : '';
                $objs   = isset( $tax['object_types'] ) && is_array( $tax['object_types'] ) ? $tax['object_types'] : [];
                $suffix = $objs !== [] ? ' (' . implode( ', ', $objs ) . ')' : '';
                $lines[] = '- ' . ( $slug !== '' ? $slug . ' — ' . $label . $suffix : $label . $suffix );
            }
        } else {
            $lines[] = '- None.';
        }

        $acf_groups = isset( $schema['acf_field_groups'] ) && is_array( $schema['acf_field_groups'] ) ? $schema['acf_field_groups'] : [];
        if ( $this->acf_requested( $prompt ) && ! empty( $acf_groups ) ) {
            $with_count = [];
            foreach ( $acf_groups as $g ) {
                $c = isset( $g['fields'] ) && is_array( $g['fields'] ) ? count( $g['fields'] ) : 0;
                $with_count[] = [ 'title' => $g['title'] ?? '', 'field_count' => $c ];
            }
            usort( $with_count, function ( $a, $b ) {
                return $b['field_count'] - $a['field_count'];
            } );
            $top5 = array_slice( $with_count, 0, 5 );
            $lines[] = '';
            $lines[] = 'ACF field groups (top 5 by field count)';
            $lines[] = '';
            foreach ( $top5 as $g ) {
                $lines[] = '- ' . ( $g['title'] ?: '(unnamed)' ) . ' — ' . $g['field_count'] . ' field(s)';
            }
            $lines[] = '';
            $lines[] = 'Specify a post type to see groups, e.g. "ACF for plots" or "Show ACF field groups for plots".';
        }
        return implode( "\n", $lines );
    }

    /**
     * Whether the given OpenAI model uses the Responses API (/v1/responses) instead of Chat Completions.
     *
     * @param string $model Model identifier (e.g. gpt-5.2, gpt-5-mini).
     * @return bool
     */
    private function openai_uses_responses_api( $model ) {
        $models = apply_filters( 'contextualwp_openai_responses_api_models', self::OPENAI_RESPONSES_API_MODELS );
        return is_array( $models ) && in_array( $model, $models, true );
    }

    /**
     * Clamp max output tokens to a safe range for Responses API and legacy calls.
     *
     * @param int $max_tokens Value from settings.
     * @return int Clamped value between OPENAI_MAX_OUTPUT_TOKENS_MIN and OPENAI_MAX_OUTPUT_TOKENS_MAX.
     */
    private function openai_clamp_max_output_tokens( $max_tokens ) {
        $n = max( 1, (int) $max_tokens );
        return min( self::OPENAI_MAX_OUTPUT_TOKENS_MAX, max( self::OPENAI_MAX_OUTPUT_TOKENS_MIN, $n ) );
    }

    /**
     * Build request payload for OpenAI Responses API (POST /v1/responses).
     * Uses instructions + input and max_output_tokens (not max_tokens).
     *
     * @param string   $model              Model ID.
     * @param string   $system_message    System/instructions text.
     * @param string   $user_content      User message (prompt + context).
     * @param int      $max_output_tokens Clamped token limit.
     * @param float    $temperature       Sampling temperature.
     * @param string|null $reasoning_effort Optional reasoning effort (e.g. 'low').
     * @return array Request body for Responses API.
     */
    private function build_openai_responses_payload( $model, $system_message, $user_content, $max_output_tokens, $temperature, $reasoning_effort = null ) {
        $payload = [
            'model'               => $model,
            'instructions'        => $system_message,
            'input'               => $user_content,
            'max_output_tokens'   => $max_output_tokens,
            'temperature'         => $temperature,
        ];
        if ( $reasoning_effort !== null && $reasoning_effort !== '' ) {
            $payload['reasoning'] = [ 'effort' => $reasoning_effort ];
        }
        return $payload;
    }

    /**
     * Parse OpenAI Responses API JSON into visible text and completeness.
     * Response has output[] of items; message items have content[] with text parts.
     *
     * @param array $data Decoded JSON response from /v1/responses.
     * @return array { 'output_text' => string, 'raw' => array, 'is_incomplete' => bool }
     */
    private function parse_openai_responses_output( $data ) {
        $output_text = '';
        $status = isset( $data['status'] ) ? $data['status'] : '';
        $is_incomplete = ( $status === 'incomplete' );

        $output_items = isset( $data['output'] ) && is_array( $data['output'] ) ? $data['output'] : [];
        foreach ( $output_items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $type = isset( $item['type'] ) ? $item['type'] : '';
            if ( $type !== 'message' ) {
                continue;
            }
            $content = isset( $item['content'] ) && is_array( $item['content'] ) ? $item['content'] : [];
            foreach ( $content as $part ) {
                if ( ! is_array( $part ) ) {
                    continue;
                }
                if ( isset( $part['text'] ) && is_string( $part['text'] ) ) {
                    $output_text .= $part['text'];
                }
            }
        }
        $output_text = trim( $output_text );
        if ( $output_text === '' && $status !== 'completed' ) {
            $is_incomplete = true;
        }

        return [
            'output_text'   => $output_text,
            'raw'           => $data,
            'is_incomplete' => $is_incomplete,
        ];
    }

    /**
     * Call OpenAI Responses API (POST /v1/responses). Returns same shape as call_openai_api.
     *
     * @param array  $payload Request body (model, instructions, input, max_output_tokens, etc).
     * @param string $api_key API key.
     * @return array|\WP_Error { output, raw } or WP_Error.
     */
    private function call_openai_responses_api( $payload, $api_key ) {
        $response = wp_remote_post( 'https://api.openai.com/v1/responses', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 60,
        ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( $code !== 200 ) {
            $message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error from OpenAI.';
            return new \WP_Error( 'openai_error', $message );
        }
        $parsed = $this->parse_openai_responses_output( $data );
        return [
            'output' => $parsed['output_text'],
            'raw'    => $parsed['raw'],
        ];
    }

    /**
     * Execute a single OpenAI request (Responses or Chat Completions), with one retry and optional
     * fallback to non-reasoning models if the response has no visible output. Never surfaces
     * "reasoning tokens" to the user; uses a generic message on total failure.
     *
     * @param string $model           Model ID.
     * @param string $system_message  System/instructions.
     * @param string $prompt          User prompt.
     * @param string $content         Context content (appended as "Context:\n" + content).
     * @param int    $max_tokens      Max tokens from settings (clamped for Responses API).
     * @param float  $temperature     Temperature.
     * @param string $api_key         OpenAI API key.
     * @param bool   $is_field_helper Whether this is an AskAI field helper request.
     * @param array  $settings        Plugin settings (for filters).
     * @param array  $context_data    Context data (for filters).
     * @param \WP_REST_Request $request Request (for filters).
     * @return array|\WP_Error { output, raw } or WP_Error. output is always a string.
     */
    private function execute_openai_request( $model, $system_message, $prompt, $content, $max_tokens, $temperature, $api_key, $is_field_helper, $settings, $context_data, $request ) {
        $user_content = $prompt . "\n\nContext:\n" . $content;
        $use_responses = $this->openai_uses_responses_api( $model );
        $clamped = $this->openai_clamp_max_output_tokens( $max_tokens );

        $do_one_call = function( $use_model, $user_text, $temp, $is_retry = false ) use ( $use_responses, $system_message, $api_key, $is_field_helper, $settings, $context_data, $request, $clamped ) {
            $use_responses_for_model = $this->openai_uses_responses_api( $use_model );
            if ( $use_responses_for_model ) {
                $payload = $this->build_openai_responses_payload(
                    $use_model,
                    $system_message,
                    $user_text,
                    $clamped,
                    $temp,
                    $is_field_helper ? 'low' : null
                );
            } else {
                $payload = [
                    'model'       => $use_model,
                    'messages'    => [
                        [ 'role' => 'system', 'content' => $system_message ],
                        [ 'role' => 'user', 'content' => $user_text ],
                    ],
                    'max_tokens'  => $clamped,
                    'temperature' => $temp,
                ];
            }
            $payload = apply_filters( 'contextualwp_ai_payload', $payload, $settings, $context_data, $request );
            if ( $use_responses_for_model ) {
                return $this->call_openai_responses_api( $payload, $api_key );
            }
            return $this->call_openai_api( $payload, $api_key );
        };

        $result = $do_one_call( $model, $user_content, $temperature );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $output = isset( $result['output'] ) ? trim( (string) $result['output'] ) : '';
        if ( $output !== '' ) {
            return $result;
        }

        // Retry once with smaller context or lower temperature.
        $content_truncated = strlen( $content ) > 2000 ? substr( $content, 0, 2000 ) . '…(truncated)' : $content;
        $user_content_retry = $prompt . "\n\nContext:\n" . $content_truncated;
        $result_retry = $do_one_call( $model, $user_content_retry, 0.3 );
        if ( ! is_wp_error( $result_retry ) ) {
            $output_retry = isset( $result_retry['output'] ) ? trim( (string) $result_retry['output'] ) : '';
            if ( $output_retry !== '' ) {
                return $result_retry;
            }
        }

        // Fallback to non-reasoning models (only if current model uses Responses API).
        $fallback_models = apply_filters( 'contextualwp_openai_fallback_models', self::OPENAI_FALLBACK_MODELS );
        if ( $use_responses && is_array( $fallback_models ) ) {
            foreach ( $fallback_models as $fallback_model ) {
                if ( $fallback_model === $model ) {
                    continue;
                }
                Utilities::log_debug( [ 'openai_fallback' => $fallback_model, 'original_model' => $model ], 'openai_fallback_model_used' );
                $fallback_result = $do_one_call( $fallback_model, $user_content, 0.5 );
                if ( ! is_wp_error( $fallback_result ) ) {
                    $fallback_output = isset( $fallback_result['output'] ) ? trim( (string) $fallback_result['output'] ) : '';
                    if ( $fallback_output !== '' ) {
                        return $fallback_result;
                    }
                }
            }
        }

        $generic_message = __( "Couldn't generate a response with the current model. Please try again or switch model.", 'contextualwp' );
        return [
            'output' => $generic_message,
            'raw'    => $result['raw'] ?? null,
        ];
    }

    private function call_openai_api( $payload, $api_key ) {
        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( $code !== 200 ) {
            return new \WP_Error( 'openai_error', isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error from OpenAI.' );
        }
        $choice = $data['choices'][0] ?? null;
        if ( ! $choice ) {
            return new \WP_Error( 'openai_error', isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error from OpenAI.' );
        }
        $content = isset( $choice['message']['content'] ) ? (string) $choice['message']['content'] : '';
        $finish_reason = $choice['finish_reason'] ?? '';
        $usage = $data['usage'] ?? [];
        $completion_tokens = (int) ( $usage['completion_tokens'] ?? 0 );
        $reasoning_tokens = (int) ( $usage['completion_tokens_details']['reasoning_tokens'] ?? 0 );
        $is_reasoning_exhausted = (
            trim( $content ) === '' &&
            ( $finish_reason === 'length' || ( $completion_tokens > 0 && $reasoning_tokens >= (int) ( $completion_tokens * 0.9 ) ) )
        );
        // Return empty output so caller (execute_openai_request) can retry/fallback; never surface reasoning message to user.
        if ( $is_reasoning_exhausted ) {
            $content = '';
        }
        return [
            'output' => $content,
            'raw'    => $data,
        ];
    }

    private function call_mistral_api( $payload, $api_key ) {
        $response = wp_remote_post( 'https://api.mistral.ai/v1/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( $code !== 200 || ! isset( $data['choices'][0]['message']['content'] ) ) {
            return new \WP_Error( 'mistral_error', isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error from Mistral.' );
        }
        return [
            'output' => $data['choices'][0]['message']['content'],
            'raw'    => $data,
        ];
    }

    private function call_claude_api( $payload, $api_key ) {
        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( $code !== 200 || ! isset( $data['content'][0]['text'] ) ) {
            return new \WP_Error( 'claude_error', isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error from Claude.' );
        }
        return [
            'output' => $data['content'][0]['text'],
            'raw'    => $data,
        ];
    }
} 