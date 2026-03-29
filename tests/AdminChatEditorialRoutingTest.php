<?php
/**
 * Tests for admin floating chat: editorial (B2/B5-style) prompt routing on CPT strict context
 * and editorial detection for QA matrix prompts.
 *
 * @package ContextualWP\Tests
 */

namespace ContextualWP\Tests;

use ContextualWP\Endpoints\Generate_Context;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Editorial vs strict CPT system messages and is_editorial_improvement_prompt().
 */
class AdminChatEditorialRoutingTest extends TestCase {

	/**
	 * @param string $methodName Method name.
	 * @param array  $args       Arguments.
	 * @return mixed
	 */
	private function invoke_private( $methodName, array $args = [] ) {
		$reflection = new ReflectionClass( Generate_Context::class );
		$method     = $reflection->getMethod( $methodName );
		$method->setAccessible( true );
		$instance = new Generate_Context();
		return $method->invokeArgs( $instance, $args );
	}

	/**
	 * @param array $params Request params for get_param.
	 * @return object
	 */
	private function mock_request( array $params = [] ) {
		return new class( $params ) {
			private $params;
			public function __construct( array $params ) {
				$this->params = $params;
			}
			public function get_param( $key ) {
				return $this->params[ $key ] ?? null;
			}
		};
	}

	public function test_b2_qa_prompt_is_editorial(): void {
		$p = 'Suggest improvements for clarity and tone. Keep it practical.';
		$this->assertTrue( $this->invoke_private( 'is_editorial_improvement_prompt', [ $p ] ) );
	}

	public function test_b1_summarize_bullets_is_not_editorial(): void {
		$p = 'Summarise this content in 5 bullet points.';
		$this->assertFalse( $this->invoke_private( 'is_editorial_improvement_prompt', [ $p ] ) );
	}

	public function test_b3_seo_is_not_editorial(): void {
		$p = 'Suggest an SEO title (max 60 chars) and meta description (max 155 chars).';
		$this->assertFalse( $this->invoke_private( 'is_editorial_improvement_prompt', [ $p ] ) );
	}

	public function test_b4_extract_table_is_not_editorial(): void {
		$p = 'Extract key facts into a simple table.';
		$this->assertFalse( $this->invoke_private( 'is_editorial_improvement_prompt', [ $p ] ) );
	}

	public function test_b5_rewrite_intro_is_editorial(): void {
		$p = 'Rewrite the intro to be clearer and more engaging, without changing meaning.';
		$this->assertTrue( $this->invoke_private( 'is_editorial_improvement_prompt', [ $p ] ) );
	}

	public function test_cpt_context_uses_editorial_system_message_for_b2(): void {
		$context_data = [
			'meta' => [ 'type' => 'development' ],
		];
		$req = $this->mock_request( [] );
		$msg = $this->invoke_private( 'get_system_message_for_single_context', [
			$context_data,
			'Suggest improvements for clarity and tone. Keep it practical.',
			$req,
		] );
		$this->assertStringContainsString( 'practical editorial suggestions', strtolower( $msg ) );
		$this->assertStringNotContainsString( 'answer ONLY using facts explicitly stated', $msg );
	}

	public function test_cpt_context_uses_strict_message_for_summarize(): void {
		$context_data = [
			'meta' => [ 'type' => 'development' ],
		];
		$req = $this->mock_request( [] );
		$msg = $this->invoke_private( 'get_system_message_for_single_context', [
			$context_data,
			'Summarise this content in 5 bullet points.',
			$req,
		] );
		$this->assertStringContainsString( 'ONLY using facts explicitly stated', $msg );
	}

	public function test_post_context_ignores_cpt_editorial_branch(): void {
		$context_data = [
			'meta' => [ 'type' => 'post' ],
		];
		$req = $this->mock_request( [] );
		$msg = $this->invoke_private( 'get_system_message_for_single_context', [
			$context_data,
			'Suggest improvements for clarity and tone. Keep it practical.',
			$req,
		] );
		$this->assertSame( 'You are a helpful assistant. Use the following context to answer.', $msg );
	}

	public function test_cpt_field_helper_does_not_use_editorial_message_for_b2_prompt(): void {
		$context_data = [
			'meta' => [ 'type' => 'development' ],
		];
		$req = $this->mock_request( [ 'source' => 'acf_field_helper', 'field_type' => 'textarea' ] );
		$msg = $this->invoke_private( 'get_system_message_for_single_context', [
			$context_data,
			'Suggest improvements for clarity and tone. Keep it practical.',
			$req,
		] );
		$this->assertStringContainsString( 'ONLY using facts explicitly stated', $msg );
	}
}
