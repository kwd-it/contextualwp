<?php
namespace ContextualWP\Endpoints;

use ContextualWP\Helpers\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ACF Schema Endpoint
 *
 * Returns editor-safe ACF field metadata derived from ACF's loaded field definitions
 * (local JSON + DB). Used by AskAI field helper and other editor-facing AI features.
 *
 * Does NOT expose: field keys, internal IDs, file paths, or raw ACF JSON.
 *
 * @package ContextualWP
 * @since 0.6.0
 */
class ACF_Schema {

    /**
     * Register the REST API route
     *
     * @since 0.6.0
     */
    public function register_route() {
        register_rest_route( 'contextualwp/v1', '/acf_schema', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_request' ],
            'permission_callback' => [ $this, 'check_permissions' ],
        ] );
    }

    /**
     * Check if the request is allowed
     *
     * @since 0.6.0
     * @return bool
     */
    public function check_permissions() {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Handle the REST API request
     *
     * @since 0.6.0
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_request( $request ) {
        $schema = $this->get_schema_data();
        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
            $schema['message'] = __( 'ACF is not active.', 'contextualwp' );
        }
        return rest_ensure_response( $schema );
    }

    /**
     * Return ACF schema data (cached). Used by AskAI helper and other server-side code.
     * Reuses existing cache/TTL. Fails gracefully if ACF is inactive or generation fails.
     *
     * @since 0.6.0
     * @return array Schema with field_groups, generated_at.
     */
    public function get_schema_data() {
        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
            return [
                'field_groups' => [],
                'generated_at' => current_time( 'c', true ),
            ];
        }

        $cache_key = Utilities::get_cache_key( 'contextualwp_acf_schema', [] );
        $cached    = wp_cache_get( $cache_key, 'contextualwp' );

        if ( $cached !== false ) {
            return $cached;
        }

        try {
            $schema = $this->generate_schema();
            $cache_ttl = apply_filters( 'contextualwp_acf_schema_cache_ttl', 5 * MINUTE_IN_SECONDS );
            wp_cache_set( $cache_key, $schema, 'contextualwp', $cache_ttl );
            return $schema;
        } catch ( \Exception $e ) {
            Utilities::log_debug( $e->getMessage(), 'acf_schema_error' );
            return [
                'field_groups' => [],
                'generated_at' => current_time( 'c', true ),
            ];
        }
    }

    /**
     * Generate editor-safe ACF schema from loaded field definitions
     *
     * @since 0.6.0
     * @return array
     */
    private function generate_schema() {
        $groups = acf_get_field_groups();

        $field_groups = [];
        foreach ( $groups as $group ) {
            $fields = acf_get_fields( $group );
            $field_data = [];
            $key_to_label = [];

            if ( $fields ) {
                $key_to_type = [];
                foreach ( $fields as $field ) {
                    $label = $field['label'] ?? '';
                    $key = $field['key'] ?? '';
                    if ( $key && $label ) {
                        $key_to_label[ $key ] = $label;
                    }
                    if ( $key ) {
                        $key_to_type[ $key ] = strtolower( (string) ( $field['type'] ?? '' ) );
                    }
                }

                foreach ( $fields as $field ) {
                    $field_data[] = $this->sanitize_field( $field, $fields, $key_to_label, $key_to_type );
                }
            }

            $field_groups[] = [
                'title'             => $group['title'] ?? '',
                'location_summary'   => $this->summarize_location( $group['location'] ?? [] ),
                'fields'            => $field_data,
            ];
        }

        return [
            'field_groups' => apply_filters( 'contextualwp_acf_schema_field_groups', $field_groups ),
            'generated_at' => current_time( 'c', true ),
        ];
    }

    /**
     * Sanitize a single field for editor-safe output
     *
     * @param array $field Raw ACF field
     * @param array $sibling_fields All fields in the same group
     * @param array $key_to_label Map of field key to label
     * @param array $key_to_type Map of field key to type
     * @return array
     */
    private function sanitize_field( $field, $sibling_fields, $key_to_label, $key_to_type = [] ) {
        $out = [
            'label'       => $field['label'] ?? '',
            'name'        => $field['name'] ?? '',
            'type'        => $field['type'] ?? '',
            'instructions'=> isset( $field['instructions'] ) && $field['instructions'] !== '' ? (string) $field['instructions'] : null,
            'required'    => ! empty( $field['required'] ),
            'default'     => isset( $field['default_value'] ) && $field['default_value'] !== '' ? (string) $field['default_value'] : null,
        ];

        $choices = $field['choices'] ?? null;
        if ( is_array( $choices ) && ! empty( $choices ) ) {
            $out['choices'] = $choices;
        }

        $cond = $field['conditional_logic'] ?? null;
        if ( is_array( $cond ) && ! empty( $cond ) ) {
            $out['conditional_logic_summary'] = $this->format_conditional_logic( $cond, $key_to_label, $key_to_type );
        }

        $key = $field['key'] ?? '';
        if ( $key ) {
            $controlled = $this->collect_controlled_fields( $key, $sibling_fields );
            if ( $controlled !== '' ) {
                $out['controlled_fields_summary'] = $controlled;
            }
        }

        return $out;
    }

    /**
     * Format conditional logic as human-readable summary (editor-friendly)
     *
     * @param array $conditional_logic ACF conditional_logic array
     * @param array $key_to_label Map of field key to label
     * @param array $key_to_type Map of field key to type
     * @return string
     */
    private function format_conditional_logic( $conditional_logic, $key_to_label, $key_to_type = [] ) {
        if ( ! is_array( $conditional_logic ) ) {
            return '';
        }

        $parts = [];
        foreach ( $conditional_logic as $group ) {
            if ( ! is_array( $group ) ) {
                continue;
            }
            $group_parts = [];
            foreach ( $group as $rule ) {
                if ( ! is_array( $rule ) || empty( $rule['field'] ) ) {
                    continue;
                }
                $field_key = $rule['field'];
                $label     = $key_to_label[ $field_key ] ?? null;
                if ( $label === null ) {
                    $label = __( '[another field]', 'contextualwp' );
                }
                $op  = $rule['operator'] ?? '==';
                $val = isset( $rule['value'] ) && $rule['value'] !== '' ? (string) $rule['value'] : '';
                $phrase = $this->format_rule_phrase( $label, $op, $val, $key_to_type[ $field_key ] ?? '' );
                if ( $phrase !== '' ) {
                    $group_parts[] = $phrase;
                }
            }
            if ( ! empty( $group_parts ) ) {
                $parts[] = implode( ' AND ', $group_parts );
            }
        }

        if ( $parts === [] ) {
            return '';
        }
        $joined = count( $parts ) > 1 ? '(' . implode( ') OR (', $parts ) . ')' : $parts[0];
        return 'Shown when ' . $joined;
    }

    /**
     * Format a single conditional rule as editor-friendly phrase
     *
     * @param string $label Field label
     * @param string $op Operator (== or !=)
     * @param string $val Rule value
     * @param string $field_type Field type (e.g. true_false)
     * @return string
     */
    private function format_rule_phrase( $label, $op, $val, $field_type ) {
        $is_true_false = $field_type === 'true_false';
        if ( $is_true_false ) {
            if ( $val === '1' || $val === 1 ) {
                return $op === '==' ? $label . ' is ON' : $label . ' is OFF';
            }
            if ( $val === '0' || $val === 0 || $val === '' ) {
                return $op === '==' ? $label . ' is OFF' : $label . ' is ON';
            }
        }
        if ( $val === '' ) {
            return '';
        }
        return $op === '==' ? $label . ' is "' . $val . '"' : $label . ' is not "' . $val . '"';
    }

    /**
     * Collect which fields are shown/hidden when this field's value changes
     *
     * @param string $this_key This field's key
     * @param array  $sibling_fields All fields in the same group
     * @return string
     */
    private function collect_controlled_fields( $this_key, $sibling_fields ) {
        $parts = [];
        $is_true_false = false;
        foreach ( $sibling_fields as $f ) {
            if ( ( $f['key'] ?? '' ) === $this_key ) {
                $is_true_false = strtolower( (string) ( $f['type'] ?? '' ) ) === 'true_false';
                break;
            }
        }

        foreach ( $sibling_fields as $f ) {
            $sib_key = $f['key'] ?? '';
            if ( $sib_key === $this_key ) {
                continue;
            }
            $cond = $f['conditional_logic'] ?? null;
            if ( ! is_array( $cond ) ) {
                continue;
            }
            $sib_label = $f['label'] ?? __( '[unnamed field]', 'contextualwp' );
            $when_shown = [];

            foreach ( $cond as $group ) {
                if ( ! is_array( $group ) ) {
                    continue;
                }
                foreach ( $group as $rule ) {
                    if ( ! is_array( $rule ) || ( $rule['field'] ?? '' ) !== $this_key ) {
                        continue;
                    }
                    $op  = $rule['operator'] ?? '==';
                    $val = isset( $rule['value'] ) && $rule['value'] !== '' ? (string) $rule['value'] : '';
                    if ( $is_true_false ) {
                        if ( $val === '1' || $val === 1 ) {
                            $when_shown[] = $op === '==' ? 'ON' : 'OFF';
                        } elseif ( $val === '0' || $val === 0 || $val === '' ) {
                            $when_shown[] = $op === '==' ? 'OFF' : 'ON';
                        } else {
                            $when_shown[] = $op === '==' ? 'value is "' . $val . '"' : 'value is not "' . $val . '"';
                        }
                    } else {
                        $when_shown[] = $val === '' ? '' : ( $op === '==' ? 'value is "' . $val . '"' : 'value is not "' . $val . '"' );
                    }
                }
            }

            $when_shown = array_unique( array_filter( $when_shown ) );
            if ( ! empty( $when_shown ) ) {
                $parts[] = $sib_label . ': shown when ' . implode( ' or ', $when_shown );
            }
        }

        return implode( '. ', $parts );
    }

    /**
     * Summarize ACF location rules as human-readable post types
     *
     * @param array $location ACF location array
     * @return string
     */
    private function summarize_location( $location ) {
        if ( ! is_array( $location ) ) {
            return '';
        }

        $post_types = [];
        foreach ( $location as $group ) {
            if ( ! is_array( $group ) ) {
                continue;
            }
            foreach ( $group as $rule ) {
                if ( ! is_array( $rule ) ) {
                    continue;
                }
                $param = $rule['param'] ?? '';
                $op    = $rule['operator'] ?? '';
                $val   = isset( $rule['value'] ) ? $rule['value'] : '';
                if ( $param === 'post_type' && $op === '==' && $val !== '' ) {
                    $post_types[] = is_string( $val ) ? $val : (string) $val;
                }
            }
        }

        if ( empty( $post_types ) ) {
            return '';
        }

        return implode( ', ', array_unique( $post_types ) );
    }
}
