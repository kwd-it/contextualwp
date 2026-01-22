<?php
namespace ContextualWP\Endpoints;

use ContextualWP\Helpers\Utilities;
use ContextualWP\Helpers\Providers;
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
        $context_id = $request->get_param( 'context_id' );
        $prompt     = $request->get_param( 'prompt' );
        $format     = $request->get_param( 'format' );

        // Schema-aware structure answers for floating admin chat (multi): no AI call.
        if ( strtolower( trim( (string) $context_id ) ) === 'multi' && $this->is_structure_question( $prompt ) ) {
            return $this->handle_structure_question( $request );
        }

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

        // Robust multi-post context aggregation check
        if ( strtolower( trim( $context_id ) ) === 'multi' ) {
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
            $provider = apply_filters( 'contextualwp_ai_provider', Providers::normalize( $ai_provider ), $settings, $context_data, $request );
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
        $content = Utilities::format_content( $post, $format );

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
                'provider'   => Providers::normalize( $ai_provider ),
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
        $provider = apply_filters( 'contextualwp_ai_provider', Providers::normalize( $ai_provider ), $settings, $context_data, $request );
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
     * Handle structure questions: fetch schema, build plain-text answer, return chat-shaped response.
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
     * Extract post type slug from prompt when asking for ACF (e.g. "ACF for plots", "field groups for post type developments").
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
        foreach ( $custom_pt_slugs as $slug ) {
            if ( $slug === '' ) {
                continue;
            }
            if ( preg_match( '/\bfor\s+(?:post\s+type\s+)?' . preg_quote( $slug, '/' ) . '\b/i', $lower ) ) {
                return $slug;
            }
            if ( preg_match( '/\b' . preg_quote( $slug, '/' ) . '\b.*\b(?:acf|field\s+group|fields)\b/i', $lower ) ) {
                return $slug;
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
     * Filter ACF field groups to those that apply to the given post type.
     *
     * @param array  $groups Schema acf_field_groups.
     * @param string $post_type Post type slug.
     * @return array Filtered groups with 'field_count' key added.
     */
    private function acf_groups_for_post_type( array $groups, $post_type ) {
        $filtered = [];
        foreach ( $groups as $g ) {
            $loc = isset( $g['location'] ) ? $g['location'] : [];
            $pts = $this->post_types_from_acf_location( $loc );
            if ( in_array( $post_type, $pts, true ) ) {
                $count = isset( $g['fields'] ) && is_array( $g['fields'] ) ? count( $g['fields'] ) : 0;
                $g['field_count'] = $count;
                $filtered[] = $g;
            }
        }
        usort( $filtered, function ( $a, $b ) {
            return ( $b['field_count'] ?? 0 ) - ( $a['field_count'] ?? 0 );
        } );
        return $filtered;
    }

    /**
     * Build short plain-text structure answer from schema (custom PTs, custom taxonomies, optional ACF).
     * Format: headings, bullet lists. Excludes built-in PTs/taxonomies unless explicitly requested.
     * ACF included only when acf_requested(); by-PT or top summary + instruction.
     *
     * @param array  $schema From Schema::get_schema_data().
     * @param string $prompt User prompt.
     * @return string
     */
    private function build_structure_answer( array $schema, $prompt = '' ) {
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

        $custom_pt_slugs = array_map( function ( $p ) {
            return isset( $p['slug'] ) ? $p['slug'] : '';
        }, $custom_pt );
        $custom_pt_slugs = array_filter( $custom_pt_slugs );

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
        $show_acf = $this->acf_requested( $prompt ) && ! empty( $acf_groups );
        if ( $show_acf ) {
            $requested_pt = $this->extract_requested_post_type_for_acf( $prompt, $custom_pt_slugs );
            if ( $requested_pt !== null ) {
                $for_pt = $this->acf_groups_for_post_type( $acf_groups, $requested_pt );
                $top = array_slice( $for_pt, 0, 10 );
                $n = count( $top );
                $lines[] = '';
                $lines[] = 'ACF field groups for \'' . $requested_pt . '\' (top ' . $n . ' by field count)';
                $lines[] = '';
                if ( ! empty( $top ) ) {
                    foreach ( $top as $g ) {
                        $title = isset( $g['title'] ) ? $g['title'] : '';
                        $n = isset( $g['field_count'] ) ? (int) $g['field_count'] : 0;
                        $lines[] = '- ' . $title . ' — ' . $n . ' field(s)';
                    }
                } else {
                    $lines[] = '- No groups target this post type.';
                }
            } else {
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
        }

        $lines[] = '';
        $gen = isset( $schema['generated_at'] ) ? $schema['generated_at'] : '-';
        $lines[] = 'Source: schema (generated at ' . $gen . ').';
        return implode( "\n", $lines );
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