<?php
namespace ContextualWP\Tests;

use ContextualWP\Helpers\Schema_Interpretation;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ContextualWP\Helpers\Schema_Interpretation
 */
class SchemaInterpretationTest extends TestCase {

    public function test_build_returns_empty_without_acf_relationships_or_packs(): void {
        $schema = [
            'post_types'   => [ [ 'slug' => 'post', 'label' => 'Posts', 'taxonomies' => [] ] ],
            'taxonomies'   => [],
            'generated_at' => '2026-01-01T00:00:00+00:00',
        ];
        $this->assertSame( [], Schema_Interpretation::build( $schema ) );
    }

    public function test_build_includes_acf_block_when_acf_field_groups_present(): void {
        $schema = [
            'post_types'        => [ [ 'slug' => 'house', 'label' => 'Houses', 'taxonomies' => [] ] ],
            'acf_field_groups'  => [
                [
                    'title'  => 'Links',
                    'fields' => [
                        [
                            'label'     => 'Related plots',
                            'name'      => 'related_plots',
                            'type'      => 'relationship',
                            'post_type' => [ 'plot' ],
                        ],
                    ],
                ],
            ],
            'generated_at'      => '2026-01-01T00:00:00+00:00',
        ];
        $out = Schema_Interpretation::build( $schema );
        $this->assertArrayHasKey( 'contextualwp', $out );
        $this->assertArrayHasKey( 'acf', $out['contextualwp'] );
        $this->assertSame( 1, $out['contextualwp']['acf']['field_group_count'] );
        $this->assertNotEmpty( $out['contextualwp']['acf']['relationship_field_hints'] );
        $this->assertArrayHasKey( 'structured_data', $out['contextualwp']['acf'] );
    }
}
