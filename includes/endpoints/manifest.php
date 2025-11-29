<?php
namespace ContextualWP\Endpoints;

use ContextualWP\Helpers\Utilities;

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
                'enum'              => [ 'json', 'yaml' ],
            ],
        ];
    }

    /**
     * Handle the REST API request
     * 
     * @since 0.1.0
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error
     *
     * Note: Only JSON output is supported. YAML is not implemented.
     */
    public function handle_request( $request ) {
        $format = $request->get_param('format');
        if ( strtolower($format) === 'yaml' ) {
            return new \WP_Error(
                'not_implemented',
                __( 'YAML output is not supported. Please use format=json.', 'contextualwp' ),
                [ 'status' => 400 ]
            );
        }
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
            'version'        => defined( 'CONTEXTUALWP_VERSION' ) ? CONTEXTUALWP_VERSION : '0.3.7',
            'endpoints'      => $this->get_endpoints(),
            'formats'        => [ 'markdown', 'plain', 'html' ],
            'context_types'  => apply_filters( 'contextualwp_supported_post_types', [ 'post', 'page' ] ),
            'branding'       => $this->get_branding(),
            'capabilities'   => $this->get_capabilities(),
            'rate_limits'    => $this->get_rate_limits(),
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
