<?php
/**
 * PHPUnit tests for single-context content formatting (block rendering via the_content).
 *
 * @package ContextualWP\Tests
 */

namespace ContextualWP\Tests;

use ContextualWP\Helpers\Utilities;
use PHPUnit\Framework\TestCase;

/**
 * Tests that context.content for single post/page uses rendered block output.
 */
class FormatContentTest extends TestCase {

	/**
	 * Test that format_content runs post_content through the_content filter.
	 * When WordPress is loaded, the_content is applied so blocks are rendered.
	 */
	public function test_format_content_uses_the_content_rendering_path(): void {
		if ( ! function_exists( 'apply_filters' ) ) {
			$this->markTestSkipped( 'Requires WordPress (apply_filters). Run within WP test suite or skip.' );
		}

		$marker   = ' RENDERED_VIA_THE_CONTENT_' . wp_rand( 10000, 99999 );
		$callback = function ( $content ) use ( $marker ) {
			return $content . $marker;
		};
		add_filter( 'the_content', $callback, 999 );

		$post   = $this->create_post_with_block_markup( 'Test title', '<!-- wp:paragraph --><p>Body copy</p><!-- /wp:paragraph -->' );
		$output = Utilities::format_content( $post, 'markdown' );

		remove_filter( 'the_content', $callback, 999 );

		$this->assertStringContainsString( $marker, $output, 'format_content must pass content through the_content filter' );
		$this->assertStringContainsString( 'Body copy', $output, 'context.content must include body text from blocks' );
		$this->assertStringContainsString( '## Test title', $output, 'context.content must include title' );
	}

	/**
	 * Test that block markup produces more than just the title in output.
	 */
	public function test_format_content_includes_more_than_title_for_block_content(): void {
		if ( ! function_exists( 'apply_filters' ) ) {
			$this->markTestSkipped( 'Requires WordPress. Run within WP test suite or skip.' );
		}

		$body_text = 'Expected body text from block content';
		$post      = $this->create_post_with_block_markup( 'Only a heading', "<!-- wp:paragraph -->\n<p>{$body_text}</p>\n<!-- /wp:paragraph -->" );
		$output    = Utilities::format_content( $post, 'markdown' );

		$this->assertStringContainsString( $body_text, $output, 'When post_content has blocks, context.content must include rendered body text, not only the title' );
		$this->assertStringContainsString( '## Only a heading', $output );
	}

	/**
	 * Test empty content returns a note instead of empty string (no fallback to multi).
	 */
	public function test_format_content_empty_after_render_returns_note(): void {
		if ( ! function_exists( 'apply_filters' ) ) {
			$this->markTestSkipped( 'Requires WordPress. Run within WP test suite or skip.' );
		}

		$post   = $this->create_post_with_block_markup( 'Empty Page', '' );
		$output = Utilities::format_content( $post, 'markdown' );

		$this->assertStringContainsString( '## Empty Page', $output );
		$this->assertStringContainsString( 'No content found', $output, 'Empty rendered content must yield a concise note, not empty body' );
	}

	/**
	 * Create a mock post object with block-style content (for use when WP is loaded).
	 *
	 * @param string $title   Post title.
	 * @param string $content Raw post_content (can contain block markup).
	 * @return \WP_Post
	 */
	private function create_post_with_block_markup( string $title, string $content ): \WP_Post {
		if ( ! function_exists( 'wp_insert_post' ) ) {
			$this->markTestSkipped( 'Requires WordPress.' );
		}

		$id = wp_insert_post( [
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'post',
		], true );

		if ( is_wp_error( $id ) ) {
			$this->markTestSkipped( 'Could not create test post: ' . $id->get_error_message() );
		}

		$post = get_post( $id );
		$this->assertInstanceOf( \WP_Post::class, $post );
		return $post;
	}
}
