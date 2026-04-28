<?php
/**
 * PHPUnit bootstrap: ABSPATH is required by plugin PHP files to load outside WordPress.
 * Stubs common WP functions used by tested code paths when not running inside WordPress.
 *
 * @package ContextualWP\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

// Some core code paths (sector pack compatibility) depend on this constant.
if ( ! defined( 'CONTEXTUALWP_VERSION' ) ) {
	define( 'CONTEXTUALWP_VERSION', '1.3.1' );
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		unset( $hook, $args );
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook_name, ...$args ) {
		unset( $hook_name, $args );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( (string) $str );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		$url = (string) $url;
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'http://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return max( 0, (int) $maybeint );
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( $min = 0, $max = 0 ) {
		$min = (int) $min;
		$max = (int) $max;
		if ( $max <= $min ) {
			$max = $min + 1;
		}
		return random_int( $min, $max );
	}
}

require dirname( __DIR__ ) . '/vendor/autoload.php';

require dirname( __DIR__ ) . '/includes/SectorPacks/Sector_Pack_Interface.php';
require dirname( __DIR__ ) . '/includes/SectorPacks/Registry.php';
require dirname( __DIR__ ) . '/includes/SectorPacks/functions.php';

// PSR-4 maps ContextualWP\ to includes/; endpoint files live under includes/endpoints/ (lowercase).
require dirname( __DIR__ ) . '/includes/endpoints/generate-context.php';
require dirname( __DIR__ ) . '/includes/helpers/smart-model-selector.php';
require dirname( __DIR__ ) . '/includes/helpers/schema-interpretation.php';
