<?php
/**
 * Plugin Name: ContextualWP
 * Description: MCP-compatible plugin for exposing context endpoints to AI agents.
 * Version: 1.1.0
 * Author: KWD IT
 * Author URI: https://kwd-it.co.uk
 * Text Domain: contextualwp
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Version: single literal in the plugin header above; constant is derived for runtime use.
if ( ! function_exists( 'get_file_data' ) ) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
$contextualwp_file_headers = get_file_data( __FILE__, array( 'Version' => 'Version' ), 'plugin' );
$contextualwp_version      = isset( $contextualwp_file_headers['Version'] ) ? trim( (string) $contextualwp_file_headers['Version'] ) : '';
define( 'CONTEXTUALWP_VERSION', $contextualwp_version !== '' ? $contextualwp_version : '0.0.0' );
unset( $contextualwp_file_headers, $contextualwp_version );

// Define plugin constants
define( 'CONTEXTUALWP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONTEXTUALWP_URL', plugin_dir_url( __FILE__ ) );


// Load plugin
require_once CONTEXTUALWP_DIR . 'includes/contextualwp-init.php';

// Add settings link to plugin row
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), function( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=contextualwp-settings' ) . '">' . __( 'Settings', 'contextualwp' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
} );
