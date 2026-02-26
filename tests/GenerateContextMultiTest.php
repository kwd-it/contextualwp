<?php
/**
 * PHPUnit tests for multi-context content in generate_context endpoint.
 * Ensures context_id=multi uses rendered post/page body (the_content path), not schema/ACF/inventory.
 *
 * @package ContextualWP\Tests
 */

namespace ContextualWP\Tests;

use ContextualWP\Endpoints\Generate_Context;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_REST_Request;

/**
 * Tests that multi-context context.content contains real rendered copy and no schema/inventory.
 */
class GenerateContextMultiTest extends TestCase {

	/**
	 * Force WP_Query used by build_multi_context_aggregated_content to return only these IDs.
	 * Set by test, read in pre_get_posts filter.
	 *
	 * @var array<int>|null
	 */
	public static $override_post_ids = null;

	/**
	 * Hook into pre_get_posts so the multi-context query returns only our test posts.
	 *
	 * @param \WP_Query $query Query object.
	 */
	public static function force_test_posts( $query ) {
		if ( self::$override_post_ids !== null && is_array( self::$override_post_ids ) ) {
			$query->set( 'post__in', self::$override_post_ids );
			$query->set( 'posts_per_page', 5 );
			$query->set( 'orderby', 'post__in' );
		}
	}

	/**
	 * Build multi-context aggregated content by invoking the protected method.
	 *
	 * @param string $format Format: markdown, plain, html.
	 * @return string
	 */
	private function build_multi_context_content( $format = 'markdown' ) {
		$request = new WP_REST_Request( 'POST', '/contextualwp/v1/generate_context' );
		$request->set_param( 'format', $format );
		$reflection = new ReflectionClass( Generate_Context::class );
		$method = $reflection->getMethod( 'build_multi_context_aggregated_content' );
		$method->setAccessible( true );
		$instance = new Generate_Context();
		return $method->invoke( $instance, $format, $request );
	}

	/**
	 * Create a published post with the given title and content.
	 *
	 * @param string $title   Post title.
	 * @param string $content Post content (can be block markup).
	 * @param string $type    Post type: post or page.
	 * @return int Post ID.
	 */
	private function create_post( $title, $content, $type = 'post' ) {
		$id = wp_insert_post( [
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => $type,
		], true );
		if ( is_wp_error( $id ) ) {
			$this->markTestSkipped( 'Could not create test post: ' . $id->get_error_message() );
		}
		return $id;
	}

	/**
	 * Multi-context must contain actual body text from posts/pages (rendered via the_content), not schema or inventory.
	 */
	public function test_multi_context_contains_rendered_body_text_from_posts(): void {
		if ( ! function_exists( 'apply_filters' ) || ! function_exists( 'wp_insert_post' ) ) {
			$this->markTestSkipped( 'Requires WordPress. Run within WP test suite or skip.' );
		}

		$body1 = 'Alpha body copy for multi context test ' . wp_rand( 10000, 99999 );
		$body2 = 'Beta body copy for multi context test ' . wp_rand( 10000, 99999 );
		$id1 = $this->create_post( 'Multi Test Alpha', "<!-- wp:paragraph -->\n<p>{$body1}</p>\n<!-- /wp:paragraph -->", 'post' );
		$id2 = $this->create_post( 'Multi Test Beta', "<!-- wp:paragraph -->\n<p>{$body2}</p>\n<!-- /wp:paragraph -->", 'page' );

		self::$override_post_ids = [ $id1, $id2 ];
		add_filter( 'pre_get_posts', [ self::class, 'force_test_posts' ] );

		try {
			$content = $this->build_multi_context_content( 'markdown' );
		} finally {
			remove_filter( 'pre_get_posts', [ self::class, 'force_test_posts' ] );
			self::$override_post_ids = null;
		}

		$this->assertStringContainsString( $body1, $content, 'Multi context must include actual body text from first post (rendered via the_content)' );
		$this->assertStringContainsString( $body2, $content, 'Multi context must include actual body text from second post/page (rendered via the_content)' );
		$this->assertStringContainsString( 'Multi Test Alpha', $content );
		$this->assertStringContainsString( 'Multi Test Beta', $content );
		$this->assertStringContainsString( "post-{$id1}", $content, 'Stable identifier post-{id} must appear' );
		$this->assertStringContainsString( "page-{$id2}", $content, 'Stable identifier page-{id} must appear' );
	}

	/**
	 * Multi-context must NOT contain schema/inventory keys (acf_field_groups, post_types, taxonomies, generated_at).
	 */
	public function test_multi_context_does_not_contain_schema_or_inventory(): void {
		if ( ! function_exists( 'apply_filters' ) ) {
			$this->markTestSkipped( 'Requires WordPress. Run within WP test suite or skip.' );
		}

		$content = $this->build_multi_context_content( 'markdown' );

		// If no posts exist, content is empty; skip assertions that require content.
		if ( $content === '' ) {
			$this->assertSame( '', $content );
			return;
		}

		$this->assertStringNotContainsString( 'acf_field_groups', $content, 'Multi context must not include schema key acf_field_groups' );
		$this->assertStringNotContainsString( 'post_types', $content, 'Multi context must not include schema/inventory key post_types' );
		$this->assertStringNotContainsString( 'taxonomies', $content, 'Multi context must not include schema key taxonomies' );
		$this->assertStringNotContainsString( 'generated_at', $content, 'Multi context must not include schema key generated_at' );
		$this->assertStringNotContainsString( "\nACF:\n", $content, 'Multi context must not include ACF summary block from old implementation' );
	}

	/**
	 * Multi-context output has expected structure: ## Label: Title (type-id) and --- separators.
	 */
	public function test_multi_context_has_expected_structure(): void {
		if ( ! function_exists( 'apply_filters' ) ) {
			$this->markTestSkipped( 'Requires WordPress. Run within WP test suite or skip.' );
		}

		$content = $this->build_multi_context_content( 'markdown' );
		if ( $content === '' ) {
			$this->assertSame( '', $content );
			return;
		}

		$this->assertStringContainsString( '## ', $content, 'Multi context must use ## header per item' );
		$this->assertMatchesRegularExpression( '/\(post-\d+\)|\(page-\d+\)/', $content, 'Stable identifiers (post-N) or (page-N) must appear' );
		$this->assertStringContainsString( "\n---\n", $content, 'Items must be separated by ---' );
	}

	/**
	 * Empty rendered content for an item shows "No content found." and is not dropped.
	 */
	public function test_multi_context_includes_empty_items_as_no_content_found(): void {
		if ( ! function_exists( 'apply_filters' ) || ! function_exists( 'wp_insert_post' ) ) {
			$this->markTestSkipped( 'Requires WordPress. Run within WP test suite or skip.' );
		}

		$id = $this->create_post( 'Empty Multi Test', '', 'post' );
		self::$override_post_ids = [ $id ];
		add_filter( 'pre_get_posts', [ self::class, 'force_test_posts' ] );

		try {
			$content = $this->build_multi_context_content( 'markdown' );
		} finally {
			remove_filter( 'pre_get_posts', [ self::class, 'force_test_posts' ] );
			self::$override_post_ids = null;
		}

		$this->assertStringContainsString( 'Empty Multi Test', $content );
		$this->assertStringContainsString( 'No content found', $content, 'Item with no body must show No content found., not be dropped' );
		$this->assertStringContainsString( "post-{$id}", $content );
	}

	/**
	 * Output is deterministic: same inputs produce same order (post_modified DESC, ID DESC).
	 */
	public function test_multi_context_ordering_is_stable(): void {
		if ( ! function_exists( 'apply_filters' ) || ! function_exists( 'wp_insert_post' ) ) {
			$this->markTestSkipped( 'Requires WordPress. Run within WP test suite or skip.' );
		}

		$id1 = $this->create_post( 'Order First', 'First body', 'post' );
		$id2 = $this->create_post( 'Order Second', 'Second body', 'post' );
		self::$override_post_ids = [ $id1, $id2 ];
		add_filter( 'pre_get_posts', [ self::class, 'force_test_posts' ] );

		try {
			$first  = $this->build_multi_context_content( 'markdown' );
			$second = $this->build_multi_context_content( 'markdown' );
		} finally {
			remove_filter( 'pre_get_posts', [ self::class, 'force_test_posts' ] );
			self::$override_post_ids = null;
		}

		$this->assertSame( $first, $second, 'Repeated build must produce identical output (deterministic ordering)' );
	}
}
