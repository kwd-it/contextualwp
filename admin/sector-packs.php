<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lightweight Sector Packs admin page (informational only; packs are separate plugins).
 */
class ContextualWP_Admin_Sector_Packs {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
	}

	public function add_menu_page() {
		add_submenu_page(
			'contextualwp-settings',
			__( 'ContextualWP Sector Packs', 'contextualwp' ),
			__( 'ContextualWP Packs', 'contextualwp' ),
			'manage_options',
			'contextualwp-sector-packs',
			[ $this, 'render_page' ]
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$packs = contextualwp_get_registered_sector_packs();

		/**
		 * Additional admin links for sector packs (empty by default). Packs may append items with keys `label` and `url`.
		 * Per-pack `settings_url` from registration is shown in the table column above; use this for extra shortcuts only.
		 *
		 * @param array<int, array<string, string>> $additional_links
		 * @param array<string, array<string, mixed>> $packs
		 */
		$additional_links = apply_filters( 'contextualwp_sector_pack_admin_links', [], $packs );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'ContextualWP Sector Packs', 'contextualwp' ) . '</h1>';
		echo '<p>' . esc_html__( 'Sector packs are optional companion plugins. They are installed and updated like any other WordPress plugin. This screen only lists packs that have registered with ContextualWP.', 'contextualwp' ) . '</p>';

		if ( $packs === [] ) {
			echo '<p><em>' . esc_html__( 'No sector packs are registered.', 'contextualwp' ) . '</em></p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'contextualwp' ) . '</th>';
		echo '<th>' . esc_html__( 'Slug', 'contextualwp' ) . '</th>';
		echo '<th>' . esc_html__( 'Version', 'contextualwp' ) . '</th>';
		echo '<th>' . esc_html__( 'Author', 'contextualwp' ) . '</th>';
		echo '<th>' . esc_html__( 'Compatibility', 'contextualwp' ) . '</th>';
		echo '<th>' . esc_html__( 'Pack settings', 'contextualwp' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $packs as $slug => $record ) {
			$compat = isset( $record['compatibility'] ) && is_array( $record['compatibility'] ) ? $record['compatibility'] : [];
			$ok     = ! empty( $compat['compatible'] );
			$status = $ok
				? '<span class="contextualwp-pack-compat-ok">' . esc_html__( 'Compatible', 'contextualwp' ) . '</span>'
				: '<span class="contextualwp-pack-compat-no">' . esc_html__( 'Incompatible', 'contextualwp' ) . '</span>';
			if ( ! $ok && ! empty( $compat['reason'] ) ) {
				$status .= '<br><small>' . esc_html( (string) $compat['reason'] ) . '</small>';
			}

			$url   = isset( $record['settings_url'] ) ? (string) $record['settings_url'] : '';
			$link  = $url !== ''
				? '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open', 'contextualwp' ) . '</a>'
				: '—';

			echo '<tr>';
			echo '<td>' . esc_html( (string) ( $record['name'] ?? '' ) ) . '</td>';
			echo '<td><code>' . esc_html( (string) $slug ) . '</code></td>';
			echo '<td>' . esc_html( (string) ( $record['version'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $record['author'] ?? '' ) ) . '</td>';
			echo '<td>' . $status . '</td>';
			echo '<td>' . $link . '</td>';
			echo '</tr>';
			$desc = isset( $record['description'] ) ? trim( (string) $record['description'] ) : '';
			if ( $desc !== '' ) {
				echo '<tr class="contextualwp-pack-desc"><td colspan="6">' . esc_html( $desc ) . '</td></tr>';
			}
		}

		echo '</tbody></table>';

		if ( is_array( $additional_links ) && $additional_links !== [] ) {
			echo '<h2>' . esc_html__( 'Additional links', 'contextualwp' ) . '</h2>';
			echo '<ul class="contextualwp-sector-pack-extra-links">';
			foreach ( $additional_links as $item ) {
				if ( ! is_array( $item ) || empty( $item['url'] ) || empty( $item['label'] ) ) {
					continue;
				}
				echo '<li><a href="' . esc_url( (string) $item['url'] ) . '">' . esc_html( (string) $item['label'] ) . '</a></li>';
			}
			echo '</ul>';
		}

		/**
		 * After the sector packs table (and optional additional links). For lightweight admin extensions.
		 *
		 * @param array<string, array<string, mixed>> $packs
		 */
		do_action( 'contextualwp_sector_packs_admin_page_after_table', $packs );

		echo '</div>';
	}
}

if ( is_admin() ) {
	new ContextualWP_Admin_Sector_Packs();
}
