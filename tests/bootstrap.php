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

require dirname( __DIR__ ) . '/vendor/autoload.php';

// PSR-4 maps ContextualWP\ to includes/; endpoint files live under includes/endpoints/ (lowercase).
require dirname( __DIR__ ) . '/includes/endpoints/generate-context.php';
require dirname( __DIR__ ) . '/includes/helpers/smart-model-selector.php';
