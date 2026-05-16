<?php
/**
 * @package ContextualWP\Tests
 */

namespace ContextualWP\Tests {

use ContextualWP\Helpers\Utilities;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ContextualWP\Helpers\Utilities::get_safe_modified_author_display_name
 */
class SafeModifiedAuthorDisplayNameTest extends TestCase {

	/** @var callable|null */
	public static $get_post_callback;

	/** @var callable|null */
	public static $get_post_meta_callback;

	/** @var callable|null */
	public static $get_userdata_callback;

	protected function setUp(): void {
		parent::setUp();
		if ( function_exists( 'get_post' ) && ! defined( 'CONTEXTUALWP_TEST_GET_POST_STUB' ) ) {
			$this->markTestSkipped( 'WordPress get_post is already defined.' );
		}
		if ( function_exists( 'get_post_meta' ) && ! defined( 'CONTEXTUALWP_TEST_GET_POST_META_STUB' ) ) {
			$this->markTestSkipped( 'WordPress get_post_meta is already defined.' );
		}
		self::$get_post_callback      = null;
		self::$get_post_meta_callback = null;
		self::$get_userdata_callback  = null;
	}

	protected function tearDown(): void {
		self::$get_post_callback      = null;
		self::$get_post_meta_callback = null;
		self::$get_userdata_callback  = null;
		parent::tearDown();
	}

	public function test_returns_null_for_invalid_post_id(): void {
		self::$get_post_callback = static function () {
			return null;
		};

		$this->assertNull( Utilities::get_safe_modified_author_display_name( 0 ) );
		$this->assertNull( Utilities::get_safe_modified_author_display_name( -1 ) );
		$this->assertNull( Utilities::get_safe_modified_author_display_name( 999 ) );
	}

	public function test_returns_null_for_non_post_argument(): void {
		$this->assertNull( Utilities::get_safe_modified_author_display_name( 'not-a-post' ) );
		$this->assertNull( Utilities::get_safe_modified_author_display_name( new \stdClass() ) );
	}

	public function test_returns_null_when_edit_last_meta_missing(): void {
		$post = $this->make_post( 42 );
		self::$get_post_meta_callback = static function ( $post_id, $key ) {
			return ( '_edit_last' === $key && 42 === (int) $post_id ) ? '' : '';
		};

		$this->assertNull( Utilities::get_safe_modified_author_display_name( $post ) );
	}

	public function test_returns_null_when_user_cannot_be_resolved(): void {
		$post = $this->make_post( 5 );
		$this->stub_edit_last( 5, 9 );
		self::$get_userdata_callback = static function () {
			return false;
		};

		$this->assertNull( Utilities::get_safe_modified_author_display_name( $post ) );
	}

	public function test_returns_sanitized_display_name_for_post_object(): void {
		$post = $this->make_post( 7 );
		$this->stub_edit_last( 7, 3 );
		self::$get_userdata_callback = static function ( $user_id ) {
			return (object) [
				'display_name' => ( 3 === (int) $user_id ) ? '  <b>Jane Editor</b>  ' : '',
			];
		};

		$this->assertSame( 'Jane Editor', Utilities::get_safe_modified_author_display_name( $post ) );
	}

	public function test_accepts_numeric_post_id(): void {
		$post = $this->make_post( 12 );
		self::$get_post_callback = static function ( $post_id ) use ( $post ) {
			return (int) $post_id === 12 ? $post : null;
		};
		$this->stub_edit_last( 12, 4 );
		self::$get_userdata_callback = static function () {
			return (object) [ 'display_name' => 'Sam Contributor' ];
		};

		$this->assertSame( 'Sam Contributor', Utilities::get_safe_modified_author_display_name( 12 ) );
	}

	public function test_returns_null_when_label_looks_like_email(): void {
		$post = $this->make_post( 3 );
		$this->stub_edit_last( 3, 2 );
		self::$get_userdata_callback = static function () {
			return (object) [ 'display_name' => 'editor@example.com' ];
		};

		$this->assertNull( Utilities::get_safe_modified_author_display_name( $post ) );
	}

	private function make_post( int $id ): \WP_Post {
		return new \WP_Post( (object) [ 'ID' => $id ] );
	}

	private function stub_edit_last( int $post_id, int $user_id ): void {
		self::$get_post_meta_callback = static function ( $queried_post_id, $key ) use ( $post_id, $user_id ) {
			if ( '_edit_last' !== $key || (int) $queried_post_id !== $post_id ) {
				return '';
			}
			return (string) $user_id;
		};
	}
}

}

namespace {

	if ( ! class_exists( 'WP_Post', false ) ) {
		class WP_Post {
			/** @var int */
			public $ID;

			/** @param object|array $post */
			public function __construct( $post ) {
				$post     = (object) $post;
				$this->ID = (int) $post->ID;
			}
		}
	}

	if ( ! function_exists( 'get_post' ) ) {
		function get_post( $post = null ) {
			$callback = \ContextualWP\Tests\SafeModifiedAuthorDisplayNameTest::$get_post_callback;
			return $callback ? $callback( $post ) : null;
		}
		define( 'CONTEXTUALWP_TEST_GET_POST_STUB', true );
	}

	if ( ! function_exists( 'get_post_meta' ) ) {
		function get_post_meta( $post_id, $key, $single = false ) {
			unset( $single );
			$callback = \ContextualWP\Tests\SafeModifiedAuthorDisplayNameTest::$get_post_meta_callback;
			return $callback ? $callback( $post_id, $key ) : '';
		}
		define( 'CONTEXTUALWP_TEST_GET_POST_META_STUB', true );
	}

	if ( ! function_exists( 'get_userdata' ) ) {
		function get_userdata( $user_id ) {
			$callback = \ContextualWP\Tests\SafeModifiedAuthorDisplayNameTest::$get_userdata_callback;
			return $callback ? $callback( $user_id ) : false;
		}
	}

	if ( ! function_exists( 'wp_strip_all_tags' ) ) {
		function wp_strip_all_tags( $string ) {
			return strip_tags( (string) $string );
		}
	}

	if ( ! function_exists( 'is_email' ) ) {
		function is_email( $email ) {
			return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
		}
	}
}
