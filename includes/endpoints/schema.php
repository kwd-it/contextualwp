<?php
namespace ContextualWP\Endpoints;

use ContextualWP\Helpers\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schema Endpoint
 * 
 * Returns schema information about the site including post types, taxonomies, and ACF fields.
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
        // Temporary debug logging
        $user_id = get_current_user_id();
        $can_manage = current_user_can( 'manage_options' );
        error_log( sprintf( 
            '[ContextualWP Schema Auth] user_id=%d, can_manage_options=%s, is_user_logged_in=%s', 
            $user_id, 
            $can_manage ? 'true' : 'false',
            is_user_logged_in() ? 'true' : 'false'
        ) );
        
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
                'version' => defined( 'CONTEXTUALWP_VERSION' ) ? CONTEXTUALWP_VERSION : '0.4.0',
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

        return apply_filters( 'contextualwp_schema', $schema );
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