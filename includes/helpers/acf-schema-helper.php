<?php
namespace ContextualWP\Helpers;

use ContextualWP\Endpoints\ACF_Schema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper for AskAI and other server-side code to access ACF schema metadata.
 * Fetches schema (cached) when AskAI is invoked and provides field lookup by name.
 * Reuses the same cache as /contextualwp/v1/acf_schema.
 *
 * @package ContextualWP
 * @since 0.6.0
 */
class ACF_Schema_Helper {

    /**
     * Get the full ACF schema (cached). Uses same cache as /acf_schema endpoint.
     * Fails gracefully if schema is unavailable.
     *
     * @return array|null Schema with field_groups, generated_at; null on failure.
     */
    public static function get_schema() {
        try {
            $schema = ( new ACF_Schema() )->get_schema_data();
            if ( ! is_array( $schema ) || ! isset( $schema['field_groups'] ) ) {
                return null;
            }
            return $schema;
        } catch ( \Exception $e ) {
            Utilities::log_debug( $e->getMessage(), 'acf_schema_helper' );
            return null;
        }
    }

    /**
     * Look up a field's schema entry by field name.
     *
     * @param string $field_name The ACF field name (e.g. 'subtitle', 'featured_image').
     * @return array|null The editor-safe field schema entry, or null if not found.
     */
    public static function get_field_by_name( $field_name ) {
        $schema = self::get_schema();
        if ( $schema === null ) {
            return null;
        }
        $field_name = is_string( $field_name ) ? trim( $field_name ) : '';
        if ( $field_name === '' ) {
            return null;
        }
        $field_groups = $schema['field_groups'] ?? [];
        foreach ( $field_groups as $group ) {
            $fields = $group['fields'] ?? [];
            foreach ( $fields as $field ) {
                if ( isset( $field['name'] ) && $field['name'] === $field_name ) {
                    return $field;
                }
            }
        }
        return null;
    }
}
