<?php
namespace ContextualWP\Endpoints;

use ContextualWP\Helpers\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Site Diagnostics Endpoint
 *
 * Returns a structured JSON snapshot for the internal chatbot: WP version, PHP version,
 * active theme (and parent if any), and active plugins with versions.
 * Admin-only; no secrets, options, or API keys.
 *
 * @package ContextualWP
 * @since 0.6.0
 */
class Site_Diagnostics {

    /**
     * Register the REST API route
     *
     * @since 0.6.0
     */
    public function register_route() {
        register_rest_route( 'mcp/v1', '/site_diagnostics', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_request' ],
            'permission_callback' => [ $this, 'check_permissions' ],
        ] );
    }

    /**
     * Check if the request is allowed (admin-only)
     *
     * @since 0.6.0
     * @return bool|\WP_Error
     */
    public function check_permissions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to access site diagnostics.', 'contextualwp' ),
                [ 'status' => 403 ]
            );
        }

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
     * Handle the REST API request
     *
     * @since 0.6.0
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_request( $request ) {
        try {
            $cache_key = Utilities::get_cache_key( 'contextualwp_site_diagnostics', [] );
            $cached    = wp_cache_get( $cache_key, 'contextualwp' );

            if ( $cached !== false ) {
                return rest_ensure_response( $cached );
            }

            $diagnostics = $this->generate_diagnostics();

            $cache_ttl = apply_filters( 'contextualwp_site_diagnostics_cache_ttl', 5 * MINUTE_IN_SECONDS );
            wp_cache_set( $cache_key, $diagnostics, 'contextualwp', $cache_ttl );

            return rest_ensure_response( $diagnostics );

        } catch ( \Exception $e ) {
            Utilities::log_debug( $e->getMessage(), 'site_diagnostics_error' );
            return new \WP_Error(
                'site_diagnostics_failed',
                __( 'Failed to generate site diagnostics.', 'contextualwp' ),
                [ 'status' => 500 ]
            );
        }
    }

    /**
     * Generate diagnostics data (no secrets, options, or API keys)
     *
     * @since 0.6.0
     * @return array
     */
    private function generate_diagnostics() {
        global $wp_version;

        $theme = wp_get_theme();
        $parent = $theme->parent();

        $diagnostics = [
            'site'         => [
                'home_url'    => home_url(),
                'site_url'    => site_url(),
                'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'unknown',
            ],
            'wp_version'   => $wp_version,
            'php_version'  => PHP_VERSION,
            'theme'        => [
                'name'    => $theme->get( 'Name' ),
                'version' => $theme->get( 'Version' ),
                'slug'    => $theme->get_stylesheet(),
            ],
            'active_plugins' => $this->get_active_plugins(),
            'generated_at'  => current_time( 'c', true ),
        ];

        if ( $parent ) {
            $diagnostics['theme']['parent'] = [
                'name'    => $parent->get( 'Name' ),
                'version' => $parent->get( 'Version' ),
                'slug'    => $parent->get_stylesheet(),
            ];
        }

        return apply_filters( 'contextualwp_site_diagnostics', $diagnostics );
    }

    /**
     * Get active plugins with name and version only (no paths, no options)
     *
     * @since 0.6.0
     * @return array
     */
    private function get_active_plugins() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins   = get_plugins();
        $active_slugs  = (array) get_option( 'active_plugins', [] );
        $active_plugins = [];

        foreach ( $active_slugs as $plugin_file ) {
            if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
                continue;
            }
            $plugin = $all_plugins[ $plugin_file ];
            $active_plugins[] = [
                'name'    => $plugin['Name'] ?? '',
                'version' => $plugin['Version'] ?? '',
            ];
        }

        return $active_plugins;
    }

    /**
     * Check if the request is rate limited
     *
     * @since 0.6.0
     * @return bool
     */
    private function is_rate_limited() {
        $ip = Utilities::get_client_ip();
        $key = 'contextualwp_site_diagnostics_rate_' . md5( $ip );
        $limit_per_minute = apply_filters( 'contextualwp_rate_limit_per_minute', 60 );

        return Utilities::is_rate_limited( $key, $limit_per_minute, 60 );
    }
}
