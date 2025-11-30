<?php
namespace ContextualWP\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provider Registry for ContextualWP
 * 
 * Centralized registry for AI provider support. This is the single source of truth
 * for all officially-supported AI providers in the plugin.
 * 
 * @package ContextualWP
 * @since 0.4.0
 */
class Providers {

    /**
     * Get the list of officially-supported AI providers
     * 
     * Returns lowercase provider slugs that are used internally throughout the plugin.
     * This list is filterable via the 'contextualwp_ai_providers' filter.
     * 
     * @since 0.4.0
     * @return array Array of provider slugs (e.g., ['openai', 'claude', 'mistral'])
     */
    public static function list() {
        return apply_filters( 'contextualwp_ai_providers', [
            'openai',
            'claude',
            'mistral',
        ] );
    }

    /**
     * Get UI labels for providers
     * 
     * Maps internal provider slugs to user-friendly display names.
     * This mapping is filterable via the 'contextualwp_provider_labels' filter.
     * 
     * @since 0.4.0
     * @return array Associative array mapping slugs to labels (e.g., ['openai' => 'OpenAI'])
     */
    public static function get_labels() {
        $labels = [
            'openai'  => 'OpenAI',
            'claude'  => 'Claude',
            'mistral' => 'Mistral',
        ];
        
        return apply_filters( 'contextualwp_provider_labels', $labels );
    }

    /**
     * Get providers as key-value pairs for dropdowns
     * 
     * Returns an array suitable for use in HTML select dropdowns, where keys are
     * the UI labels and values are also the UI labels (for backward compatibility
     * with existing settings structure).
     * 
     * @since 0.4.0
     * @return array Associative array for dropdowns (e.g., ['OpenAI' => 'OpenAI'])
     */
    public static function get_dropdown_options() {
        $providers = self::list();
        $labels = self::get_labels();
        $options = [];
        
        foreach ( $providers as $slug ) {
            $label = $labels[ $slug ] ?? ucfirst( $slug );
            $options[ $label ] = $label;
        }
        
        // Allow filtering for extensibility (backward compatibility with contextualwp_available_providers)
        return apply_filters( 'contextualwp_available_providers', $options );
    }

    /**
     * Normalize a provider name to internal slug
     * 
     * Converts UI provider names (e.g., 'OpenAI', 'Claude', 'Mistral') to
     * lowercase internal slugs (e.g., 'openai', 'claude', 'mistral').
     * 
     * @since 0.4.0
     * @param string $provider Provider name (can be UI label or slug)
     * @return string Normalized provider slug
     */
    public static function normalize( $provider ) {
        if ( empty( $provider ) ) {
            return '';
        }
        
        // Create reverse mapping from labels to slugs
        $labels = self::get_labels();
        $label_to_slug = array_flip( $labels );
        
        // If it's already a known label, return the slug
        if ( isset( $label_to_slug[ $provider ] ) ) {
            return $label_to_slug[ $provider ];
        }
        
        // If it's already a known slug, return it
        $providers = self::list();
        $provider_lower = strtolower( $provider );
        if ( in_array( $provider_lower, $providers, true ) ) {
            return $provider_lower;
        }
        
        // Fallback: return lowercase version
        return strtolower( $provider );
    }

    /**
     * Check if a provider is officially supported
     * 
     * @since 0.4.0
     * @param string $provider Provider slug or label
     * @return bool True if provider is supported
     */
    public static function is_supported( $provider ) {
        $normalized = self::normalize( $provider );
        $providers = self::list();
        return in_array( $normalized, $providers, true );
    }
}

