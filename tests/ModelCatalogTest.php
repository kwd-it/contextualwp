<?php
namespace ContextualWP\Tests;

use ContextualWP\Helpers\Smart_Model_Selector;
use PHPUnit\Framework\TestCase;

/**
 * Model catalog tests: ensure new model IDs are present and legacy IDs remain valid.
 */
class ModelCatalogTest extends TestCase {

    public function test_default_agent_model_constant_matches_openai_flagship(): void {
        $this->assertSame( 'gpt-5.5', Smart_Model_Selector::DEFAULT_AGENT_MODEL );
        $this->assertSame( Smart_Model_Selector::DEFAULT_AGENT_MODEL, Smart_Model_Selector::get_default_model( 'openai' ) );
    }

    public function test_get_default_model_respects_filter_override(): void {
        $callback = static function () {
            return 'gpt-custom-override';
        };
        add_filter( 'contextualwp_default_agent_model', $callback, 10, 2 );

        try {
            $this->assertSame( 'gpt-custom-override', Smart_Model_Selector::get_default_model( 'openai' ) );
        } finally {
            remove_filter( 'contextualwp_default_agent_model', $callback, 10 );
        }
    }

    public function test_openai_fallback_models_use_current_mini_nano_tiers_only(): void {
        $fallback = Smart_Model_Selector::get_openai_fallback_models();
        $this->assertSame( [ 'gpt-5.4-mini', 'gpt-6-luna' ], $fallback );
    }

    public function test_openai_fallback_models_skip_retiring_ids_even_when_mapped(): void {
        $callback = static function ( $mapping ) {
            $mapping['openai']['mini'] = 'gpt-5-mini';
            return $mapping;
        };
        add_filter( 'contextualwp_smart_model_mapping', $callback, 10, 2 );

        try {
            $this->assertSame( [ 'gpt-6-luna' ], Smart_Model_Selector::get_openai_fallback_models() );
        } finally {
            remove_filter( 'contextualwp_smart_model_mapping', $callback, 10 );
        }
    }

    public function test_retiring_models_are_classified_with_vendor_dates(): void {
        $property = new \ReflectionProperty( Smart_Model_Selector::class, 'retiring_models' );
        $property->setAccessible( true );

        $this->assertSame(
            [
                'openai'  => [
                    'gpt-5'        => '2026-12-11',
                    'gpt-5-mini'   => '2026-12-11',
                    'gpt-5-nano'   => '2026-12-11',
                    'gpt-5.4-nano' => '2027-04-01',
                ],
                'claude'  => [
                    'claude-sonnet-4-5' => '2026-11-30',
                ],
                'mistral' => [
                    'mistral-small-2506'  => '2026-07-31',
                    'mistral-medium-2508' => '2026-08-31',
                ],
            ],
            $property->getValue()
        );
    }

    /**
     * Retiring IDs stay valid for saved settings but are never offered or chosen automatically.
     */
    public function test_retiring_models_stay_supported_but_unreachable_automatically(): void {
        $property = new \ReflectionProperty( Smart_Model_Selector::class, 'retiring_models' );
        $property->setAccessible( true );
        $retiring  = $property->getValue();
        $visible   = Smart_Model_Selector::get_visible_models();
        $supported = Smart_Model_Selector::get_supported_models();
        $fallback  = Smart_Model_Selector::get_openai_fallback_models();
        $mapping   = Smart_Model_Selector::get_all_models();

        foreach ( $retiring as $provider => $models ) {
            foreach ( array_keys( $models ) as $model ) {
                $this->assertContains( $model, $supported[ $provider ], "$model must remain supported" );
                $this->assertNotContains( $model, $visible[ $provider ], "$model must not be visible" );
                $this->assertNotContains( $model, $fallback, "$model must not be a fallback" );
                foreach ( [ 'nano', 'mini', 'large' ] as $tier ) {
                    $this->assertNotSame( $model, $mapping[ $provider ][ $tier ], "$model must not be a smart-selection tier" );
                }
                $this->assertNotSame( $model, Smart_Model_Selector::get_default_model( $provider ), "$model must not be a default" );
            }
        }
    }

    public function test_default_models_per_provider_are_unchanged(): void {
        $this->assertSame( 'gpt-5.5', Smart_Model_Selector::get_default_model( 'openai' ) );
        $this->assertSame( 'claude-opus-4-7', Smart_Model_Selector::get_default_model( 'claude' ) );
        $this->assertSame( 'mistral-large-2512', Smart_Model_Selector::get_default_model( 'mistral' ) );
    }

    public function test_mistral_visible_models_use_current_successors(): void {
        $visible = Smart_Model_Selector::get_visible_models();
        $this->assertSame(
            [ 'mistral-large-2512', 'mistral-medium-3-5', 'mistral-small-2603' ],
            array_values( $visible['mistral'] )
        );
    }

    public function test_mistral_supported_models_include_retired_legacy_ids(): void {
        $supported = Smart_Model_Selector::get_supported_models();
        $this->assertSame(
            [ 'mistral-small-2603', 'mistral-medium-3-5', 'mistral-large-2512', 'mistral-small-2506', 'mistral-medium-2508' ],
            array_values( $supported['mistral'] )
        );
    }

    public function test_mistral_smart_model_selection_uses_current_models(): void {
        $settings = [ 'smart_model_selection' => true ];
        $provider = 'mistral';

        $this->assertSame( 'mistral-small-2603', Smart_Model_Selector::select_model( 'Hi', '', $provider, 'mistral-large-2512', $settings ) );

        $medium_prompt = 'Analyze and explain this. ' . str_repeat( 'word ', 400 );
        $this->assertSame( 'mistral-medium-3-5', Smart_Model_Selector::select_model( $medium_prompt, '', $provider, 'mistral-large-2512', $settings ) );

        $long_prompt = 'Analyze and explain this. ' . str_repeat( 'word ', 2000 );
        $this->assertSame( 'mistral-large-2512', Smart_Model_Selector::select_model( $long_prompt, '', $provider, 'mistral-small-2603', $settings ) );
    }

    public function test_saved_retired_model_is_kept_when_smart_selection_is_off(): void {
        $settings = [ 'smart_model_selection' => false ];
        $this->assertSame( 'mistral-small-2506', Smart_Model_Selector::select_model( 'Hi', '', 'mistral', 'mistral-small-2506', $settings ) );
        $this->assertSame( 'claude-sonnet-4-5', Smart_Model_Selector::select_model( 'Hi', '', 'claude', 'claude-sonnet-4-5', $settings ) );
    }

    public function test_openai_responses_api_models_include_current_and_legacy_gpt5_ids(): void {
        $models = Smart_Model_Selector::get_openai_responses_api_models();
        $this->assertContains( 'gpt-5.5', $models );
        $this->assertContains( 'gpt-5.4-mini', $models );
        $this->assertContains( 'gpt-5.2', $models );
        $this->assertContains( 'gpt-6-luna', $models );
        $this->assertNotContains( 'gpt-4o', $models );
    }

    public function test_openai_visible_models_only_include_current_recommended_models(): void {
        $visible = Smart_Model_Selector::get_visible_models();
        $this->assertIsArray( $visible );
        $this->assertArrayHasKey( 'openai', $visible );

        $this->assertSame(
            [ 'gpt-5.5', 'gpt-5.4-mini', 'gpt-6-luna' ],
            array_values( $visible['openai'] )
        );
    }

    /**
     * Invariant for admin settings.js: legacy saved IDs must be supported but not in the visible-only map,
     * so the script can add a single "(legacy)" row for the current value only.
     */
    public function test_example_legacy_models_supported_not_visible_for_js_invariant(): void {
        $visible = Smart_Model_Selector::get_visible_models();
        $supported = Smart_Model_Selector::get_supported_models();
        $this->assertNotContains( 'gpt-5.2', $visible['openai'], 'OpenAI legacy must not appear in visible list' );
        $this->assertContains( 'gpt-5.2', $supported['openai'], 'OpenAI legacy must remain supported' );
        $this->assertNotContains( 'gpt-5.4-nano', $visible['openai'], 'Retiring gpt-5.4-nano must not appear in visible list' );
        $this->assertContains( 'gpt-5.4-nano', $supported['openai'], 'Retiring gpt-5.4-nano must remain supported for saved settings' );
        $this->assertNotContains( 'claude-sonnet-4-5', $visible['claude'], 'Claude legacy must not appear in visible list' );
        $this->assertContains( 'claude-sonnet-4-5', $supported['claude'], 'Claude legacy must remain supported' );
    }

    public function test_openai_supported_models_include_legacy_models(): void {
        $supported = Smart_Model_Selector::get_supported_models();
        $this->assertIsArray( $supported );
        $this->assertArrayHasKey( 'openai', $supported );

        $models = array_values( $supported['openai'] );
        $this->assertContains( 'gpt-5.5', $models );
        $this->assertContains( 'gpt-5.4-mini', $models );
        $this->assertContains( 'gpt-6-luna', $models );
        $this->assertContains( 'gpt-5.4-nano', $models );

        // Backwards compatibility: previously-shipped IDs must remain valid.
        $this->assertContains( 'gpt-5.2', $models );
        $this->assertContains( 'gpt-5-mini', $models );
        $this->assertContains( 'gpt-5-nano', $models );
        $this->assertContains( 'gpt-5', $models );
    }

    public function test_claude_visible_models_only_include_current_recommended_models(): void {
        $visible = Smart_Model_Selector::get_visible_models();
        $this->assertIsArray( $visible );
        $this->assertArrayHasKey( 'claude', $visible );

        $this->assertSame(
            [ 'claude-opus-4-7', 'claude-sonnet-4-6', 'claude-haiku-4-5' ],
            array_values( $visible['claude'] )
        );
    }

    public function test_claude_supported_models_include_legacy_models(): void {
        $supported = Smart_Model_Selector::get_supported_models();
        $this->assertIsArray( $supported );
        $this->assertArrayHasKey( 'claude', $supported );

        $models = array_values( $supported['claude'] );
        $this->assertContains( 'claude-opus-4-7', $models );
        $this->assertContains( 'claude-sonnet-4-6', $models );
        $this->assertContains( 'claude-haiku-4-5', $models );

        // Backwards compatibility: previously-shipped IDs must remain valid.
        $this->assertContains( 'claude-opus-4-5', $models );
        $this->assertContains( 'claude-sonnet-4-5', $models );
    }

    public function test_openai_smart_model_selection_uses_current_models(): void {
        $settings = [ 'smart_model_selection' => true ];
        $provider = 'openai';

        // nano: short prompt
        $nano = Smart_Model_Selector::select_model( 'Hi', '', $provider, 'gpt-5.5', $settings );
        $this->assertSame( 'gpt-6-luna', $nano );

        // mini: medium-length prompt (~400 words) with medium complexity (no threshold adjustment).
        $medium_prompt = 'Analyze and explain this. ' . str_repeat( 'word ', 400 );
        $mini = Smart_Model_Selector::select_model( $medium_prompt, '', $provider, 'gpt-5.5', $settings );
        $this->assertSame( 'gpt-5.4-mini', $mini );

        // large: long prompt (~2000 words) with medium complexity (no threshold adjustment).
        $long_prompt = 'Analyze and explain this. ' . str_repeat( 'word ', 2000 );
        $large = Smart_Model_Selector::select_model( $long_prompt, '', $provider, 'gpt-5.4-mini', $settings );
        $this->assertSame( 'gpt-5.5', $large );
    }

    public function test_claude_smart_model_selection_uses_current_models(): void {
        $settings = [ 'smart_model_selection' => true ];
        $provider = 'claude';

        // nano: short prompt
        $nano = Smart_Model_Selector::select_model( 'Hi', '', $provider, 'claude-opus-4-7', $settings );
        $this->assertSame( 'claude-haiku-4-5', $nano );

        // mini: medium-length prompt (~400 words) with medium complexity (no threshold adjustment).
        $medium_prompt = 'Analyze and explain this. ' . str_repeat( 'word ', 400 );
        $mini = Smart_Model_Selector::select_model( $medium_prompt, '', $provider, 'claude-opus-4-7', $settings );
        $this->assertSame( 'claude-sonnet-4-6', $mini );

        // large: long prompt (~2000 words) with medium complexity (no threshold adjustment).
        $long_prompt = 'Analyze and explain this. ' . str_repeat( 'word ', 2000 );
        $large = Smart_Model_Selector::select_model( $long_prompt, '', $provider, 'claude-sonnet-4-6', $settings );
        $this->assertSame( 'claude-opus-4-7', $large );
    }
}

