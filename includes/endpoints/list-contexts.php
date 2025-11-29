<?php
namespace ContextualWP\Endpoints;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use ContextualWP\Helpers\Utilities;

/**
 * Register the MCP /list_contexts endpoint
 *
 * @package ContextualWP
 * @since 0.1.0
 */
class List_Contexts {

    public function register_route() {
        register_rest_route( 'mcp/v1', '/list_contexts', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_request' ],
            'permission_callback' => [ $this, 'check_permissions' ],
            'args'                => $this->get_args(),
        ]);
    }

    public function check_permissions() {
        return current_user_can( 'read' );
    }

    public function get_args() {
        return [
            'post_type' => [
                'default'           => 'post',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => [ $this, 'validate_post_type' ],
            ],
            'limit' => [
                'default'           => 10,
                'sanitize_callback' => 'absint',
                'validate_callback' => fn( $param ) => $param > 0 && $param <= 100,
            ],
            'page' => [
                'default'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => fn( $param ) => $param > 0,
            ],
            'search' => [
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ],
        ];
    }

    /**
     * Validate post type parameter
     * 
     * Ensures the post type exists and is in the allowed list (same as get_context endpoint).
     * 
     * @since 0.3.8
     * @param string $post_type The post type to validate
     * @return bool|\WP_Error
     */
    public function validate_post_type( $post_type ) {
        if ( empty( $post_type ) ) {
            return new \WP_Error( 'invalid_post_type', 'Post type cannot be empty' );
        }

        // Verify the post type exists in WordPress
        if ( ! post_type_exists( $post_type ) ) {
            return new \WP_Error(
                'post_type_not_found',
                sprintf( 'Post type "%s" does not exist.', $post_type )
            );
        }

        // Get allowed post types (same as get_context endpoint)
        $allowed_post_types = Utilities::get_allowed_post_types();
        
        // Check if the post type is in the allowed list
        if ( ! in_array( $post_type, $allowed_post_types, true ) ) {
            return new \WP_Error(
                'post_type_not_allowed',
                sprintf(
                    'Post type "%s" is not allowed. Supported: %s',
                    $post_type,
                    implode( ', ', $allowed_post_types )
                )
            );
        }

        return true;
    }

    public function handle_request( $request ) {
        $post_type = $request->get_param( 'post_type' );
        $limit     = $request->get_param( 'limit' );
        $page      = $request->get_param( 'page' );
        $search    = $request->get_param( 'search' );

        $args = [
            'post_type'      => $post_type,
            'posts_per_page' => $limit,
            'paged'          => $page,
            'post_status'    => 'publish',
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ];

        if ( ! empty( $search ) ) {
            $args['s'] = $search;
        }

        $query = new \WP_Query( apply_filters( 'contextualwp_list_contexts_query_args', $args, $request ) );

        $contexts = array_map( fn( $post ) => $this->format_context( $post ), $query->posts );

        return rest_ensure_response([
            'contexts'   => $contexts,
            'pagination' => [
                'current_page' => $page,
                'total_pages'  => $query->max_num_pages,
                'total_items'  => $query->found_posts,
                'per_page'     => $limit,
            ],
        ]);
    }

    private function format_context( $post ) {
        $excerpt = get_the_excerpt( $post ) ?: wp_trim_words( $post->post_content, 20, '...' );

        return [
            'id'           => sprintf('%s-%d', $post->post_type, $post->ID),
            'title'        => get_the_title( $post ),
            'description'  => $excerpt,
            'last_updated' => get_post_modified_time( 'c', true, $post ),
        ];
    }
}
