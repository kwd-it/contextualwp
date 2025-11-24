<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ContextualWP_Admin_Settings {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu_pages' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function add_menu_pages() {
        add_options_page(
            __( 'ContextualWP Settings', 'contextualwp' ),
            'ContextualWP',
            'manage_options',
            'contextualwp-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function enqueue_assets( $hook ) {
        // Only load assets on our settings page
        if ( $hook !== 'settings_page_contextualwp-settings' ) {
            return;
        }

        wp_enqueue_style(
            'contextualwp-settings',
            plugin_dir_url( __FILE__ ) . 'assets/css/settings.css',
            [],
            CONTEXTUALWP_VERSION
        );

        wp_enqueue_script(
            'contextualwp-settings',
            plugin_dir_url( __FILE__ ) . 'assets/js/settings.js',
            [],
            CONTEXTUALWP_VERSION,
            true
        );

        // Localize model mappings for JavaScript
        wp_localize_script(
            'contextualwp-settings',
            'ContextualWPModels',
            \ContextualWP\Helpers\Smart_Model_Selector::get_all_models()
        );
    }

    public function register_settings() {
        register_setting( 'contextualwp_settings_group', 'contextualwp_settings', [ $this, 'sanitize_settings' ] );

        add_settings_section(
            'contextualwp_main_section',
            __( 'AI Provider Settings', 'contextualwp' ),
            [ $this, 'section_description' ],
            'contextualwp-settings'
        );

        add_settings_field(
            'ai_provider',
            __( 'AI Provider', 'contextualwp' ),
            [ $this, 'field_ai_provider' ],
            'contextualwp-settings',
            'contextualwp_main_section'
        );
        add_settings_field(
            'api_key',
            __( 'API Key', 'contextualwp' ),
            [ $this, 'field_api_key' ],
            'contextualwp-settings',
            'contextualwp_main_section'
        );
        add_settings_field(
            'model',
            __( 'Model', 'contextualwp' ),
            [ $this, 'field_model' ],
            'contextualwp-settings',
            'contextualwp_main_section'
        );

        // Advanced settings section
        add_settings_section(
            'contextualwp_advanced_section',
            __( 'Advanced Settings', 'contextualwp' ),
            [ $this, 'advanced_section_description' ],
            'contextualwp-settings'
        );

        add_settings_field(
            'max_tokens',
            __( 'Max Tokens', 'contextualwp' ),
            [ $this, 'field_max_tokens' ],
            'contextualwp-settings',
            'contextualwp_advanced_section'
        );
        add_settings_field(
            'temperature',
            __( 'Temperature', 'contextualwp' ),
            [ $this, 'field_temperature' ],
            'contextualwp-settings',
            'contextualwp_advanced_section'
        );
        add_settings_field(
            'smart_model_selection',
            __( 'Enable Smart Model Selection', 'contextualwp' ),
            [ $this, 'field_smart_model_selection' ],
            'contextualwp-settings',
            'contextualwp_advanced_section'
        );
    }

    public function section_description() {
        echo '<p>' . esc_html__( 'Configure your AI provider settings for ContextualWP.', 'contextualwp' ) . '</p>';
    }

    public function advanced_section_description() {
        echo '<div class="contextualwp-advanced-toggle">';
        echo '<button type="button" id="contextualwp-advanced-toggle" class="button button-secondary">';
        echo '<span class="toggle-text">' . esc_html__( 'Show Advanced Settings', 'contextualwp' ) . '</span>';
        echo '<span class="toggle-icon">▼</span>';
        echo '</button>';
        echo '</div>';
        echo '<div id="contextualwp-advanced-settings" class="contextualwp-advanced-settings" style="display: none;">';
        echo '<p>' . esc_html__( 'Fine-tune your AI model behavior with these advanced settings.', 'contextualwp' ) . '</p>';
    }

    public function sanitize_settings( $input ) {
        $output = [];
        
        // Sanitize AI provider
        $output['ai_provider'] = sanitize_text_field( $input['ai_provider'] ?? 'OpenAI' );
        
        // Validate provider and set default if invalid
        $valid_providers = array_keys( $this->get_available_providers() );
        if ( !in_array( $output['ai_provider'], $valid_providers ) ) {
            $output['ai_provider'] = 'OpenAI';
        }
        
        $output['api_key'] = sanitize_text_field( $input['api_key'] ?? '' );
        
        // Sanitize model and validate against provider
        $output['model'] = sanitize_text_field( $input['model'] ?? '' );
        $output['model'] = $this->validate_model_for_provider( $output['model'], $output['ai_provider'] );
        
        // Sanitize advanced settings with defaults
        $output['max_tokens'] = absint( $input['max_tokens'] ?? 1024 );
        if ( $output['max_tokens'] === 0 ) {
            $output['max_tokens'] = 1024;
        }
        
        $output['temperature'] = is_numeric( $input['temperature'] ?? null ) ? floatval( $input['temperature'] ) : 1.0;
        if ( $output['temperature'] < 0 || $output['temperature'] > 2 ) {
            $output['temperature'] = 1.0;
        }
        
        // Sanitize smart model selection setting
        $output['smart_model_selection'] = isset( $input['smart_model_selection'] ) ? (bool) $input['smart_model_selection'] : true;
        
        return $output;
    }

    /**
     * Validate model for the selected provider and return a valid model
     */
    private function validate_model_for_provider( $model, $provider ) {
        $valid_models = $this->get_valid_models_for_provider( $provider );
        
        if ( in_array( $model, $valid_models ) ) {
            return $model;
        }
        
        // Return first valid model as fallback
        return !empty( $valid_models ) ? $valid_models[0] : '';
    }

    /**
     * Get valid models for a provider (uses single source of truth from Smart Model Selector)
     */
    private function get_valid_models_for_provider( $provider ) {
        // Map UI provider names to internal slugs
        $provider_map = [
            'OpenAI' => 'openai',
            'Claude' => 'claude',
        ];
        
        $provider_slug = $provider_map[ $provider ] ?? strtolower( $provider );
        
        // Get all models from single source of truth
        $all_models = \ContextualWP\Helpers\Smart_Model_Selector::get_all_models();
        $provider_models = $all_models[ $provider_slug ] ?? [];
        
        // Convert from associative array (nano/mini/large => model_name) to simple array of model names
        return array_values( $provider_models );
    }

    /**
     * Get available providers
     */
    private function get_available_providers() {
        $providers = [
            'OpenAI' => 'OpenAI',
            'Claude' => 'Claude'
        ];
        
        // Allow filtering of providers for extensibility
        return apply_filters( 'contextualwp_available_providers', $providers );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'ContextualWP Settings', 'contextualwp' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'contextualwp_settings_group' );
        do_settings_sections( 'contextualwp-settings' );
        echo '</div>'; // Close advanced settings div
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function field_ai_provider() {
        $options = get_option( 'contextualwp_settings' );
        $value = esc_attr( $options['ai_provider'] ?? 'OpenAI' );
        
        // Get available providers
        $providers = $this->get_available_providers();

        echo '<div class="contextualwp-settings-field">';
        echo '<select name="contextualwp_settings[ai_provider]" id="contextualwp-ai-provider">';
        
        foreach ( $providers as $key => $label ) {
            $selected = ( $value === $key ) ? 'selected' : '';
            echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        
        echo '<p class="description">' . esc_html__( 'Select your AI provider.', 'contextualwp' ) . '</p>';
        echo '</div>';
    }

    public function field_api_key() {
        $options = get_option( 'contextualwp_settings' );
        $value = esc_attr( $options['api_key'] ?? '' );
        
        echo '<div class="contextualwp-settings-field">';
        echo '<input type="password" name="contextualwp_settings[api_key]" value="' . $value . '" class="regular-text" autocomplete="off" />';
        echo '<p class="description">' . esc_html__( 'Your AI provider API key (hidden for security).', 'contextualwp' ) . '</p>';
        echo '</div>';
    }

    public function field_model() {
        $options = get_option( 'contextualwp_settings' );
        $value = esc_attr( $options['model'] ?? '' );
        
        // Get current provider to show appropriate models
        $current_provider = $options['ai_provider'] ?? 'OpenAI';
        
        // Map UI provider names to internal slugs
        $provider_map = [
            'OpenAI' => 'openai',
            'Claude' => 'claude',
        ];
        $provider_slug = $provider_map[ $current_provider ] ?? strtolower( $current_provider );
        
        // Get all models from single source of truth
        $all_models = \ContextualWP\Helpers\Smart_Model_Selector::get_all_models();
        $provider_models = $all_models[ $provider_slug ] ?? [];

        echo '<div class="contextualwp-settings-field">';
        echo '<select name="contextualwp_settings[model]" id="contextualwp-model">';
        echo '<option value="">' . esc_html__( 'Select a model...', 'contextualwp' ) . '</option>';
        
        foreach ( $provider_models as $size => $model_name ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $model_name ),
                selected( $value, $model_name, false ),
                esc_html( $model_name )
            );
        }
        echo '</select>';
        
        echo '<p class="description">' . esc_html__( 'Select your model. Available models depend on your chosen provider.', 'contextualwp' ) . '</p>';
        echo '</div>';
    }

    public function field_max_tokens() {
        $options = get_option( 'contextualwp_settings' );
        $value = esc_attr( $options['max_tokens'] ?? 1024 );
        
        echo '<div class="contextualwp-settings-field">';
        echo '<input type="number" name="contextualwp_settings[max_tokens]" value="' . $value . '" class="small-text" min="1" max="32000" />';
        echo '<p class="description">' . esc_html__( 'Maximum tokens for generation (1-32000). Default: 1024', 'contextualwp' ) . '</p>';
        echo '</div>';
    }

    public function field_temperature() {
        $options = get_option( 'contextualwp_settings' );
        $value = esc_attr( $options['temperature'] ?? 1.0 );
        
        echo '<div class="contextualwp-settings-field">';
        echo '<input type="number" step="0.01" name="contextualwp_settings[temperature]" value="' . $value . '" class="small-text" min="0" max="2" />';
        echo '<p class="description">' . esc_html__( 'Sampling temperature (0-2). Lower values are more focused, higher values more creative. Default: 1.0', 'contextualwp' ) . '</p>';
        echo '</div>';
    }

    public function field_smart_model_selection() {
        $options = get_option( 'contextualwp_settings' );
        $value = isset( $options['smart_model_selection'] ) ? (bool) $options['smart_model_selection'] : true;
        
        echo '<div class="contextualwp-settings-field">';
        echo '<label>';
        echo '<input type="checkbox" name="contextualwp_settings[smart_model_selection]" value="1" ' . checked( $value, true, false ) . ' />';
        echo ' ' . esc_html__( 'Automatically select the most efficient model based on prompt length and complexity', 'contextualwp' );
        echo '</label>';
        echo '<p class="description">' . esc_html__( 'Automatically selects 4o-mini, 4o, 4.1 or Haiku, Sonnet, Opus depending on prompt size and complexity. Only models from your selected provider will be used.', 'contextualwp' ) . '</p>';
        echo '</div>';
    }
}

// Initialize settings only in admin
if ( is_admin() ) {
    new ContextualWP_Admin_Settings();
}