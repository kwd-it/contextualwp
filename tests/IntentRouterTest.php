<?php
/**
 * Golden/fixture tests for schema intent router (ACF-by-post-type, generic overview, unknown post type).
 *
 * @package ContextualWP\Tests
 * @since 0.6.3
 */

namespace ContextualWP\Tests;

use ContextualWP\Endpoints\Generate_Context;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test class for intent router and structure answer output stability.
 */
class IntentRouterTest extends TestCase {

    /**
     * Minimal schema fixture for deterministic tests.
     *
     * @var array
     */
    private static $fixture_schema = [
        'post_types'    => [
            [ 'slug' => 'plots', 'label' => 'Plots' ],
            [ 'slug' => 'developments', 'label' => 'Developments' ],
        ],
        'taxonomies'    => [
            [ 'slug' => 'plot_status', 'label' => 'Plot Status', 'object_types' => [ 'plots' ] ],
        ],
        'acf_field_groups' => [
            [
                'title'    => 'Plot Fields',
                'key'      => 'group_plot',
                'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'plots' ] ] ],
                'fields'   => [
                    [ 'label' => 'Plot Name', 'name' => 'plot_name', 'type' => 'text', 'key' => 'field_plot_name' ],
                ],
            ],
            [
                'title'    => 'Development Hero',
                'key'      => 'group_dev',
                'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'developments' ] ] ],
                'fields'   => [],
            ],
        ],
        'generated_at'  => '2026-01-28T12:00:00+00:00',
    ];

    /**
     * Invoke private build_structure_answer on Generate_Context with fixture schema.
     *
     * @param string $prompt User prompt.
     * @return string Full structure answer (body + footer).
     */
    private function build_structure_answer_for_fixture( $prompt ) {
        $reflection = new ReflectionClass( Generate_Context::class );
        $method = $reflection->getMethod( 'build_structure_answer' );
        $method->setAccessible( true );
        $instance = new Generate_Context();
        return $method->invoke( $instance, self::$fixture_schema, $prompt );
    }

    /**
     * Invoke private get_schema_intent.
     *
     * @param string $prompt User prompt.
     * @return array Intent data.
     */
    private function get_schema_intent_for_fixture( $prompt ) {
        $reflection = new ReflectionClass( Generate_Context::class );
        $method = $reflection->getMethod( 'get_schema_intent' );
        $method->setAccessible( true );
        $instance = new Generate_Context();
        return $method->invoke( $instance, $prompt, self::$fixture_schema );
    }

    /**
     * Assert that "Source: schema (generated at …)" appears exactly once in the output.
     *
     * @param string $output Full structure answer.
     */
    private function assertSingleSchemaFooter( $output ) {
        $count = substr_count( $output, 'Source: schema (generated at ' );
        $this->assertSame( 1, $count, 'Expected exactly one "Source: schema (generated at …)" line in output.' );
    }

    /**
     * Intent: ACF-by-post-type. Query like "ACF for plots" → only matched field groups for that post type; one footer.
     */
    public function test_intent_acf_by_post_type_output_stable() {
        $prompt = 'ACF for plots';
        $intent = $this->get_schema_intent_for_fixture( $prompt );
        $this->assertSame( 'acf_by_post_type', $intent['intent'] );
        $this->assertSame( 'plots', $intent['post_type_slug'] );

        $output = $this->build_structure_answer_for_fixture( $prompt );

        $this->assertStringContainsString( 'ACF Field Groups for "plots"', $output );
        $this->assertStringContainsString( 'Plot Fields', $output );
        $this->assertStringContainsString( 'Plot Name', $output );
        $this->assertStringContainsString( 'plot_name', $output );
        $this->assertStringNotContainsString( 'Development Hero', $output, 'Must not include field groups for other post types.' );
        $this->assertSingleSchemaFooter( $output );
        $this->assertStringContainsString( 'Source: schema (generated at 2026-01-28T12:00:00+00:00).', $output );
    }

    /**
     * Intent: Generic schema overview. Query like "What CPTs are on this site?" → CPTs + taxonomies; one footer.
     */
    public function test_intent_generic_overview_output_stable() {
        $prompt = 'What CPTs are on this site?';
        $intent = $this->get_schema_intent_for_fixture( $prompt );
        $this->assertSame( 'generic_schema_overview', $intent['intent'] );

        $output = $this->build_structure_answer_for_fixture( $prompt );

        $this->assertStringContainsString( 'Custom Post Types', $output );
        $this->assertStringContainsString( 'plots', $output );
        $this->assertStringContainsString( 'developments', $output );
        $this->assertStringContainsString( 'Custom Taxonomies', $output );
        $this->assertStringContainsString( 'plot_status', $output );
        $this->assertSingleSchemaFooter( $output );
    }

    /**
     * Intent: Unknown post type. User asks ACF-by-post-type but post type cannot be resolved → helpful message + list available.
     */
    public function test_intent_unknown_post_type_output_stable() {
        $prompt = 'List ACF assigned to widget cpt';
        $intent = $this->get_schema_intent_for_fixture( $prompt );
        $this->assertSame( 'unknown_post_type', $intent['intent'] );
        $this->assertNotNull( $intent['requested_slug'] );

        $output = $this->build_structure_answer_for_fixture( $prompt );

        $this->assertStringContainsString( 'could not be found', $output );
        $this->assertStringContainsString( 'Available post types:', $output );
        $this->assertStringContainsString( 'plots', $output );
        $this->assertStringContainsString( 'developments', $output );
        $this->assertSingleSchemaFooter( $output );
    }

    /**
     * Output is deterministic: same prompt + same schema produces identical output.
     */
    public function test_output_deterministic() {
        $prompt = 'ACF for plots';
        $first  = $this->build_structure_answer_for_fixture( $prompt );
        $second = $this->build_structure_answer_for_fixture( $prompt );
        $this->assertSame( $first, $second );
    }
}
