<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register a sector pack with ContextualWP. Call from the pack plugin on contextualwp_sector_packs_init
 * or later (for example plugins_loaded at a priority after ContextualWP has loaded).
 *
 * @param array<string, mixed> $meta Pack metadata; see \ContextualWP\SectorPacks\Registry::normalise_and_validate().
 * @return bool True on success.
 */
function contextualwp_register_sector_pack( array $meta ): bool {
    return \ContextualWP\SectorPacks\Registry::register( $meta );
}

/**
 * @return array<string, array<string, mixed>>
 */
function contextualwp_get_registered_sector_packs(): array {
    return \ContextualWP\SectorPacks\Registry::get_all();
}
