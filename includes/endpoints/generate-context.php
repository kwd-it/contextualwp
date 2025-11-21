<?php
namespace ContextualWP\Endpoints;

use ContextualWP\Helpers\Utilities;
use ContextualWP\Helpers\Smart_Model_Selector;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Generate_Context {
    public function register_route() {
        register_rest_route( 'contextualwp/v1', '/generate_context', [
            'methods'  => 'POST',
            'callback' => [ $this, 'handle_request' ],
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
            'args' => [
                'context_id' => [ 'required' => true, 'type' => 'string' ],
                'prompt'     => [ 'required' => false, 'type' => 'string' ],
                'format'     => [ 'required' => false, 'type' => 'string', 'default' => 'markdown' ],
            ],
        ] );
    }

    public function handle_request( $request ) {
        error_log('ContextualWP DEBUG: context_id=' . var_export($request->get_param('context_id'), true));
        $settings = get_option( 'contextualwp_settings', [] );
        $ai_provider = $settings['ai_provider'] ?? '';
        $api_key     = $settings['api_key'] ?? '';
        $model       = $settings['model'] ?? '';
        $max_tokens  = $settings['max_tokens'] ?? 1024;
        $temperature = $settings['temperature'] ?? 1.0;

        if ( empty( $ai_provider ) || empty( $api_key ) || empty( $model ) ) {
            return new \WP_Error( 'contextualwp_missing_settings', __( 'AI provider, API key, and model must be configured in ContextualWP settings.', 'contextualwp' ), [ 'status' => 400 ] );
        }

        $context_id = $request->get_param( 'context_id' );
        $prompt     = $request->get_param( 'prompt' );
        $format     = $request->get_param( 'format' );

        // Apply smart model selection if enabled
        $provider_slug = $this->map_provider_name( $ai_provider );
        $model = Smart_Model_Selector::select_model( $prompt, '', $provider_slug, $model, $settings );

        // Robust multi-post context aggregation check
        if ( strtolower( trim( $context_id ) ) === 'multi' ) {
            error_log('ContextualWP DEBUG: Entered multi-context block');
            // Fetch recent posts and pages (limit 5 for brevity)
            $query = new \WP_Query([
                'post_type'      => [ 'post', 'page' ],
                'posts_per_page' => 5,
                'post_status'    => 'publish',
                'orderby'        => 'modified',
                'order'          => 'DESC',
            ]);
            $posts = $query->posts;
            $multi_context = [];
            foreach ( $posts as $post ) {
                $acf_fields = [];
                if ( function_exists( 'get_fields' ) ) {
                    try {
                        $acf_fields = get_fields( $post->ID ) ?: [];
                    } catch ( \Exception $e ) {
                        error_log( 'ContextualWP: ACF fields error for post ' . $post->ID . ': ' . $e->getMessage() );
                    }
                }
                $acf_summary = Utilities::acf_summary_markdown($acf_fields);
                $multi_context[] = sprintf(
                    "### %s (%s)\n%s\n%s\n",
                    get_the_title($post),
                    $post->post_type,
                    $acf_summary ? "ACF:\n$acf_summary" : '',
                    wp_trim_words( wp_strip_all_tags($post->post_content), 100, '...' )
                );
            }
            $aggregated_context = implode("\n---\n", $multi_context);
            $content = $aggregated_context;
            $context_data = [
                'id'      => 'multi',
                'content' => $content,
                'meta'    => [
                    'type'    => 'multi',
                    'format'  => $format,
                    'count'   => count($multi_context),
                ],
            ];
            
            // Apply smart model selection for multi-context
            $model = Smart_Model_Selector::select_model( $prompt, $content, $provider_slug, $model, $settings );
            
            // AI call (OpenAI/Mistral/Claude)
            $provider = apply_filters( 'contextualwp_ai_provider', $this->map_provider_name( $ai_provider ), $settings, $context_data, $request );
            $ai_response = null;
            $ai_error = null;
            switch ( $provider ) {
                case 'openai':
                    $ai_payload = apply_filters( 'contextualwp_ai_payload', [
                        'model' => $model,
                        'messages' => [
                            [ 'role' => 'system', 'content' => 'You are a helpful assistant. Use the following context to answer.' ],
                            [ 'role' => 'user', 'content' => $prompt . "\n\nContext:\n" . $content ]
                        ],
                        'max_tokens' => $max_tokens,
                        'temperature' => $temperature,
                    ], $settings, $context_data, $request );
                    \ContextualWP\Helpers\Utilities::log_debug( $ai_payload, 'generate_payload_openai_multi' );
                    $ai_response = $this->call_openai_api( $ai_payload, $api_key );
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
                            [ 'role' => 'system', 'content' => 'You are a helpful assistant. Use the following context to answer.' ],
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
                    $ai_error = 'Unsupported AI provider: ' . esc_html( $ai_provider );
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

        // Parse context_id (e.g., post-123)
        $parsed = Utilities::parse_post_id( $context_id );
        if ( ! $parsed ) {
            return new \WP_Error( 'invalid_context_id', 'Invalid context_id format', [ 'status' => 400 ] );
        }

        $post = get_post( $parsed['id'] );
        if ( ! $post ) {
            return new \WP_Error( 'not_found', 'Context not found', [ 'status' => 404 ] );
        }
        // Ensure the requested type matches the actual post type (case-insensitive).
        if ( strtolower( $post->post_type ) !== strtolower( $parsed['type'] ) ) {
            $valid_types = [ $post->post_type ];
            // Optionally, allow for registered post types with similar names
            $registered_types = get_post_types( [], 'names' );
            if ( in_array( strtolower( $parsed['type'] ), array_map( 'strtolower', $registered_types ), true ) ) {
                // Accept as valid if the type exists, even if not matching this post
                return new \WP_Error( 'invalid_post_type', sprintf( 'Post type mismatch: requested type "%s" does not match actual post type "%s" for ID %d.', $parsed['type'], $post->post_type, $post->ID ), [ 'status' => 400 ] );
            }
            return new \WP_Error( 'invalid_post_type', sprintf( 'Post type "%s" is not valid for post ID %d. Actual type: "%s".', $parsed['type'], $post->ID, $post->post_type ), [ 'status' => 400 ] );
        }
        if ( ! Utilities::can_access_post( $post ) ) {
            return new \WP_Error( 'rest_forbidden', 'Access denied', [ 'status' => 403 ] );
        }

        // Format content (markdown/plain/html)
        $content = $this->format_content( $post, $format );

        // Fetch ACF fields if available
        $acf_fields = [];
        if ( function_exists( 'get_fields' ) ) {
            try {
                $acf_fields = get_fields( $post->ID ) ?: [];
            } catch ( \Exception $e ) {
                error_log( 'ContextualWP: ACF fields error for post ' . $post->ID . ': ' . $e->getMessage() );
            }
        }

        // Allow other plugins to filter/modify the context before sending to AI
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

        // Apply smart model selection for single post context
        $model = Smart_Model_Selector::select_model( $prompt, $content, $provider_slug, $model, $settings );

        // Cache key uses provider, model and context parameters
        $cache_key = apply_filters( 'contextualwp_ai_cache_key', \ContextualWP\Helpers\Utilities::get_cache_key(
            'contextualwp_generate', [
                'provider'   => $ai_provider,
                'model'      => $model,
                'context_id' => $context_id,
                'prompt'     => $prompt,
                'format'     => $format,
                'modified'   => $post->post_modified_gmt,
            ]
        ), $request, $context_data, $settings );
        $cached = wp_cache_get( $cache_key, 'contextualwp' );
        if ( $cached !== false ) {
            return rest_ensure_response( array_merge( $cached, [ 'cached' => true ] ) );
        }

        // Provider extensibility: allow other plugins to register/override AI providers
        $ai_response = null;
        $ai_error = null;
        $provider = apply_filters( 'contextualwp_ai_provider', $this->map_provider_name( $ai_provider ), $settings, $context_data, $request );
        switch ( $provider ) {
            case 'openai':
                $ai_payload = apply_filters( 'contextualwp_ai_payload', [
                    'model' => $model,
                    'messages' => [
                        [ 'role' => 'system', 'content' => 'You are a helpful assistant. Use the following context to answer.' ],
                        [ 'role' => 'user', 'content' => $prompt . "\n\nContext:\n" . $content ]
                    ],
                    'max_tokens' => $max_tokens,
                    'temperature' => $temperature,
                ], $settings, $context_data, $request );
                \ContextualWP\Helpers\Utilities::log_debug( $ai_payload, 'generate_payload_openai' );
                $ai_response = $this->call_openai_api( $ai_payload, $api_key );
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
                        [ 'role' => 'system', 'content' => 'You are a helpful assistant. Use the following context to answer.' ],
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
                $ai_error = 'Unsupported AI provider: ' . esc_html( $ai_provider );
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
     * Map UI provider names to internal provider slugs
     */
    private function map_provider_name( $provider ) {
        $mapping = [
            'OpenAI' => 'openai',
            'Claude' => 'claude',
            'Mistral' => 'mistral',
        ];
        
        return $mapping[ $provider ] ?? strtolower( $provider );
    }

    private function format_content( $post, $format ) {
        $title   = get_the_title( $post );
        $content = apply_filters( 'contextualwp_content_before_format', $post->post_content, $post, $format );
        switch ( $format ) {
            case 'html':
                $output = sprintf(
                    '<h2>%s</h2><div>%s</div>',
                    esc_html( $title ),
                    wp_kses_post( $content )
                );
                break;
            case 'plain':
                $output = sprintf(
                    "%s\n\n%s",
                    $title,
                    wp_strip_all_tags( $content )
                );
                break;
            case 'markdown':
            default:
                $output = sprintf(
                    "## %s\n\n%s\n",
                    $title,
                    wp_strip_all_tags( $content )
                );
                break;
        }
        return apply_filters( 'contextualwp_formatted_content', $output, $post, $format );
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
        if ( $code !== 200 || ! isset( $data['choices'][0]['message']['content'] ) ) {
            return new \WP_Error( 'openai_error', isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error from OpenAI.' );
        }
        return [
            'output' => $data['choices'][0]['message']['content'],
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