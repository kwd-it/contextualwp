<?php
/**
 * Plugin Name: ContextualWP
 * Description: MCP-compatible plugin for exposing context endpoints to AI agents.
 * Version: 0.10.0
 * Author: KWD IT
 * Author URI: https://kwd-it.co.uk
 * Text Domain: contextualwp
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants
define( 'CONTEXTUALWP_VERSION', '0.10.0' );
define( 'CONTEXTUALWP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONTEXTUALWP_URL', plugin_dir_url( __FILE__ ) );


// Load plugin
require_once CONTEXTUALWP_DIR . 'includes/contextualwp-init.php';

// Add settings link to plugin row
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), function( $links ) {
    $settings_link = '<a href="' . admin_url( 'options-general.php?page=contextualwp-settings' ) . '">' . __( 'Settings', 'contextualwp' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
} );
