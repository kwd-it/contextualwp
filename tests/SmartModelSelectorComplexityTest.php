<?php
/**
 * PHPUnit tests for Smart Model Selector complexity analysis
 *
 * @package ContextualWP\Tests
 * @since 0.2.0
 */

namespace ContextualWP\Tests;

use ContextualWP\Helpers\Smart_Model_Selector;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Test class for complexity analysis functionality
 */
class SmartModelSelectorComplexityTest extends TestCase {

    /**
     * Reflection class instance for accessing private methods
     *
     * @var ReflectionClass
     */
    private $reflection;

    /**
     * Reflection method for analyze_complexity
     *
     * @var ReflectionMethod
     */
    private $analyze_method;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        
        // Create reflection to access private method
        $this->reflection = new ReflectionClass( Smart_Model_Selector::class );
        $this->analyze_method = $this->reflection->getMethod( 'analyze_complexity' );
        $this->analyze_method->setAccessible( true );
    }

    /**
     * Invoke the private analyze_complexity method
     *
     * @param string $prompt The user prompt
     * @param string $context The context content
     * @return string Complexity level
     */
    private function analyze_complexity( $prompt, $context = '' ) {
        return $this->analyze_method->invoke( null, $prompt, $context );
    }

    /**
     * Test simple complexity examples (score <= 2)
     *
     * @dataProvider simpleExamplesProvider
     * @param string $prompt The prompt to test
     * @param string $description Description of the test case
     */
    public function test_simple_complexity( $prompt, $description ) {
        $result = $this->analyze_complexity( $prompt );
        $this->assertEquals(
            'simple',
            $result,
            "Expected 'simple' for: {$description} - Prompt: '{$prompt}'"
        );
    }

    /**
     * Test medium complexity examples (score 3-5)
     *
     * @dataProvider mediumExamplesProvider
     * @param string $prompt The prompt to test
     * @param string $description Description of the test case
     */
    public function test_medium_complexity( $prompt, $description ) {
        $result = $this->analyze_complexity( $prompt );
        $this->assertEquals(
            'medium',
            $result,
            "Expected 'medium' for: {$description} - Prompt: '{$prompt}'"
        );
    }

    /**
     * Test complex complexity examples (score >= 6)
     *
     * @dataProvider complexExamplesProvider
     * @param string $prompt The prompt to test
     * @param string $description Description of the test case
     */
    public function test_complex_complexity( $prompt, $description ) {
        $result = $this->analyze_complexity( $prompt );
        $this->assertEquals(
            'complex',
            $result,
            "Expected 'complex' for: {$description} - Prompt: '{$prompt}'"
        );
    }

    /**
     * Data provider for simple complexity test cases
     *
     * @return array Array of [prompt, description] pairs
     */
    public function simpleExamplesProvider() {
        return [
            [
                'What is this?',
                'Simple WH-word question at start (WH-word penalty reduces score)'
            ],
            [
                'Who are you?',
                'Simple who question (WH-word penalty reduces score)'
            ],
            [
                'How do I do this?',
                'Simple how question with few words (WH-word penalty reduces score)'
            ],
        ];
    }

    /**
     * Data provider for medium complexity test cases
     *
     * @return array Array of [prompt, description] pairs
     */
    public function mediumExamplesProvider() {
        return [
            [
                'Please analyze the document and explain the findings.',
                'Two analytical verbs with conjunction (analyze +2, explain +2, and +1 = 5)'
            ],
            [
                'Compare these two options but evaluate the pros.',
                'Two analytical verbs with conjunction (compare +2, but +1, evaluate +2 = 5)'
            ],
            [
                'This is a longer prompt with multiple sentences. It has some content. And more here.',
                'Multiple sentences with conjunction (~15 words = 1 point, 3 sentences = +2, and = +1, total = 4)'
            ],
        ];
    }

    /**
     * Data provider for complex complexity test cases
     *
     * @return array Array of [prompt, description] pairs
     */
    public function complexExamplesProvider() {
        return [
            [
                'Analyze the document, compare it with others, and explain the differences. Then evaluate the results.',
                'Multiple analytical verbs and multiple sentences (analyze +2, compare +2, explain +2, evaluate +2, and +1, 2 sentences = +1, total = 9)'
            ],
            [
                'Please explain the concept, however, we must also analyze the implications. Moreover, we should compare alternatives.',
                'Multiple analytical verbs with multiple conjunctions and sentences (explain +2, however +1, analyze +2, moreover +1, compare +2, 2 sentences = +1, total = 9)'
            ],
            [
                'This is a very long prompt that has many words and multiple sentences. It contains analytical verbs like analyze and compare. Moreover, it uses conjunctions. Therefore, it should be complex.',
                'Long prompt with many factors (~40 words = 4 points, analyze +2, compare +2, and +1, moreover +1, therefore +1, 4 sentences = +3, total = 14)'
            ],
        ];
    }

    /**
     * Test that context parameter is accepted but not used
     */
    public function test_context_parameter_ignored() {
        $prompt = 'What is this?';
        $context1 = '';
        $context2 = 'Some long context that should not affect the complexity score.';
        
        $result1 = $this->analyze_complexity( $prompt, $context1 );
        $result2 = $this->analyze_complexity( $prompt, $context2 );
        
        $this->assertEquals( $result1, $result2, 'Context should not affect complexity score' );
    }

    /**
     * Test edge cases: empty prompt
     */
    public function test_empty_prompt() {
        $result = $this->analyze_complexity( '' );
        // Empty prompt should default to simple (score 0)
        $this->assertEquals( 'simple', $result );
    }

    /**
     * Test edge cases: very short prompt
     */
    public function test_very_short_prompt() {
        $result = $this->analyze_complexity( 'Hi' );
        // Very short prompt with no indicators should be simple
        $this->assertEquals( 'simple', $result );
    }

    /**
     * Test that WH-words only reduce score when at the beginning
     */
    public function test_wh_words_only_at_beginning() {
        $prompt_start = 'What is this?';
        $prompt_middle = 'Tell me what this is.';
        
        $result_start = $this->analyze_complexity( $prompt_start );
        $result_middle = $this->analyze_complexity( $prompt_middle );
        
        // WH-word at start should reduce score more than in middle
        $this->assertEquals( 'simple', $result_start );
        // Middle WH-word doesn't get penalty, so should be simple but potentially higher score
        $this->assertContains( $result_middle, ['simple', 'medium'], 'WH-word in middle should not get beginning penalty' );
    }
}
