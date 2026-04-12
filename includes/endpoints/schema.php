<?php
namespace ContextualWP\Endpoints;

use ContextualWP\Helpers\Schema_Interpretation;
use ContextualWP\Helpers\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schema Endpoint
 *
 * Returns site structure (post types, taxonomies, optional ACF group metadata). The optional
 * `interpretation` object is an AI-facing layer (summaries, relationship hints, ACF JSON-LD capability
 * flags)—not a substitute for Schema.org JSON-LD, which ACF 6.8+ may emit on the front end when enabled.
 *
 * @package ContextualWP
 * @since 0.4.0
 */
class Schema {

    /**
     * Register the REST API route
     * 
     * @since 0.4.0
     */
    public function register_route() {
        register_rest_route( 'contextualwp/v1', '/schema', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_request' ],
            'permission_callback' => [ $this, 'check_permissions' ],
        ] );
    }

    /**
     * Check if the request is allowed
     * 
     * WordPress REST API automatically returns:
     * - 401 (Unauthorized) if user is not logged in
     * - 403 (Forbidden) if user is logged in but doesn't have the capability
     * 
     * @since 0.4.0
     * @return bool
     */
    public function check_permissions() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Handle the REST API request
     * 
     * @since 0.4.0
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_request( $request ) {
        try {
            // Check for cached response
            $cache_key = Utilities::get_cache_key( 'contextualwp_schema', [] );
            $cached    = wp_cache_get( $cache_key, 'contextualwp' );
            
            if ( $cached !== false ) {
                return rest_ensure_response( $cached );
            }

            $schema = $this->generate_schema();
            
            // Cache the response (default 5 minutes, filterable)
            $cache_ttl = apply_filters( 'contextualwp_schema_cache_ttl', 5 * MINUTE_IN_SECONDS );
            wp_cache_set( $cache_key, $schema, 'contextualwp', $cache_ttl );
            
            return rest_ensure_response( $schema );
            
        } catch ( \Exception $e ) {
            Utilities::log_debug( $e->getMessage(), 'schema_error' );
            return new \WP_Error(
                'schema_generation_failed',
                __( 'Failed to generate schema.', 'contextualwp' ),
                [ 'status' => 500 ]
            );
        }
    }

    /**
     * Return schema data (cached). Used by generate-context for structure answers.
     * Reuses existing schema cache/TTL.
     *
     * @since 0.4.0
     * @return array Schema array (post_types, taxonomies, acf_field_groups, generated_at, etc.)
     */
    public function get_schema_data() {
        $cache_key = Utilities::get_cache_key( 'contextualwp_schema', [] );
        $cached    = wp_cache_get( $cache_key, 'contextualwp' );
        if ( $cached !== false ) {
            return $cached;
        }
        $schema = $this->generate_schema();
        $cache_ttl = apply_filters( 'contextualwp_schema_cache_ttl', 5 * MINUTE_IN_SECONDS );
        wp_cache_set( $cache_key, $schema, 'contextualwp', $cache_ttl );
        return $schema;
    }

    /**
     * Generate the schema data
     * 
     * @since 0.4.0
     * @return array
     */
    private function generate_schema() {
        global $wp_version;

        $schema = [
            'plugin' => [
                'name'    => 'ContextualWP',
                'version' => CONTEXTUALWP_VERSION,
            ],
            'site' => [
                'home_url'  => home_url(),
                'wp_version' => $wp_version,
            ],
            'post_types' => $this->get_post_types(),
            'taxonomies' => $this->get_taxonomies(),
            'generated_at' => current_time( 'c', true ),
        ];

        // Add ACF field groups if ACF is active
        if ( function_exists( 'acf_get_field_groups' ) ) {
            $schema['acf_field_groups'] = $this->get_acf_field_groups();
        }

        $schema = apply_filters( 'contextualwp_schema', $schema );

        /**
         * Interpretation layer: AI-oriented summaries and relationship hints (never raw Schema.org JSON-LD).
         *
         * Core supplies a default `contextualwp` key via Schema_Interpretation::build() when ACF is active,
         * manifest relationships are declared, or sector packs are registered. Extensions merge on top by
         * returning their own top-level keys; avoid overwriting `contextualwp` unless intentionally replacing core hints.
         *
         * @param array<string, mixed> $extension_keys Empty array, or pack-specific keys to merge with the core layer.
         * @param array<string, mixed> $schema         Full schema after contextualwp_schema.
         */
        $base_interpretation = Schema_Interpretation::build( $schema );
        $extension_keys    = apply_filters( 'contextualwp_schema_interpretation', [], $schema );
        if ( ! is_array( $extension_keys ) ) {
            $extension_keys = [];
        }
        $interpretation = $extension_keys !== []
            ? array_merge( $base_interpretation, $extension_keys )
            : $base_interpretation;

        if ( is_array( $interpretation ) && $interpretation !== [] ) {
            $schema['interpretation'] = $interpretation;
        }

        return $schema;
    }

    /**
     * Get public post types with their details
     * 
     * @since 0.4.0
     * @return array
     */
    private function get_post_types() {
        $post_types = [];
        $public_post_types = get_post_types( [ 'public' => true ], 'objects' );

        foreach ( $public_post_types as $post_type ) {
            // Get supported features
            $supports = [];
            $post_type_supports = get_all_post_type_supports( $post_type->name );
            foreach ( $post_type_supports as $feature => $args ) {
                $supports[] = $feature;
            }

            // Get taxonomies for this post type
            $taxonomies = get_object_taxonomies( $post_type->name, 'names' );

            $post_types[] = [
                'slug'      => $post_type->name,
                'label'     => $post_type->label,
                'supports'  => $supports,
                'taxonomies' => $taxonomies,
            ];
        }

        return $post_types;
    }

    /**
     * Get taxonomies with their details
     * 
     * @since 0.4.0
     * @return array
     */
    private function get_taxonomies() {
        $taxonomies = [];
        $public_taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );

        foreach ( $public_taxonomies as $taxonomy ) {
            $taxonomy_obj = get_taxonomy( $taxonomy->name );
            if ( ! $taxonomy_obj ) {
                continue;
            }

            $taxonomies[] = [
                'slug'        => $taxonomy->name,
                'label'       => $taxonomy_obj->label,
                'object_types' => $taxonomy_obj->object_type,
            ];
        }

        return $taxonomies;
    }

    /**
     * Get ACF field groups and their fields
     * 
     * @since 0.4.0
     * @return array
     */
    private function get_acf_field_groups() {
        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
            return [];
        }

        $field_groups = [];
        $groups = acf_get_field_groups();

        foreach ( $groups as $group ) {
            $fields = acf_get_fields( $group );
            $field_data = [];

            if ( $fields ) {
                foreach ( $fields as $field ) {
                    $field_info = [
                        'label' => $field['label'] ?? '',
                        'name'  => $field['name'] ?? '',
                        'key'   => $field['key'] ?? '',
                        'type'  => $field['type'] ?? '',
                    ];

                    // For post-object and relationship fields, include allowed post types if available
                    if ( in_array( $field['type'] ?? '', [ 'post_object', 'relationship' ], true ) ) {
                        if ( isset( $field['post_type'] ) && ! empty( $field['post_type'] ) ) {
                            $field_info['post_type'] = $field['post_type'];
                        }
                    }

                    $field_data[] = $field_info;
                }
            }

            $field_groups[] = [
                'title'    => $group['title'] ?? '',
                'key'      => $group['key'] ?? '',
                'location' => $group['location'] ?? [],
                'fields'   => $field_data,
            ];
        }

        return $field_groups;
    }
}