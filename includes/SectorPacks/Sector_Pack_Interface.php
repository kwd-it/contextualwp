<?php
namespace ContextualWP\SectorPacks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Optional contract for sector pack plugins that expose metadata via a class.
 *
 * Packs may instead call contextualwp_register_sector_pack() with an array; both approaches are valid.
 *
 * @package ContextualWP
 */
interface Sector_Pack_Interface {

    /**
     * Return pack metadata matching the shape validated by Registry::normalise_and_validate().
     *
     * @return array<string, mixed>
     */
    public function get_sector_pack_metadata(): array;
}
