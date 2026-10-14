<?php
/**
 * PHPUnit tests for the Anthropic (Claude) provider: the visible answer is the first
 * text content block, whether or not thinking blocks precede it.
 *
 * @package ContextualWP\Tests
 */

namespace ContextualWP\Tests;

use ContextualWP\Endpoints\Generate_Context;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for Claude Messages API response parsing.
 */
class ClaudeProviderTest extends TestCase {

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
	 * Decoded body of a normal Messages API response (text block first).
	 *
	 * @param string $text Text block content.
	 * @return array
	 */
	private function text_first_response( $text = 'Hello from Claude.' ) {
		return [
			'id'          => 'msg_01',
			'type'        => 'message',
			'role'        => 'assistant',
			'model'       => 'claude-sonnet-4-6',
			'content'     => [
				[ 'type' => 'text', 'text' => $text ],
			],
			'stop_reason' => 'end_turn',
		];
	}

	/**
	 * Current models: the first block is text and is returned unchanged.
	 */
	public function test_normal_response_returns_first_text_block(): void {
		$this->assertSame( 'Hello from Claude.', $this->invoke_private( 'extract_claude_output_text', [ $this->text_first_response() ] ) );
	}

	/**
	 * Newer models can return thinking before text: the text block is still the answer
	 * and the thinking content is never exposed.
	 */
	public function test_thinking_block_before_text_is_skipped(): void {
		$data = [
			'content' => [
				[ 'type' => 'thinking', 'thinking' => 'Let me reason about this.', 'signature' => 'sig' ],
				[ 'type' => 'redacted_thinking', 'data' => 'opaque' ],
				[ 'type' => 'text', 'text' => 'The visible answer.' ],
			],
		];
		$output = $this->invoke_private( 'extract_claude_output_text', [ $data ] );
		$this->assertSame( 'The visible answer.', $output );
		$this->assertStringNotContainsString( 'reason', $output );
	}

	/**
	 * Several text blocks: the first non-empty one is used, as before.
	 */
	public function test_first_non_empty_text_block_wins(): void {
		$data = [
			'content' => [
				[ 'type' => 'text', 'text' => '   ' ],
				[ 'type' => 'text', 'text' => 'First real text.' ],
				[ 'type' => 'text', 'text' => 'Second text.' ],
			],
		];
		$this->assertSame( 'First real text.', $this->invoke_private( 'extract_claude_output_text', [ $data ] ) );
	}

	/**
	 * The shape accepted before this change (a block with text and no type) still works.
	 */
	public function test_untyped_block_with_text_is_still_accepted(): void {
		$data = [ 'content' => [ [ 'text' => 'Legacy shape.' ] ] ];
		$this->assertSame( 'Legacy shape.', $this->invoke_private( 'extract_claude_output_text', [ $data ] ) );
	}

	/**
	 * Thinking only, empty content, missing content, malformed blocks and non-string text
	 * all yield null so the caller returns a WP_Error instead of exposing anything.
	 */
	public function test_responses_without_visible_text_yield_null(): void {
		$cases = [
			'thinking only'     => [ 'content' => [ [ 'type' => 'thinking', 'thinking' => 'Only thoughts.' ] ] ],
			'empty content'     => [ 'content' => [] ],
			'missing content'   => [ 'id' => 'msg_01' ],
			'content not array' => [ 'content' => 'text' ],
			'block not array'   => [ 'content' => [ 'text' ] ],
			'non-string text'   => [ 'content' => [ [ 'type' => 'text', 'text' => [ 'nested' ] ] ] ],
			'whitespace text'   => [ 'content' => [ [ 'type' => 'text', 'text' => "\n\t " ] ] ],
			'tool use only'     => [ 'content' => [ [ 'type' => 'tool_use', 'id' => 'tu_1', 'name' => 'x', 'input' => [] ] ] ],
			'not an array'      => null,
		];
		foreach ( $cases as $label => $data ) {
			$this->assertNull( $this->invoke_private( 'extract_claude_output_text', [ $data ] ), $label );
		}
	}

	/**
	 * A thinking block whose own payload contains a "text" key is not mistaken for text.
	 */
	public function test_thinking_block_with_text_key_is_not_used(): void {
		$data = [
			'content' => [
				[ 'type' => 'thinking', 'thinking' => 'hidden', 'text' => 'should not leak' ],
				[ 'type' => 'text', 'text' => 'Visible.' ],
			],
		];
		$this->assertSame( 'Visible.', $this->invoke_private( 'extract_claude_output_text', [ $data ] ) );
	}
}
