<?php
/**
 * Plugin Name: ContextWP
 * Description: MCP-compatible plugin for exposing context endpoints to AI agents.
 * Version: 0.2.0
 * Author: KWD IT
 * Author URI: https://kwd-it.co.uk
 * Text Domain: contextwp
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants
define( 'CONTEXTWP_VERSION', '0.2.0' );
define( 'CONTEXTWP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONTEXTWP_URL', plugin_dir_url( __FILE__ ) );

// Load plugin
require_once CONTEXTWP_DIR . 'includes/contextwp-init.php';

// Add settings link to plugin row
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), function( $links ) {
    $settings_link = '<a href="' . admin_url( 'options-general.php?page=contextwp-settings' ) . '">' . __( 'Settings', 'contextwp' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
} );
