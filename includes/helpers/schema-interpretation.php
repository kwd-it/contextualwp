<?php
namespace ContextualWP\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds the default `interpretation` payload for `/contextualwp/v1/schema`.
 *
 * Complements ACF 6.8+ automatic Schema.org JSON-LD: ContextualWP does not output JSON-LD; this interpretation layer
 * stays AI- and editor-oriented (summaries, relationship hints, capability flags, optional ACF-derived relationship edges)
 * and never embeds raw JSON-LD graphs.
 *
 * @package ContextualWP
 * @since 1.2.0
 */
class Schema_Interpretation {

    /**
     * @param array<string, mixed> $schema Full schema after `contextualwp_schema`.
     * @return array<string, mixed> Non-empty when ACF is active, manifest relationships or ACF-derived fallback edges exist, or sector packs are registered.
     */
    public static function build( array $schema ): array {
        $has_acf       = isset( $schema['acf_field_groups'] ) && is_array( $schema['acf_field_groups'] );
        $relationships = apply_filters( 'contextualwp_manifest_schema_relationships', [] );
        if ( ! is_array( $relationships ) ) {
            $relationships = [];
        }
        $has_rels      = $relationships !== [];

        $packs = function_exists( 'contextualwp_get_registered_sector_packs' ) ? contextualwp_get_registered_sector_packs() : [];
        $has_packs     = is_array( $packs ) && $packs !== [];

        if ( ! $has_rels && $has_acf ) {
            $derived = self::acf_derived_relationship_edges( $schema['acf_field_groups'] );
            if ( $derived !== [] ) {
                $relationships = $derived;
                $has_rels      = true;
            }
        }

        if ( ! $has_acf && ! $has_rels && ! $has_packs ) {
            return [];
        }

        $block = [
            'about' => __(
                'ContextualWP interpretation layer: human-readable content model hints for agents. This is not Schema.org JSON-LD; ACF may output JSON-LD on singular front-end views when the site enables it.',
                'contextualwp'
            ),
        ];

        $post_types = isset( $schema['post_types'] ) && is_array( $schema['post_types'] ) ? $schema['post_types'] : [];
        if ( $post_types !== [] ) {
            $block['wordpress'] = self::summarize_post_types( $post_types );
        }

        if ( $has_rels ) {
            $block['relationships'] = [
                'edges'      => $relationships,
                'narrative'  => self::relationships_narrative( $relationships ),
                'guidance'   => __(
                    'Treat edges as declared domain links (e.g. development → plots). Validate against live content via MCP get_context.',
                    'contextualwp'
                ),
            ];
        }

        if ( $has_acf ) {
            $groups = $schema['acf_field_groups'];
            $block['acf'] = [
                'field_group_count'       => count( $groups ),
                'relationship_field_hints'=> self::acf_relationship_hints( $groups ),
                'structured_data'         => self::acf_structured_data_availability(),
                'editor_safe_field_detail'=> __(
                    'Use GET /wp-json/contextualwp/v1/acf_schema for editor-safe ACF field metadata (labels, types, conditional logic summaries).',
                    'contextualwp'
                ),
            ];
        }

        if ( $has_packs ) {
            $block['sector_packs'] = self::summarize_sector_packs( $packs );
        }

        return [ 'contextualwp' => $block ];
    }

    /**
     * @param array<int, array<string, mixed>> $post_types
     * @return array<string, mixed>
     */
    private static function summarize_post_types( array $post_types ): array {
        $entities = [];
        $lines    = [];
        $limit    = 40;
        $i        = 0;
        foreach ( $post_types as $pt ) {
            if ( ! is_array( $pt ) ) {
                continue;
            }
            $slug = isset( $pt['slug'] ) ? (string) $pt['slug'] : '';
            if ( $slug === '' ) {
                continue;
            }
            $label = isset( $pt['label'] ) ? (string) $pt['label'] : $slug;
            $entities[] = [
                'slug'        => $slug,
                'label'       => $label,
                'description' => sprintf(
                    /* translators: %s: post type label */
                    __( 'Public content type "%s" (REST and templates depend on theme/plugins).', 'contextualwp' ),
                    $label
                ),
            ];
            $tax = isset( $pt['taxonomies'] ) && is_array( $pt['taxonomies'] ) ? $pt['taxonomies'] : [];
            $tax_note = $tax !== [] ? ' [' . implode( ', ', array_map( 'strval', $tax ) ) . ']' : '';
            $lines[] = $label . ' (' . $slug . ')' . $tax_note;
            if ( ++$i >= $limit ) {
                break;
            }
        }

        return [
            'entities'          => $entities,
            'one_line_overview' => implode( '; ', $lines ),
        ];
    }

    /**
     * @param array<int, mixed> $relationships
     */
    private static function relationships_narrative( array $relationships ): string {
        $parts = [];
        foreach ( $relationships as $r ) {
            if ( ! is_array( $r ) ) {
                continue;
            }
            $src  = isset( $r['source_type'] ) ? (string) $r['source_type'] : '';
            $tgt  = isset( $r['target_type'] ) ? (string) $r['target_type'] : '';
            $desc = isset( $r['description'] ) ? (string) $r['description'] : '';
            if ( $src === '' || $tgt === '' ) {
                continue;
            }
            $parts[] = trim( $src . ' → ' . $tgt . ( $desc !== '' ? ': ' . $desc : '' ) );
        }
        return implode( "\n", $parts );
    }

    /**
     * When manifest relationships are empty, infer graph edges from ACF post_object / relationship fields.
     *
     * @param array<int, array<string, mixed>> $groups Schema `acf_field_groups` (includes `location`, `fields`).
     * @return array<int, array<string, string>>
     */
    private static function acf_derived_relationship_edges( array $groups ): array {
        $edges = [];
        foreach ( $groups as $group ) {
            if ( ! is_array( $group ) ) {
                continue;
            }
            $location = isset( $group['location'] ) && is_array( $group['location'] ) ? $group['location'] : [];
            $source   = self::acf_infer_source_post_type_from_location( $location );

            $fields = isset( $group['fields'] ) && is_array( $group['fields'] ) ? $group['fields'] : [];
            foreach ( $fields as $field ) {
                if ( ! is_array( $field ) ) {
                    continue;
                }
                $type = isset( $field['type'] ) ? (string) $field['type'] : '';
                if ( ! in_array( $type, [ 'post_object', 'relationship' ], true ) ) {
                    continue;
                }
                $label = isset( $field['label'] ) ? (string) $field['label'] : '';
                $name  = isset( $field['name'] ) ? (string) $field['name'] : '';
                $pt    = $field['post_type'] ?? null;
                $targets = [];
                if ( is_array( $pt ) ) {
                    foreach ( $pt as $p ) {
                        if ( is_string( $p ) && $p !== '' ) {
                            $targets[] = $p;
                        }
                    }
                } elseif ( is_string( $pt ) && $pt !== '' ) {
                    $targets[] = $pt;
                }
                if ( $targets === [] ) {
                    continue;
                }
                $field_label = $label !== '' ? $label : ( $name !== '' ? $name : __( '(unnamed)', 'contextualwp' ) );
                foreach ( array_unique( $targets ) as $target_slug ) {
                    $edges[] = [
                        'source_type' => $source,
                        'target_type' => $target_slug,
                        'description' => sprintf(
                            /* translators: %s: ACF field label or name */
                            __( 'Derived from ACF field "%s"', 'contextualwp' ),
                            $field_label
                        ),
                    ];
                }
            }
        }
        return $edges;
    }

    /**
     * Infer a single post type slug from ACF location rules, or `unknown` when unclear or missing.
     *
     * @param array<int, mixed> $location ACF `location` array (OR of AND-rule groups).
     */
    private static function acf_infer_source_post_type_from_location( array $location ): string {
        $slugs = [];
        foreach ( $location as $or_group ) {
            if ( ! is_array( $or_group ) ) {
                continue;
            }
            foreach ( $or_group as $rule ) {
                if ( ! is_array( $rule ) ) {
                    continue;
                }
                if ( ( $rule['param'] ?? '' ) !== 'post_type' ) {
                    continue;
                }
                if ( ( $rule['operator'] ?? '' ) !== '==' ) {
                    continue;
                }
                $value = $rule['value'] ?? '';
                if ( is_string( $value ) && $value !== '' ) {
                    $slugs[] = $value;
                }
            }
        }
        $unique = array_values( array_unique( $slugs ) );
        if ( count( $unique ) === 1 ) {
            return $unique[0];
        }
        return 'unknown';
    }

    /**
     * @param array<int, array<string, mixed>> $groups
     * @return array<int, array<string, mixed>>
     */
    private static function acf_relationship_hints( array $groups ): array {
        $hints = [];
        foreach ( $groups as $group ) {
            if ( ! is_array( $group ) ) {
                continue;
            }
            $fields = isset( $group['fields'] ) && is_array( $group['fields'] ) ? $group['fields'] : [];
            foreach ( $fields as $field ) {
                if ( ! is_array( $field ) ) {
                    continue;
                }
                $type = isset( $field['type'] ) ? (string) $field['type'] : '';
                if ( ! in_array( $type, [ 'post_object', 'relationship' ], true ) ) {
                    continue;
                }
                $label = isset( $field['label'] ) ? (string) $field['label'] : '';
                $name  = isset( $field['name'] ) ? (string) $field['name'] : '';
                $pt    = $field['post_type'] ?? null;
                $target_slugs = [];
                if ( is_array( $pt ) ) {
                    foreach ( $pt as $p ) {
                        if ( is_string( $p ) && $p !== '' ) {
                            $target_slugs[] = $p;
                        }
                    }
                } elseif ( is_string( $pt ) && $pt !== '' ) {
                    $target_slugs[] = $pt;
                }
                $targets_readable = $target_slugs !== [] ? implode( ', ', $target_slugs ) : __( 'unspecified post types', 'contextualwp' );
                $hints[]          = [
                    'field_label'          => $label !== '' ? $label : $name,
                    'field_name'           => $name,
                    'field_type'           => $type,
                    'links_to_post_types'  => $target_slugs,
                    'summary'              => sprintf(
                        /* translators: 1: field label or name, 2: comma-separated post type slugs */
                        __( 'Field "%1$s" references other content (post types: %2$s).', 'contextualwp' ),
                        $label !== '' ? $label : ( $name !== '' ? $name : __( '(unnamed)', 'contextualwp' ) ),
                        $targets_readable
                    ),
                ];
            }
        }
        return $hints;
    }

    /**
     * Progressive enhancement: detect whether ACF 6.8’s JSON-LD stack may be active (no payload inspection).
     *
     * @return array<string, mixed>
     */
    private static function acf_structured_data_availability(): array {
        if ( ! defined( 'ACF_VERSION' ) || ! is_string( ACF_VERSION ) ) {
            return [
                'acf_version_supports_schema_org_json_ld' => false,
                'enable_schema_site_setting'              => null,
                'note'                                    => __( 'ACF version could not be read; automatic Schema.org JSON-LD (ACF 6.8+) is unknown.', 'contextualwp' ),
            ];
        }

        $version_ok = version_compare( ACF_VERSION, '6.8', '>=' );
        if ( ! $version_ok ) {
            return [
                'acf_version_supports_schema_org_json_ld' => false,
                'acf_version'                             => ACF_VERSION,
                'enable_schema_site_setting'              => null,
                'note'                                    => __( 'Automatic Schema.org JSON-LD requires ACF 6.8 or newer.', 'contextualwp' ),
            ];
        }

        // ACF gates the feature behind this filter; UI/post-type toggles may still limit output.
        $enabled = (bool) apply_filters( 'acf/settings/enable_schema', false );

        return [
            'acf_version_supports_schema_org_json_ld' => true,
            'acf_version'                             => ACF_VERSION,
            'enable_schema_site_setting'              => $enabled,
            'note'                                    => $enabled
                ? __(
                    'ACF’s site setting allows Schema.org JSON-LD. JSON-LD is emitted on singular front-end views when post types/fields are configured for it—inspect page source or Site Health, not this endpoint.',
                    'contextualwp'
                )
                : __(
                    'ACF is new enough for Schema.org JSON-LD, but acf/settings/enable_schema is not enabled, so automatic JSON-LD is likely off unless customised elsewhere.',
                    'contextualwp'
                ),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $packs
     * @return array<int, array<string, string>>
     */
    private static function summarize_sector_packs( array $packs ): array {
        $out = [];
        foreach ( $packs as $slug => $meta ) {
            if ( ! is_string( $slug ) || $slug === '' || ! is_array( $meta ) ) {
                continue;
            }
            $name = isset( $meta['name'] ) ? (string) $meta['name'] : $slug;
            $out[] = [
                'slug'        => $slug,
                'name'        => $name,
                'description' => sprintf(
                    /* translators: %s: sector pack name */
                    __( 'Registered sector pack "%s" may supply extra prompts or interpretation via ContextualWP hooks.', 'contextualwp' ),
                    $name
                ),
            ];
        }
        return $out;
    }
}
