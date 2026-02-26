<?php
/**
 * PHPUnit tests for OpenAI provider: Responses API selection, output normalisation,
 * and graceful handling of incomplete/no-visible-text (no "reasoning tokens" message).
 *
 * @package ContextualWP\Tests
 */

namespace ContextualWP\Tests;

use ContextualWP\Endpoints\Generate_Context;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_REST_Request;

/**
 * Tests for OpenAI provider (Responses API vs Chat Completions, output normalisation).
 */
class OpenAIProviderTest extends TestCase {

	/**
	 * Invoke private method on Generate_Context.
	 *
	 * @param string $methodName Method name.
	 * @param array  $args       Arguments.
	 * @return mixed
	 */
	private function invoke_private( $methodName, array $args = [] ) {
		$reflection = new ReflectionClass( Generate_Context::class );
		$method = $reflection->getMethod( $methodName );
		$method->setAccessible( true );
		$instance = new Generate_Context();
		return $method->invokeArgs( $instance, $args );
	}

	/**
	 * gpt-5.2 and other GPT-5.x models must use the Responses API path.
	 */
	public function test_gpt_5_2_uses_responses_api(): void {
		$this->assertTrue( $this->invoke_private( 'openai_uses_responses_api', [ 'gpt-5.2' ] ) );
		$this->assertTrue( $this->invoke_private( 'openai_uses_responses_api', [ 'gpt-5-mini' ] ) );
		$this->assertTrue( $this->invoke_private( 'openai_uses_responses_api', [ 'gpt-5-nano' ] ) );
		$this->assertTrue( $this->invoke_private( 'openai_uses_responses_api', [ 'gpt-5' ] ) );
	}

	/**
	 * Non-GPT-5.x models use Chat Completions (not Responses API).
	 */
	public function test_legacy_models_do_not_use_responses_api(): void {
		$this->assertFalse( $this->invoke_private( 'openai_uses_responses_api', [ 'gpt-4o' ] ) );
		$this->assertFalse( $this->invoke_private( 'openai_uses_responses_api', [ 'gpt-4-turbo' ] ) );
		$this->assertFalse( $this->invoke_private( 'openai_uses_responses_api', [ '' ] ) );
	}

	/**
	 * max_output_tokens is clamped between 256 and 4096.
	 */
	public function test_openai_clamp_max_output_tokens(): void {
		$this->assertSame( 256, $this->invoke_private( 'openai_clamp_max_output_tokens', [ 0 ] ) );
		$this->assertSame( 256, $this->invoke_private( 'openai_clamp_max_output_tokens', [ 100 ] ) );
		$this->assertSame( 1024, $this->invoke_private( 'openai_clamp_max_output_tokens', [ 1024 ] ) );
		$this->assertSame( 4096, $this->invoke_private( 'openai_clamp_max_output_tokens', [ 4096 ] ) );
		$this->assertSame( 4096, $this->invoke_private( 'openai_clamp_max_output_tokens', [ 10000 ] ) );
	}

	/**
	 * Parse Responses API payload: incomplete / no visible text => output_text empty, is_incomplete true.
	 */
	public function test_parse_openai_responses_incomplete_returns_no_visible_text(): void {
		$payload = [
			'output' => [],
			'status' => 'incomplete',
		];
		$result = $this->invoke_private( 'parse_openai_responses_output', [ $payload ] );
		$this->assertIsArray( $result );
		$this->assertSame( '', $result['output_text'] );
		$this->assertTrue( $result['is_incomplete'] );
		$this->assertSame( $payload, $result['raw'] );
	}

	/**
	 * Parse Responses API payload: message with text => output_text populated, is_incomplete false.
	 */
	public function test_parse_openai_responses_completed_with_text(): void {
		$payload = [
			'output' => [
				[
					'type'    => 'message',
					'content' => [
						[ 'type' => 'output_text', 'text' => 'Hello, world.' ],
					],
				],
			],
			'status' => 'completed',
		];
		$result = $this->invoke_private( 'parse_openai_responses_output', [ $payload ] );
		$this->assertIsArray( $result );
		$this->assertSame( 'Hello, world.', $result['output_text'] );
		$this->assertFalse( $result['is_incomplete'] );
	}

	/**
	 * Parse Responses API: content parts use "text" key (API uses output_text type but text field).
	 */
	public function test_parse_openai_responses_extracts_text_from_content_parts(): void {
		$payload = [
			'output' => [
				[
					'type'    => 'message',
					'content' => [
						[ 'text' => 'Part one. ' ],
						[ 'text' => 'Part two.' ],
					],
				],
			],
			'status' => 'completed',
		];
		$result = $this->invoke_private( 'parse_openai_responses_output', [ $payload ] );
		$this->assertSame( 'Part one. Part two.', $result['output_text'] );
		$this->assertFalse( $result['is_incomplete'] );
	}

	/**
	 * Generic failure message must never mention "reasoning tokens" (user-facing).
	 */
	public function test_generic_failure_message_does_not_mention_reasoning_tokens(): void {
		$generic = "Couldn't generate a response with the current model. Please try again or switch model.";
		$this->assertStringNotContainsString( 'reasoning', strtolower( $generic ), 'User-facing failure message must not mention reasoning tokens' );
		$this->assertStringNotContainsString( 'token', strtolower( $generic ), 'User-facing failure message must not mention tokens' );
	}

	/**
	 * When all attempts return no visible output, response must be generic message not raw provider message.
	 * Uses pre_http_request to stub OpenAI to return incomplete/empty; asserts ai.output never contains "reasoning tokens".
	 * Requires WordPress (run within WP test suite).
	 */
	public function test_incomplete_responses_yield_generic_message_not_raw_reasoning_text(): void {
		if ( ! function_exists( 'add_filter' ) || ! function_exists( 'get_option' ) || ! function_exists( 'wp_json_encode' ) ) {
			$this->markTestSkipped( 'Requires WordPress (run within WP test suite).' );
		}

		$calls = 0;
		$stub_openai = function( $preempt, $parsed_args, $url ) use ( &$calls ) {
			if ( strpos( $url, 'api.openai.com' ) === false ) {
				return $preempt;
			}
			$calls++;
			$body = isset( $parsed_args['body'] ) ? json_decode( $parsed_args['body'], true ) : [];
			$is_responses = isset( $body['instructions'] ) && isset( $body['input'] );
			if ( $is_responses ) {
				$response_body = [
					'output' => [],
					'status' => 'incomplete',
				];
			} else {
				$response_body = [
					'choices' => [
						[
							'message' => [ 'content' => '' ],
							'finish_reason' => 'length',
						],
					],
					'usage' => [
						'completion_tokens' => 100,
						'completion_tokens_details' => [ 'reasoning_tokens' => 95 ],
					],
				];
			}
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( $response_body ),
			];
		};

		add_filter( 'pre_http_request', $stub_openai, 10, 3 );

		// Ensure OpenAI is selected and model is gpt-5.2 so we use Responses API.
		add_filter( 'pre_option_contextualwp_settings', function( $value ) {
			return [
				'ai_provider'           => 'OpenAI',
				'model'                 => 'gpt-5.2',
				'api_key'               => 'sk-test-dummy',
				'max_tokens'            => 1024,
				'temperature'           => 0.7,
				'smart_model_selection' => false,
			];
		}, 10, 1 );

		try {
			$request = new WP_REST_Request( 'POST', '/contextualwp/v1/generate_context' );
			$request->set_param( 'context_id', 'multi' );
			$request->set_param( 'prompt', 'Suggest improvements.' );
			$request->set_param( 'format', 'markdown' );

			$controller = new Generate_Context();
			$controller->register_route();
			$response = $controller->handle_request( $request );
		} finally {
			remove_filter( 'pre_http_request', $stub_openai, 10 );
			remove_filter( 'pre_option_contextualwp_settings', 10 );
		}

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'ai', $response );
		$this->assertIsArray( $response['ai'] );
		$this->assertArrayHasKey( 'output', $response['ai'] );
		$output = (string) $response['ai']['output'];

		// Must never show raw "reasoning tokens" message to user.
		$this->assertStringNotContainsString( 'reasoning tokens', strtolower( $output ), 'Must not surface raw reasoning token exhaustion message' );
		$this->assertStringNotContainsString( 'Try a shorter question', $output, 'Must not surface old reasoning-token UX message' );

		// Should be the generic failure message when all attempts fail.
		$this->assertStringContainsString( "Couldn't generate a response", $output, 'Should show generic failure message when all attempts fail' );
	}

}
