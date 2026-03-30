<?php
/**
 * Sector pack registry (no WordPress test suite required).
 *
 * @package ContextualWP\Tests
 */

namespace ContextualWP\Tests;

use ContextualWP\SectorPacks\Registry;
use PHPUnit\Framework\TestCase;

class SectorPackRegistryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'CONTEXTUALWP_VERSION' ) ) {
			define( 'CONTEXTUALWP_VERSION', '1.0.0' );
		}
		Registry::reset_for_testing();
	}

	public function test_no_packs_registered(): void {
		$this->assertSame( [], Registry::get_all() );
	}

	public function test_register_valid_pack(): void {
		$ok = \contextualwp_register_sector_pack(
			[
				'slug'                   => 'demo-pack',
				'name'                   => 'Demo Pack',
				'version'                => '0.1.0',
				'description'            => 'Test pack.',
				'author'                 => 'Test Author',
				'requires_contextualwp' => '1.0.0',
				'settings_url'           => admin_url( 'admin.php?page=demo' ),
			]
		);
		$this->assertTrue( $ok );
		$all = Registry::get_all();
		$this->assertArrayHasKey( 'demo-pack', $all );
		$this->assertTrue( $all['demo-pack']['compatibility']['compatible'] );
	}

	public function test_duplicate_slug_rejected(): void {
		$meta = [
			'slug'    => 'same-slug',
			'name'    => 'One',
			'version' => '1.0.0',
		];
		$this->assertTrue( Registry::register( $meta ) );
		$meta['name'] = 'Two';
		$this->assertFalse( Registry::register( $meta ) );
	}

	public function test_incompatible_pack_recorded_gracefully(): void {
		\contextualwp_register_sector_pack(
			[
				'slug'                   => 'needs-future',
				'name'                   => 'Future Core',
				'version'                => '1.0.0',
				'requires_contextualwp' => '99.0.0',
			]
		);
		$all = Registry::get_all();
		$this->assertFalse( $all['needs-future']['compatibility']['compatible'] );
		$this->assertNotSame( '', $all['needs-future']['compatibility']['reason'] );
	}

	public function test_invalid_metadata_rejected(): void {
		$this->assertFalse(
			Registry::register(
				[
					'slug'    => '',
					'name'    => 'X',
					'version' => '1.0.0',
				]
			)
		);
		$this->assertFalse(
			Registry::register(
				[
					'slug'    => 'ok-slug',
					'name'    => '',
					'version' => '1.0.0',
				]
			)
		);
	}

	public function test_vendor_maps_to_author(): void {
		Registry::register(
			[
				'slug'    => 'vpack',
				'name'    => 'Vendor Pack',
				'version' => '1.0.0',
				'vendor'  => 'ACME Ltd',
			]
		);
		$all = Registry::get_all();
		$this->assertSame( 'ACME Ltd', $all['vpack']['author'] );
	}
}
