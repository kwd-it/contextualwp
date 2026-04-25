<?php
namespace ContextualWP\Tests;

use ContextualWP\Helpers\Smart_Model_Selector;
use PHPUnit\Framework\TestCase;

/**
 * Model catalog tests: ensure new model IDs are present and legacy IDs remain valid.
 */
class ModelCatalogTest extends TestCase {

    public function test_openai_visible_models_only_include_current_recommended_models(): void {
        $visible = Smart_Model_Selector::get_visible_models();
        $this->assertIsArray( $visible );
        $this->assertArrayHasKey( 'openai', $visible );

        $this->assertSame(
            [ 'gpt-5.5', 'gpt-5.4-mini', 'gpt-5.4-nano' ],
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
        $this->assertSame( 'gpt-5.4-nano', $nano );

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

