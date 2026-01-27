<?php
/**
 * PHPUnit tests for ACF post type query intent detection and filtering
 *
 * @package ContextualWP\Tests
 * @since 0.6.2
 */

namespace ContextualWP\Tests;

use ContextualWP\Endpoints\Generate_Context;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Test class for ACF post type query functionality
 */
class ACFPostTypeQueryTest extends TestCase {

    /**
     * Reflection class instance for accessing private methods
     *
     * @var ReflectionClass
     */
    private $reflection;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        
        // Create reflection to access private methods
        $this->reflection = new ReflectionClass( Generate_Context::class );
    }

    /**
     * Invoke a private method
     *
     * @param string $method_name Method name
     * @param array  $args Method arguments
     * @return mixed Method return value
     */
    private function invoke_private_method( $method_name, $args = [] ) {
        $method = $this->reflection->getMethod( $method_name );
        $method->setAccessible( true );
        $instance = new Generate_Context();
        return $method->invokeArgs( $instance, $args );
    }

    /**
     * Test post type extraction with various patterns
     *
     * @dataProvider postTypeExtractionProvider
     * @param string $prompt User prompt
     * @param array  $available_slugs Available post type slugs
     * @param string|null $expected Expected post type slug or null
     * @param string $description Test description
     */
    public function test_extract_post_type_for_acf( $prompt, $available_slugs, $expected, $description ) {
        $result = $this->invoke_private_method( 'extract_requested_post_type_for_acf', [ $prompt, $available_slugs ] );
        $this->assertEquals(
            $expected,
            $result,
            "Failed for: {$description} - Prompt: '{$prompt}', Expected: " . ( $expected ?? 'null' ) . ", Got: " . ( $result ?? 'null' )
        );
    }

    /**
     * Data provider for post type extraction tests
     *
     * @return array Array of [prompt, available_slugs, expected, description]
     */
    public function postTypeExtractionProvider() {
        return [
            [
                'List all acf assigned to plot cpt',
                [ 'plots', 'developments' ],
                'plots',
                'Pattern: "assigned to plot cpt"'
            ],
            [
                'ACF for plots',
                [ 'plots', 'developments' ],
                'plots',
                'Pattern: "ACF for plots"'
            ],
            [
                'Show ACF field groups for plots',
                [ 'plots', 'developments' ],
                'plots',
                'Pattern: "Show ACF field groups for plots"'
            ],
            [
                'acf assigned to post type plots',
                [ 'plots', 'developments' ],
                'plots',
                'Pattern: "assigned to post type plots"'
            ],
            [
                'ACF for plot',
                [ 'plots', 'developments' ],
                'plots',
                'Singular "plot" should match "plots" slug'
            ],
            [
                'field groups for developments',
                [ 'plots', 'developments' ],
                'developments',
                'Pattern: "field groups for developments"'
            ],
            [
                'plots ACF',
                [ 'plots', 'developments' ],
                'plots',
                'Pattern: "plots ACF"'
            ],
            [
                'ACF plots',
                [ 'plots', 'developments' ],
                'plots',
                'Pattern: "ACF plots"'
            ],
            [
                'What are the ACF fields?',
                [ 'plots', 'developments' ],
                null,
                'No post type specified'
            ],
            [
                'ACF for unknown_type',
                [ 'plots', 'developments' ],
                null,
                'Unknown post type should return null'
            ],
        ];
    }

    /**
     * Test block detection
     *
     * @dataProvider blockDetectionProvider
     * @param string $prompt User prompt
     * @param bool   $expected Expected result
     * @param string $description Test description
     */
    public function test_blocks_requested( $prompt, $expected, $description ) {
        $result = $this->invoke_private_method( 'blocks_requested', [ $prompt ] );
        $this->assertEquals(
            $expected,
            $result,
            "Failed for: {$description} - Prompt: '{$prompt}'"
        );
    }

    /**
     * Data provider for block detection tests
     *
     * @return array Array of [prompt, expected, description]
     */
    public function blockDetectionProvider() {
        return [
            [
                'ACF for plots and include blocks',
                true,
                'Pattern: "and include blocks"'
            ],
            [
                'ACF blocks for plots',
                true,
                'Pattern: "blocks for"'
            ],
            [
                'Show ACF field groups for plots with blocks',
                true,
                'Pattern: "with blocks"'
            ],
            [
                'ACF for plots',
                false,
                'No block mention'
            ],
            [
                'List all acf assigned to plot cpt',
                false,
                'No block mention'
            ],
        ];
    }

    /**
     * Test block group detection
     *
     * @dataProvider blockGroupDetectionProvider
     * @param array  $group ACF field group structure
     * @param string $post_type_slug Post type slug to match
     * @param bool   $expected Expected result
     * @param string $description Test description
     */
    public function test_is_block_group( $group, $post_type_slug, $expected, $description ) {
        $result = $this->invoke_private_method( 'is_block_group', [ $group, $post_type_slug ] );
        $this->assertEquals(
            $expected,
            $result,
            "Failed for: {$description}"
        );
    }

    /**
     * Data provider for block group detection tests
     *
     * @return array Array of [group, post_type_slug, expected, description]
     */
    public function blockGroupDetectionProvider() {
        return [
            [
                [
                    'location' => [
                        [
                            [
                                'param' => 'block',
                                'operator' => '==',
                                'value' => 'acf/plot-hero',
                            ],
                        ],
                    ],
                ],
                'plots',
                true,
                'Block group with matching post type in value'
            ],
            [
                [
                    'title' => 'Block: Plot Hero',
                    'location' => [
                        [
                            [
                                'param' => 'block',
                                'operator' => '==',
                                'value' => 'acf/hero',
                            ],
                        ],
                    ],
                ],
                'plots',
                true,
                'Block group with matching post type in title'
            ],
            [
                [
                    'location' => [
                        [
                            [
                                'param' => 'post_type',
                                'operator' => '==',
                                'value' => 'plots',
                            ],
                        ],
                    ],
                ],
                'plots',
                false,
                'Post type group, not a block group'
            ],
            [
                [
                    'location' => [
                        [
                            [
                                'param' => 'block',
                                'operator' => '==',
                                'value' => 'acf/other-block',
                            ],
                        ],
                    ],
                ],
                'plots',
                true,
                'Any block group (when post_type_slug is empty or doesn\'t match)'
            ],
        ];
    }

    /**
     * Test ACF groups filtering for post type
     *
     * @dataProvider acfGroupsFilteringProvider
     * @param array  $groups ACF field groups
     * @param string $post_type Post type slug
     * @param bool   $include_blocks Whether to include blocks
     * @param int    $expected_count Expected number of groups
     * @param string $description Test description
     */
    public function test_acf_groups_for_post_type( $groups, $post_type, $include_blocks, $expected_count, $description ) {
        $result = $this->invoke_private_method( 'acf_groups_for_post_type', [ $groups, $post_type, $include_blocks ] );
        $this->assertCount(
            $expected_count,
            $result,
            "Failed for: {$description}"
        );
    }

    /**
     * Data provider for ACF groups filtering tests
     *
     * @return array Array of [groups, post_type, include_blocks, expected_count, description]
     */
    public function acfGroupsFilteringProvider() {
        return [
            [
                [
                    [
                        'title' => 'Plot Fields',
                        'key' => 'group_plot',
                        'location' => [
                            [
                                [
                                    'param' => 'post_type',
                                    'operator' => '==',
                                    'value' => 'plots',
                                ],
                            ],
                        ],
                        'fields' => [
                            [ 'label' => 'Plot Name', 'name' => 'plot_name', 'type' => 'text' ],
                        ],
                    ],
                ],
                'plots',
                false,
                1,
                'Single matching post type group'
            ],
            [
                [
                    [
                        'title' => 'Plot Block',
                        'location' => [
                            [
                                [
                                    'param' => 'block',
                                    'operator' => '==',
                                    'value' => 'acf/plot-hero',
                                ],
                            ],
                        ],
                        'fields' => [],
                    ],
                ],
                'plots',
                false,
                0,
                'Block group excluded when include_blocks is false'
            ],
            [
                [
                    [
                        'title' => 'Plot Block',
                        'location' => [
                            [
                                [
                                    'param' => 'block',
                                    'operator' => '==',
                                    'value' => 'acf/plot-hero',
                                ],
                            ],
                        ],
                        'fields' => [],
                    ],
                ],
                'plots',
                true,
                1,
                'Block group included when include_blocks is true'
            ],
            [
                [
                    [
                        'title' => 'Plot Fields',
                        'location' => [
                            [
                                [
                                    'param' => 'post_type',
                                    'operator' => '==',
                                    'value' => 'plots',
                                ],
                            ],
                        ],
                        'fields' => [],
                    ],
                    [
                        'title' => 'Development Fields',
                        'location' => [
                            [
                                [
                                    'param' => 'post_type',
                                    'operator' => '==',
                                    'value' => 'developments',
                                ],
                            ],
                        ],
                        'fields' => [],
                    ],
                ],
                'plots',
                false,
                1,
                'Multiple groups, only one matches'
            ],
        ];
    }
}
