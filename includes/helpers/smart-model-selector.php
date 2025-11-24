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
     * @since 0.2.0
     * @param string $prompt The user prompt
     * @param string $context The context content
     * @return int Estimated token count
     */
    private static function estimate_tokens( $prompt, $context ) {
        $combined_text = $prompt . "\n\n" . $context;
        
        // Simple token estimation: ~4 characters per token
        // This is a rough approximation - in production you might want to use a proper tokenizer
        $estimated_tokens = strlen( $combined_text ) / 4;
        
        // Allow filtering for more accurate token counting
        return apply_filters( 'contextualwp_estimate_tokens', (int) $estimated_tokens, $prompt, $context );
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
