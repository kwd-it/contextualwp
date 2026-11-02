<?php
/**
 * PHPUnit tests for the Mistral provider: request payloads and the visible answer, whether
 * message.content is a plain string or a list of thinking and text chunks.
 *
 * @package ContextualWP\Tests
 */

namespace ContextualWP\Tests;

use ContextualWP\Endpoints\Generate_Context;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for Mistral chat completions payloads and response parsing.
 */
class MistralProviderTest extends TestCase {

	/**
	 * Invoke private method on Generate_Context.
	 *
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
	 * Decoded chat completions body with the given message content.
	 *
	 * @param mixed $content message.content value.
	 * @return array
	 */
	private function response_with_content( $content ) {
		return [
			'id'      => 'cmpl-1',
			'object'  => 'chat.completion',
			'model'   => 'mistral-large-4',
			'choices' => [
				[
					'index'         => 0,
					'message'       => [ 'role' => 'assistant', 'content' => $content ],
					'finish_reason' => 'stop',
				],
			],
		];
	}

	/**
	 * Non-reasoning output: a plain string is returned unchanged, as before.
	 */
	public function test_plain_string_content_is_returned_unchanged(): void {
		$this->assertSame( 'Hello from Mistral.', $this->invoke_private( 'extract_mistral_output_text', [ $this->response_with_content( 'Hello from Mistral.' ) ] ) );
		$this->assertSame( '', $this->invoke_private( 'extract_mistral_output_text', [ $this->response_with_content( '' ) ] ) );
	}

	/**
	 * Reasoning output: thinking chunks are skipped and only the text chunk is returned.
	 */
	public function test_thinking_chunk_before_text_is_skipped(): void {
		$content = [
			[
				'type'     => 'thinking',
				'thinking' => [ [ 'type' => 'text', 'text' => 'Let me reason about this.' ] ],
			],
			[ 'type' => 'text', 'text' => 'The visible answer.' ],
		];
		$output = $this->invoke_private( 'extract_mistral_output_text', [ $this->response_with_content( $content ) ] );
		$this->assertSame( 'The visible answer.', $output );
		$this->assertStringNotContainsString( 'reason', $output );
	}

	/**
	 * An answer split over several text chunks is joined in order.
	 */
	public function test_multiple_text_chunks_are_joined_in_order(): void {
		$content = [
			[ 'type' => 'thinking', 'thinking' => [ [ 'type' => 'text', 'text' => 'Hidden.' ] ] ],
			[ 'type' => 'text', 'text' => 'First part, ' ],
			[ 'type' => 'text', 'text' => 'second part.' ],
		];
		$this->assertSame( 'First part, second part.', $this->invoke_private( 'extract_mistral_output_text', [ $this->response_with_content( $content ) ] ) );
	}

	/**
	 * Only thinking, malformed chunks or no content: null, so the caller returns a WP_Error
	 * instead of exposing the reasoning trace or a non-string value.
	 */
	public function test_responses_without_text_yield_null(): void {
		$only_thinking = [ [ 'type' => 'thinking', 'thinking' => [ [ 'type' => 'text', 'text' => 'Ran out of tokens.' ] ] ] ];
		$this->assertNull( $this->invoke_private( 'extract_mistral_output_text', [ $this->response_with_content( $only_thinking ) ] ) );
		$this->assertNull( $this->invoke_private( 'extract_mistral_output_text', [ $this->response_with_content( [ 'not-a-chunk', [ 'type' => 'text', 'text' => [ 'nested' ] ] ] ) ] ) );
		$this->assertNull( $this->invoke_private( 'extract_mistral_output_text', [ $this->response_with_content( [ [ 'type' => 'text', 'text' => '   ' ] ] ) ] ) );
		$this->assertNull( $this->invoke_private( 'extract_mistral_output_text', [ $this->response_with_content( null ) ] ) );
		$this->assertNull( $this->invoke_private( 'extract_mistral_output_text', [ [] ] ) );
	}

	/**
	 * Mistral Large 4 is sent reasoning_effort "low"; the rest of the payload is unchanged.
	 */
	public function test_large_4_payload_sets_low_reasoning_effort(): void {
		$payload = $this->invoke_private( 'build_mistral_payload', [ 'mistral-large-4', 'System.', 'Prompt.', 1024, 0.7 ] );
		$this->assertSame(
			[
				'model'            => 'mistral-large-4',
				'messages'         => [
					[ 'role' => 'system', 'content' => 'System.' ],
					[ 'role' => 'user', 'content' => 'Prompt.' ],
				],
				'max_tokens'       => 1024,
				'temperature'      => 0.7,
				'reasoning_effort' => 'low',
			],
			$payload
		);
	}

	/**
	 * Other Mistral models, including saved legacy IDs, get the same payload as before.
	 */
	public function test_other_models_payload_has_no_reasoning_effort(): void {
		foreach ( [ 'mistral-small-2603', 'mistral-large-2512', 'mistral-medium-3-5' ] as $model ) {
			$payload = $this->invoke_private( 'build_mistral_payload', [ $model, 'System.', 'Prompt.', 1024, 1.0 ] );
			$this->assertSame(
				[
					'model'       => $model,
					'messages'    => [
						[ 'role' => 'system', 'content' => 'System.' ],
						[ 'role' => 'user', 'content' => 'Prompt.' ],
					],
					'max_tokens'  => 1024,
					'temperature' => 1.0,
				],
				$payload,
				"$model payload changed"
			);
		}
	}
}
