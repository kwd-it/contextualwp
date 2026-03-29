<?php
/**
 * Tests for AskAI textarea grounding: system messages and advise intent routing.
 *
 * @package ContextualWP\Tests
 */

namespace ContextualWP\Tests;

use ContextualWP\Endpoints\Generate_Context;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * AskAI textarea-specific prompt routing (PHP side).
 */
class AskAITextareaHelperTest extends TestCase {

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
	private function mock_request( array $params ) {
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

	public function test_textarea_explain_system_message_is_grounded(): void {
		$req = $this->mock_request( [ 'field_type' => 'textarea' ] );
		$msg = $this->invoke_private( 'get_askai_system_message', [ 'explain', $req ] );
		$this->assertStringContainsString( 'neutral', strtolower( $msg ) );
		$this->assertStringContainsString( 'Do NOT mention:', $msg );
		$this->assertStringContainsString( 'word count', strtolower( $msg ) );
	}

	public function test_textarea_advise_system_message_avoids_bullet_format_prescription(): void {
		$req = $this->mock_request( [ 'field_type' => 'textarea' ] );
		$msg = $this->invoke_private( 'get_askai_system_message', [ 'advise', $req ] );
		$this->assertStringContainsString( 'Follow any instructions', $msg );
		$this->assertStringNotContainsString( '2–4 concise bullets', $msg );
	}

	public function test_text_advise_system_message_keeps_bullet_guidance(): void {
		$req = $this->mock_request( [ 'field_type' => 'text' ] );
		$msg = $this->invoke_private( 'get_askai_system_message', [ 'advise', $req ] );
		$this->assertStringContainsString( '2–4 concise bullets', $msg );
	}

	public function test_textarea_behaviour_appends_plain_stored_text_note(): void {
		$req = $this->mock_request( [ 'field_type' => 'textarea' ] );
		$msg = $this->invoke_private( 'get_askai_system_message', [ 'behaviour', $req ] );
		$this->assertStringContainsString( 'textarea', strtolower( $msg ) );
		$this->assertStringContainsString( 'layout', strtolower( $msg ) );
	}

	/**
	 * QA prompt A2 must resolve to advise so textarea-specific system text applies.
	 */
	public function test_detect_intent_how_should_fill_is_advise(): void {
		$prompt  = "How should I fill this in well?\n\n---\nACF Field context:\nType: textarea\nLabel: Bio\n";
		$request = $this->mock_request( [ 'source' => 'acf_field_helper' ] );
		$intent  = $this->invoke_private( 'detect_askai_intent', [ $prompt, $request ] );
		$this->assertSame( 'advise', $intent );
	}
}
