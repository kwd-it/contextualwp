<?php
namespace ContextualWP\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Smart Model Selector for ContextualWP
 * 
 * Automatically selects the most efficient AI model based on prompt length and complexity.
 * 
 * @package ContextualWP
 * @since 0.2.0
 */
class Smart_Model_Selector {

    /**
     * Default token thresholds for model selection
     * 
     * @since 0.2.0
     * @var array
     */
    private static $default_thresholds = [
        'nano'  => 200,   // Short/simple prompts
        'mini'  => 1000,  // Medium prompts
        'large' => 2000,  // Long/complex prompts
    ];

    /**
     * Model mapping for different providers
     * 
     * @since 0.2.0
     * @var array
     * 
     * @note Model lists evolve quickly. Recommend reviewing this mapping every 3-6 months
     *       to ensure models are current and optimal. Use the contextualwp_smart_model_mapping
     *       filter to customize mappings without modifying core code.
     */
    private static $model_mapping = [
        'openai' => [
            'nano'  => 'gpt-4o-mini',
            'mini'  => 'gpt-4o',
            'large' => 'gpt-4.1',
        ],
        'claude' => [
            'nano'  => 'claude-3-haiku',
            'mini'  => 'claude-3.5-sonnet',
            'large' => 'claude-3.5-opus',
        ],
    ];

    /**
     * Select the optimal model based on prompt and context analysis
     * 
     * @since 0.2.0
     * @param string $prompt The user prompt
     * @param string $context The context content
     * @param string $provider The AI provider (openai, claude)
     * @param string $current_model The currently selected model
     * @param array $settings Plugin settings
     * @return string The selected model
     */
    public static function select_model( $prompt, $context, $provider, $current_model, $settings = [] ) {
        // Check if smart model selection is enabled
        if ( ! self::is_smart_selection_enabled( $settings ) ) {
            return $current_model;
        }

        // Calculate total token count
        $total_tokens = self::estimate_tokens( $prompt, $context );
        
        // Determine complexity level
        $complexity = self::analyze_complexity( $prompt, $context );
        
        // Get thresholds (allow filtering)
        $thresholds = apply_filters( 'contextualwp_smart_model_thresholds', self::$default_thresholds, $provider );
        
        // Select model size based on tokens and complexity
        $model_size = self::determine_model_size( $total_tokens, $complexity, $thresholds );
        
        // Get model mapping (allow filtering)
        $mapping = apply_filters( 'contextualwp_smart_model_mapping', self::$model_mapping, $provider );
        
        // Get the specific model for the provider and size
        $selected_model = $mapping[ $provider ][ $model_size ] ?? $mapping['openai'][$model_size] ?? $current_model;
        
        // Allow developers to override the selection
        $selected_model = apply_filters( 'contextualwp_smart_model_select', $selected_model, [
            'prompt' => $prompt,
            'context' => $context,
            'provider' => $provider,
            'current_model' => $current_model,
            'tokens' => $total_tokens,
            'complexity' => $complexity,
            'model_size' => $model_size,
            'settings' => $settings,
        ] );
        
        // Log the selection for debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            Utilities::log_debug( [
                'selected_model' => $selected_model,
                'original_model' => $current_model,
                'provider' => $provider,
                'tokens' => $total_tokens,
                'complexity' => $complexity,
                'model_size' => $model_size,
            ], 'smart_model_selection' );
        }
        
        return $selected_model;
    }

    /**
     * Check if smart model selection is enabled
     * 
     * @since 0.2.0
     * @param array $settings Plugin settings
     * @return bool
     */
    private static function is_smart_selection_enabled( $settings ) {
        $enabled = $settings['smart_model_selection'] ?? true; // Default to enabled
        
        // Allow filtering
        return apply_filters( 'contextualwp_smart_model_selection_enabled', $enabled, $settings );
    }

    /**
     * Estimate token count for prompt and context
     * 
     * Uses an improved estimation algorithm that accounts for:
     * - Word boundaries and whitespace
     * - Punctuation and special characters
     * - Different character types (letters, numbers, symbols)
     * - HTML/formatting tags if present
     * 
     * @since 0.3.3
     * @param string $prompt The user prompt
     * @param string $context The context content
     * @return int Estimated token count
     */
    private static function estimate_tokens( $prompt, $context ) {
        $combined_text = $prompt . "\n\n" . $context;
        
        // Remove HTML tags for more accurate estimation (count text content only)
        $text_only = strip_tags( $combined_text );
        
        // Trim and normalize whitespace
        $text_only = trim( $text_only );
        $text_only = preg_replace( '/\s+/', ' ', $text_only );
        
        if ( empty( $text_only ) ) {
            return 0;
        }
        
        // Count words (split on whitespace and punctuation boundaries)
        $words = preg_split( '/[\s\p{P}]+/u', $text_only, -1, PREG_SPLIT_NO_EMPTY );
        $word_count = count( $words );
        
        // Count characters by type for better estimation
        $char_count = mb_strlen( $text_only, 'UTF-8' );
        $letter_count = preg_match_all( '/[\p{L}]/u', $text_only );
        $number_count = preg_match_all( '/[\p{N}]/u', $text_only );
        $punctuation_count = preg_match_all( '/[\p{P}]/u', $text_only );
        
        // Improved token estimation algorithm:
        // - Most tokens are sub-word units, averaging ~0.75 tokens per word
        // - Punctuation often forms separate tokens (~0.5 tokens per punctuation mark)
        // - Numbers can be tokenized variably (~0.8 tokens per number sequence)
        // - Remaining characters contribute ~0.25 tokens each
        $estimated_tokens = 0;
        
        // Base estimation from words (most accurate predictor)
        $estimated_tokens += $word_count * 0.75;
        
        // Add tokens for punctuation (often separate tokens)
        $estimated_tokens += $punctuation_count * 0.5;
        
        // Account for numbers (can be tokenized as sequences)
        $estimated_tokens += $number_count * 0.3;
        
        // Add small contribution for remaining characters (spaces, special chars)
        $remaining_chars = $char_count - $letter_count - $number_count - $punctuation_count;
        $estimated_tokens += $remaining_chars * 0.25;
        
        // Fallback: if word count is unreliable, use character-based estimation
        // This handles edge cases like very long words or non-standard text
        $char_based_estimate = $char_count / 3.5; // Slightly more accurate than 4
        
        // Use the higher estimate to avoid underestimation (better to overestimate slightly)
        $estimated_tokens = max( $estimated_tokens, $char_based_estimate );
        
        // Round to nearest integer
        $estimated_tokens = (int) round( $estimated_tokens );
        
        // Ensure minimum of 1 token for non-empty text
        if ( $estimated_tokens < 1 && ! empty( $text_only ) ) {
            $estimated_tokens = 1;
        }
        
        // Allow filtering for more accurate token counting or custom implementations
        return apply_filters( 'contextualwp_estimate_tokens', $estimated_tokens, $prompt, $context );
    }

    /**
     * Analyze prompt and context complexity
     * 
     * @since 0.2.0
     * @param string $prompt The user prompt
     * @param string $context The context content
     * @return string Complexity level (simple, medium, complex)
     */
    private static function analyze_complexity( $prompt, $context ) {
        $complexity_indicators = [
            'simple' => [
                'keywords' => ['what', 'when', 'where', 'who', 'how'],
                'patterns' => ['\?$', '^[A-Z][^.!?]*\?$'], // Simple questions
            ],
            'complex' => [
                'keywords' => ['analyze', 'compare', 'explain', 'describe', 'evaluate', 'critique'],
                'patterns' => ['\b(and|or|but|however|therefore|moreover|furthermore)\b'],
            ],
        ];
        
        $prompt_lower = strtolower( $prompt );
        $context_lower = strtolower( $context );
        
        // Check for complex indicators
        foreach ( $complexity_indicators['complex']['keywords'] as $keyword ) {
            if ( strpos( $prompt_lower, $keyword ) !== false ) {
                return 'complex';
            }
        }
        
        foreach ( $complexity_indicators['complex']['patterns'] as $pattern ) {
            if ( preg_match( '/' . $pattern . '/i', $prompt ) ) {
                return 'complex';
            }
        }
        
        // Check for simple indicators
        foreach ( $complexity_indicators['simple']['keywords'] as $keyword ) {
            if ( strpos( $prompt_lower, $keyword ) !== false ) {
                return 'simple';
            }
        }
        
        foreach ( $complexity_indicators['simple']['patterns'] as $pattern ) {
            if ( preg_match( '/' . $pattern . '/i', $prompt ) ) {
                return 'simple';
            }
        }
        
        // Default to medium complexity
        return 'medium';
    }

    /**
     * Determine model size based on tokens and complexity
     * 
     * @since 0.2.0
     * @param int $tokens Token count
     * @param string $complexity Complexity level
     * @param array $thresholds Token thresholds
     * @return string Model size (nano, mini, large)
     */
    private static function determine_model_size( $tokens, $complexity, $thresholds ) {
        // Adjust thresholds based on complexity
        $adjusted_thresholds = $thresholds;
        
        if ( $complexity === 'complex' ) {
            // Use larger models for complex tasks
            $adjusted_thresholds['nano'] = $thresholds['nano'] * 0.5;
            $adjusted_thresholds['mini'] = $thresholds['mini'] * 0.7;
        } elseif ( $complexity === 'simple' ) {
            // Use smaller models for simple tasks
            $adjusted_thresholds['nano'] = $thresholds['nano'] * 1.5;
            $adjusted_thresholds['mini'] = $thresholds['mini'] * 1.3;
        }
        
        if ( $tokens <= $adjusted_thresholds['nano'] ) {
            return 'nano';
        } elseif ( $tokens <= $adjusted_thresholds['mini'] ) {
            return 'mini';
        } else {
            return 'large';
        }
    }

    /**
     * Get all models for all providers (single source of truth)
     * 
     * @since 0.3.2
     * @return array All model mappings
     */
    public static function get_all_models() {
        return apply_filters( 'contextualwp_model_list', self::$model_mapping );
    }

    /**
     * Get available models for a provider
     * 
     * @since 0.2.0
     * @param string $provider The AI provider
     * @return array Available models
     */
    public static function get_available_models( $provider ) {
        $mapping = apply_filters( 'contextualwp_smart_model_mapping', self::$model_mapping, $provider );
        return $mapping[ $provider ] ?? [];
    }

    /**
     * Get model information for display
     * 
     * @since 0.2.0
     * @param string $model The model name
     * @param string $provider The AI provider
     * @return array Model information
     */
    public static function get_model_info( $model, $provider ) {
        $mapping = apply_filters( 'contextualwp_smart_model_mapping', self::$model_mapping, $provider );
        
        foreach ( $mapping[ $provider ] ?? [] as $size => $model_name ) {
            if ( $model_name === $model ) {
                return [
                    'name' => $model_name,
                    'size' => $size,
                    'provider' => $provider,
                    'description' => self::get_model_description( $size ),
                ];
            }
        }
        
        return [
            'name' => $model,
            'size' => 'unknown',
            'provider' => $provider,
            'description' => 'Custom model',
        ];
    }

    /**
     * Get model size description
     * 
     * @since 0.2.0
     * @param string $size Model size
     * @return string Description
     */
    private static function get_model_description( $size ) {
        $descriptions = [
            'nano'  => 'Fast and efficient for simple tasks',
            'mini'  => 'Balanced performance for medium complexity',
            'large' => 'High capability for complex tasks',
        ];
        
        return $descriptions[ $size ] ?? 'Unknown model size';
    }
}
