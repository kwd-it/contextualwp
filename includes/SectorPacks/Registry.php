<?php
namespace ContextualWP\SectorPacks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * In-memory registry of optional sector pack plugins. No persistence; registrations occur at runtime.
 *
 * @package ContextualWP
 */
final class Registry {

    /**
     * @var array<string, array<string, mixed>>
     */
    private static array $packs = [];

    /**
     * Register a pack. Duplicate slugs are rejected.
     *
     * @param array<string, mixed> $meta Raw metadata from a pack.
     * @return bool True if registered, false if invalid or duplicate slug.
     */
    public static function register( array $meta ): bool {
        $normalised = self::normalise_and_validate( $meta );
        if ( $normalised === null ) {
            return false;
        }
        $slug = $normalised['slug'];
        if ( isset( self::$packs[ $slug ] ) ) {
            return false;
        }
        $normalised['compatibility'] = self::assess_compatibility( $normalised );
        self::$packs[ $slug ]        = $normalised;

        /**
         * Fires after a sector pack has been registered successfully.
         *
         * @param string               $slug   Pack slug.
         * @param array<string, mixed> $record Normalised pack record including compatibility.
         */
        do_action( 'contextualwp_sector_pack_registered', $slug, self::$packs[ $slug ] );

        return true;
    }

    /**
     * @internal For automated tests only. Do not use in production plugins.
     */
    public static function reset_for_testing(): void {
        self::$packs = [];
    }

    /**
     * @param array<string, mixed> $record Normalised record.
     * @return array{compatible: bool, requires_core: string, reason: string}
     */
    public static function assess_compatibility( array $record ): array {
        $requires = isset( $record['requires_contextualwp'] ) ? (string) $record['requires_contextualwp'] : '';
        if ( $requires === '' ) {
            return [
                'compatible'    => true,
                'requires_core' => '',
                'reason'        => '',
            ];
        }
        $core = defined( 'CONTEXTUALWP_VERSION' ) ? CONTEXTUALWP_VERSION : '0';
        if ( version_compare( $core, $requires, '>=' ) ) {
            return [
                'compatible'    => true,
                'requires_core' => $requires,
                'reason'        => '',
            ];
        }
        return [
            'compatible'    => false,
            'requires_core' => $requires,
            /* translators: 1: required ContextualWP version, 2: current ContextualWP version */
            'reason'        => sprintf(
                __( 'This pack requires ContextualWP %1$s or newer; this site is running %2$s.', 'contextualwp' ),
                $requires,
                $core
            ),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function get_all_raw(): array {
        return self::$packs;
    }

    /**
     * Returns all packs with refreshed compatibility assessment.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_all(): array {
        $out = [];
        foreach ( self::$packs as $slug => $record ) {
            $record['compatibility'] = self::assess_compatibility( $record );
            $out[ $slug ]            = $record;
        }

        /**
         * Filter the list of registered sector packs (after compatibility is computed).
         *
         * @param array<string, array<string, mixed>> $out Slug => pack record.
         */
        return apply_filters( 'contextualwp_registered_sector_packs', $out );
    }

    /**
     * Expected keys: slug, name, version, description (optional), author or vendor (optional),
     * requires_contextualwp (optional minimum core version), settings_url (optional admin URL).
     *
     * @param array<string, mixed> $meta Input.
     * @return array<string, mixed>|null
     */
    public static function normalise_and_validate( array $meta ): ?array {
        $slug = isset( $meta['slug'] ) ? sanitize_key( (string) $meta['slug'] ) : '';
        if ( $slug === '' || ! preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $slug ) ) {
            return null;
        }
        $name = isset( $meta['name'] ) ? sanitize_text_field( (string) $meta['name'] ) : '';
        if ( $name === '' ) {
            return null;
        }
        $version = isset( $meta['version'] ) ? sanitize_text_field( (string) $meta['version'] ) : '';
        if ( $version === '' ) {
            return null;
        }
        $description = isset( $meta['description'] ) ? sanitize_textarea_field( (string) $meta['description'] ) : '';
        $author      = '';
        if ( isset( $meta['author'] ) && $meta['author'] !== '' ) {
            $author = sanitize_text_field( (string) $meta['author'] );
        } elseif ( isset( $meta['vendor'] ) && $meta['vendor'] !== '' ) {
            $author = sanitize_text_field( (string) $meta['vendor'] );
        }
        $requires     = isset( $meta['requires_contextualwp'] ) ? sanitize_text_field( (string) $meta['requires_contextualwp'] ) : '';
        $settings_url = isset( $meta['settings_url'] ) ? esc_url_raw( (string) $meta['settings_url'] ) : '';
        if ( $settings_url !== '' && ! self::is_plausible_admin_url( $settings_url ) ) {
            $settings_url = '';
        }

        return [
            'slug'                  => $slug,
            'name'                  => $name,
            'version'               => $version,
            'description'           => $description,
            'author'                => $author,
            'requires_contextualwp' => $requires,
            'settings_url'          => $settings_url,
        ];
    }

    private static function is_plausible_admin_url( string $url ): bool {
        $admin = admin_url();
        if ( $admin === '' ) {
            return true;
        }
        $parts_url   = wp_parse_url( $url );
        $parts_admin = wp_parse_url( $admin );
        if ( ! is_array( $parts_url ) || ! isset( $parts_url['host'] ) ) {
            return false;
        }
        if ( is_array( $parts_admin ) && isset( $parts_admin['host'] ) ) {
            if ( strtolower( (string) $parts_url['host'] ) !== strtolower( (string) $parts_admin['host'] ) ) {
                return false;
            }
        }
        $path = isset( $parts_url['path'] ) ? (string) $parts_url['path'] : '';
        if ( $path === '' ) {
            return false;
        }
        return true;
    }
}
