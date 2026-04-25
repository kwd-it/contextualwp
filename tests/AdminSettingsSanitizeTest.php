<?php
/**
 * Tests for ContextualWP admin settings sanitisation (outside full WordPress).
 *
 * @package ContextualWP\Tests
 */

namespace ContextualWP\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once dirname( __DIR__ ) . '/includes/helpers/providers.php';
require_once dirname( __DIR__ ) . '/admin/settings.php';

/**
 * @covers \ContextualWP_Admin_Settings::sanitize_settings
 */
class AdminSettingsSanitizeTest extends TestCase {

	/**
	 * Avoid running the real constructor (add_action) outside WordPress.
	 */
	private function settings_instance(): \ContextualWP_Admin_Settings {
		$ref = new ReflectionClass( \ContextualWP_Admin_Settings::class );
		return $ref->newInstanceWithoutConstructor();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function base_input() {
		return [
			'ai_provider' => 'OpenAI',
			'api_key'     => 'sk-test',
			'model'       => 'gpt-5.5',
			'max_tokens'  => 1024,
			'temperature' => 1.0,
		];
	}

	public function test_smart_model_selection_false_when_checkbox_absent_from_post(): void {
		$settings = $this->settings_instance();
		$out      = $settings->sanitize_settings( $this->base_input() );
		$this->assertArrayHasKey( 'smart_model_selection', $out );
		$this->assertFalse( $out['smart_model_selection'] );
	}

	public function test_smart_model_selection_true_when_checkbox_submitted_as_one(): void {
		$settings = $this->settings_instance();
		$in       = $this->base_input();
		$in['smart_model_selection'] = '1';
		$out = $settings->sanitize_settings( $in );
		$this->assertTrue( $out['smart_model_selection'] );
	}

	public function test_sanitize_preserves_other_main_fields_when_toggling_smart_model(): void {
		$settings = $this->settings_instance();
		$in       = $this->base_input();
		$in['api_key']     = 'sk-preserved';
		$in['model']       = 'gpt-5.4-mini';
		$in['max_tokens']  = 2048;
		$in['temperature'] = 0.5;
		$out = $settings->sanitize_settings( $in );
		$this->assertSame( 'OpenAI', $out['ai_provider'] );
		$this->assertSame( 'sk-preserved', $out['api_key'] );
		$this->assertSame( 'gpt-5.4-mini', $out['model'] );
		$this->assertSame( 2048, $out['max_tokens'] );
		$this->assertSame( 0.5, $out['temperature'] );
		$this->assertFalse( $out['smart_model_selection'] );
	}
}
