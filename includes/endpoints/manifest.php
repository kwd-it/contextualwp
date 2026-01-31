<?php
namespace ContextualWP\Endpoints;

use ContextualWP\Helpers\Utilities;
use ContextualWP\Helpers\Providers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MCP Manifest Endpoint
 * 
 * Returns metadata about this context provider for AI agents.
 * 
 * @package ContextualWP
 * @since 0.1.0
 */
class Manifest {

    /**
     * Register the REST API route
     * 
     * @since 0.1.0
     */
    public function register_route() {
        register_rest_route( 'mcp/v1', '/manifest', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_request' ],
            'permission_callback' => [ $this, 'check_permissions' ],
            'args'                => $this->get_args(),
        ] );
    }

    /**
     * Check if the request is allowed
     * 
     * @since 0.1.0
     * @return bool|\WP_Error
     */
    public function check_permissions() {
        // Allow public access but with rate limiting
        if ( $this->is_rate_limited() ) {
            return new \WP_Error(
                'rate_limit_exceeded',
                __( 'Too many requests. Please try again later.', 'contextualwp' ),
                [ 'status' => 429 ]
            );
        }
        
        return true;
    }

    /**
     * Get the arguments for the endpoint
     * 
     * @since 0.1.0
     * @return array
     */
    public function get_args() {
        return [
            'format' => [
                'default'           => 'json',
                'sanitize_callback' => 'sanitize_text_field',
                'enum'              => [ 'json' ],
            ],
        ];
    }

    /**
     * Handle the REST API request
     * 
     * @since 0.1.0
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_request( $request ) {
        try {
            // Check for cached response
            $cache_key = \ContextualWP\Helpers\Utilities::get_cache_key( 'contextualwp_manifest', $request->get_params() );
            $cached    = wp_cache_get( $cache_key, 'contextualwp' );
            
            if ( $cached !== false ) {
                return rest_ensure_response( $cached );
            }

            $manifest = $this->generate_manifest( $request );
            
            // Cache the response for 1 hour
            wp_cache_set( $cache_key, $manifest, 'contextualwp', HOUR_IN_SECONDS );
            
            return rest_ensure_response( $manifest );
            
        } catch ( \Exception $e ) {
            \ContextualWP\Helpers\Utilities::log_debug( $e->getMessage(), 'manifest_error' );
            return new \WP_Error(
                'manifest_generation_failed',
                __( 'Failed to generate manifest.', 'contextualwp' ),
                [ 'status' => 500 ]
            );
        }
    }

    /**
     * Generate the manifest data
     * 
     * @since 0.1.0
     * @param \WP_REST_Request $request The request object
     * @return array
     */
    private function generate_manifest( $request ) {
        $site_name = get_bloginfo( 'name' );
        $site_description = get_bloginfo( 'description' );
        
        // Ensure we have valid data
        if ( empty( $site_name ) ) {
            $site_name = __( 'WordPress Site', 'contextualwp' );
        }
        
        if ( empty( $site_description ) ) {
            $site_description = __( 'A WordPress site with ContextualWP integration', 'contextualwp' );
        }

        $manifest = apply_filters( 'contextualwp_manifest', [
            'name'           => $site_name . ' – ContextualWP',
            'description'    => $site_description,
            'version'        => defined( 'CONTEXTUALWP_VERSION' ) ? CONTEXTUALWP_VERSION : '0.6.0',
            'endpoints'      => $this->get_endpoints(),
            'formats'        => [ 'markdown', 'plain', 'html' ],
            'context_types'  => apply_filters( 'contextualwp_supported_post_types', [ 'post', 'page' ] ),
            'providers'      => Providers::list(),
            'branding'       => $this->get_branding(),
            'capabilities'   => $this->get_capabilities(),
            'rate_limits'    => $this->get_rate_limits(),
            'schema'         => $this->get_schema(),
        ] );

        return $manifest;
    }

    /**
     * Get the available endpoints
     * 
     * @since 0.1.0
     * @return array
     */
    private function get_endpoints() {
        return [
            'list_contexts' => [
                'url'    => rest_url( 'mcp/v1/list_contexts' ),
                'method' => 'GET',
                'description' => __( 'List available contexts', 'contextualwp' ),
            ],
            'get_context' => [
                'url'    => rest_url( 'mcp/v1/get_context' ),
                'method' => 'GET',
                'description' => __( 'Get specific context content', 'contextualwp' ),
            ],
        ];
    }

    /**
     * Get branding information
     * 
     * @since 0.1.0
     * @return array
     */
    private function get_branding() {
        $plugin_url = defined( 'CONTEXTUALWP_URL' ) ? CONTEXTUALWP_URL : '';
        $logo_url   = $plugin_url ? $plugin_url . 'admin/assets/logo.png' : '';
        
        return [
            'plugin_url' => apply_filters( 'contextualwp_plugin_url', $plugin_url ),
            'logo_url'   => apply_filters( 'contextualwp_logo_url', $logo_url ),
            'author'     => apply_filters( 'contextualwp_author', __( 'ContextualWP Team', 'contextualwp' ) ),
        ];
    }

    /**
     * Get capability information
     * 
     * @since 0.1.0
     * @return array
     */
    private function get_capabilities() {
        return [
            'public_access' => true,
            'authentication_required' => false,
            'rate_limited' => true,
            'caching_enabled' => true,
        ];
    }

    /**
     * Get rate limit information
     * 
     * @since 0.1.0
     * @return array
     */
    private function get_rate_limits() {
        return [
            'requests_per_minute' => apply_filters( 'contextualwp_rate_limit_per_minute', 60 ),
            'requests_per_hour'   => apply_filters( 'contextualwp_rate_limit_per_hour', 1000 ),
        ];
    }

    /**
     * Get schema (post types, taxonomies, and relationships metadata)
     *
     * Uses WordPress core APIs. Returns simple metadata only—no content or field values.
     * Filter hooks allow customisation of schema and relationships.
     *
     * Relationships are provided via the contextualwp_manifest_schema_relationships filter.
     * Each relationship should have: source_type, target_type, and description.
     *
     * @since 0.6.0
     * @return array
     */
    private function get_schema() {
        $post_types = apply_filters( 'contextualwp_manifest_schema_post_types', $this->get_schema_post_types() );
        $taxonomies = apply_filters( 'contextualwp_manifest_schema_taxonomies', $this->get_schema_taxonomies() );
        $relationships = apply_filters( 'contextualwp_manifest_schema_relationships', [] );

        $schema = [
            'core_field_count' => $this->count_core_post_fields(),
            'post_types'       => $post_types,
            'taxonomies'       => $taxonomies,
            'relationships'    => $relationships,
        ];

        return apply_filters( 'contextualwp_manifest_schema', $schema );
    }

    /**
     * Get public post types as simple metadata
     *
     * @since 0.6.0
     * @return array
     */
    private function get_schema_post_types() {
        $post_types = [];
        $public_post_types = get_post_types( [ 'public' => true ], 'objects' );

        foreach ( $public_post_types as $post_type ) {
            $entry = [
                'name'         => $post_type->name,
                'label'        => $post_type->label,
                'description'  => $post_type->description ?? '',
                'hierarchical' => (bool) $post_type->hierarchical,
                'rest_base'    => $post_type->rest_base ?? '',
                'taxonomies'   => get_object_taxonomies( $post_type->name, 'names' ),
            ];
            $field_sources = $this->get_post_type_field_sources( $post_type->name );
            if ( ! empty( $field_sources ) ) {
                $entry['field_sources'] = $field_sources;
            }
            $post_types[] = $entry;
        }

        return $post_types;
    }

    /**
     * Count core wp_posts table columns (same for all post types).
     *
     * @since 0.6.0
     * @return int
     */
    private function count_core_post_fields() {
        global $wpdb;
        $columns = $wpdb->get_col( "DESCRIBE {$wpdb->posts}" );
        return is_array( $columns ) ? count( $columns ) : 0;
    }

    /**
     * Get field_sources summary (counts only) for a post type.
     * Returns acf_fields when ACF is active; empty array otherwise.
     * Core field count is at schema.core_field_count (same for all post types).
     *
     * @since 0.6.0
     * @param string $post_type_name Post type name.
     * @return array{acf_fields?: int}
     */
    private function get_post_type_field_sources( $post_type_name ) {
        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
            return [];
        }

        $acf_count = 0;
        $groups = acf_get_field_groups( [ 'post_type' => $post_type_name ] );
        foreach ( $groups as $group ) {
            $fields = acf_get_fields( $group );
            $acf_count += is_array( $fields ) ? count( $fields ) : 0;
        }

        return [ 'acf_fields' => $acf_count ];
    }

    /**
     * Get public taxonomies as simple metadata
     *
     * @since 0.6.0
     * @return array
     */
    private function get_schema_taxonomies() {
        $taxonomies = [];
        $public_taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );

        foreach ( $public_taxonomies as $taxonomy ) {
            $taxonomies[] = [
                'name'         => $taxonomy->name,
                'label'        => $taxonomy->label,
                'description'  => $taxonomy->description ?? '',
                'hierarchical' => (bool) $taxonomy->hierarchical,
                'rest_base'    => $taxonomy->rest_base ?? '',
                'object_types' => (array) $taxonomy->object_type,
            ];
        }

        return $taxonomies;
    }

    /**
     * Check if the request is rate limited
     * 
     * @since 0.1.0
     * @return bool
     */
    private function is_rate_limited() {
        $ip = \ContextualWP\Helpers\Utilities::get_client_ip();
        $key = 'contextualwp_rate_limit_' . md5( $ip );
        $limit_per_minute = apply_filters( 'contextualwp_rate_limit_per_minute', 60 );
        
        return \ContextualWP\Helpers\Utilities::is_rate_limited( $key, $limit_per_minute, 60 );
    }
}
