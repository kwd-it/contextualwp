<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ContextWP_Admin_Settings {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu_pages' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function add_menu_pages() {
        add_options_page(
            __( 'ContextWP Settings', 'contextwp' ),
            'ContextWP',
            'manage_options',
            'contextwp-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function enqueue_assets( $hook ) {
        // Only load assets on our settings page
        if ( $hook !== 'settings_page_contextwp-settings' ) {
            return;
        }

        wp_enqueue_style(
            'contextwp-settings',
            plugin_dir_url( __FILE__ ) . 'assets/css/settings.css',
            [],
            CONTEXTWP_VERSION
        );

        wp_enqueue_script(
            'contextwp-settings',
            plugin_dir_url( __FILE__ ) . 'assets/js/settings.js',
            [],
            CONTEXTWP_VERSION,
            true
        );
    }

    public function register_settings() {
        register_setting( 'contextwp_settings_group', 'contextwp_settings', [ $this, 'sanitize_settings' ] );

        add_settings_section(
            'contextwp_main_section',
            __( 'AI Provider Settings', 'contextwp' ),
            [ $this, 'section_description' ],
            'contextwp-settings'
        );

        add_settings_field(
            'ai_provider',
            __( 'AI Provider', 'contextwp' ),
            [ $this, 'field_ai_provider' ],
            'contextwp-settings',
            'contextwp_main_section'
        );
        add_settings_field(
            'api_key',
            __( 'API Key', 'contextwp' ),
            [ $this, 'field_api_key' ],
            'contextwp-settings',
            'contextwp_main_section'
        );
        add_settings_field(
            'model',
            __( 'Model', 'contextwp' ),
            [ $this, 'field_model' ],
            'contextwp-settings',
            'contextwp_main_section'
        );

        // Advanced settings section
        add_settings_section(
            'contextwp_advanced_section',
            __( 'Advanced Settings', 'contextwp' ),
            [ $this, 'advanced_section_description' ],
            'contextwp-settings'
        );

        add_settings_field(
            'max_tokens',
            __( 'Max Tokens', 'contextwp' ),
            [ $this, 'field_max_tokens' ],
            'contextwp-settings',
            'contextwp_advanced_section'
        );
        add_settings_field(
            'temperature',
            __( 'Temperature', 'contextwp' ),
            [ $this, 'field_temperature' ],
            'contextwp-settings',
            'contextwp_advanced_section'
        );
        add_settings_field(
            'smart_model_selection',
            __( 'Enable Smart Model Selection', 'contextwp' ),
            [ $this, 'field_smart_model_selection' ],
            'contextwp-settings',
            'contextwp_advanced_section'
        );
    }

    public function section_description() {
        echo '<p>' . esc_html__( 'Configure your AI provider settings for ContextWP.', 'contextwp' ) . '</p>';
    }

    public function advanced_section_description() {
        echo '<div class="contextwp-advanced-toggle">';
        echo '<button type="button" id="contextwp-advanced-toggle" class="button button-secondary">';
        echo '<span class="toggle-text">' . esc_html__( 'Show Advanced Settings', 'contextwp' ) . '</span>';
        echo '<span class="toggle-icon">▼</span>';
        echo '</button>';
        echo '</div>';
        echo '<div id="contextwp-advanced-settings" class="contextwp-advanced-settings" style="display: none;">';
        echo '<p>' . esc_html__( 'Fine-tune your AI model behavior with these advanced settings.', 'contextwp' ) . '</p>';
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
     * Get valid models for a provider
     */
    private function get_valid_models_for_provider( $provider ) {
        $models = [
            'OpenAI' => ['gpt-4', 'gpt-3.5-turbo'],
            'Claude' => ['claude-3-opus', 'claude-3-sonnet']
        ];
        
        // Allow filtering of models for extensibility
        $models = apply_filters( 'contextwp_provider_models', $models, $provider );
        
        return $models[ $provider ] ?? [];
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
        return apply_filters( 'contextwp_available_providers', $providers );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'ContextWP Settings', 'contextwp' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'contextwp_settings_group' );
        do_settings_sections( 'contextwp-settings' );
        echo '</div>'; // Close advanced settings div
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function field_ai_provider() {
        $options = get_option( 'contextwp_settings' );
        $value = esc_attr( $options['ai_provider'] ?? 'OpenAI' );
        
        // Get available providers
        $providers = $this->get_available_providers();

        echo '<div class="contextwp-settings-field">';
        echo '<select name="contextwp_settings[ai_provider]" id="contextwp-ai-provider">';
        
        foreach ( $providers as $key => $label ) {
            $selected = ( $value === $key ) ? 'selected' : '';
            echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        
        echo '<p class="description">' . esc_html__( 'Select your AI provider.', 'contextwp' ) . '</p>';
        echo '</div>';
    }

    public function field_api_key() {
        $options = get_option( 'contextwp_settings' );
        $value = esc_attr( $options['api_key'] ?? '' );
        
        echo '<div class="contextwp-settings-field">';
        echo '<input type="password" name="contextwp_settings[api_key]" value="' . $value . '" class="regular-text" autocomplete="off" />';
        echo '<p class="description">' . esc_html__( 'Your AI provider API key (hidden for security).', 'contextwp' ) . '</p>';
        echo '</div>';
    }

    public function field_model() {
        $options = get_option( 'contextwp_settings' );
        $value = esc_attr( $options['model'] ?? '' );
        
        // Get current provider to show appropriate models
        $current_provider = $options['ai_provider'] ?? 'OpenAI';
        $models = $this->get_valid_models_for_provider( $current_provider );
        
        // Model labels for display
        $model_labels = [
            'gpt-4' => 'GPT-4',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'claude-3-opus' => 'Claude 3 Opus',
            'claude-3-sonnet' => 'Claude 3 Sonnet'
        ];

        echo '<div class="contextwp-settings-field">';
        echo '<select name="contextwp_settings[model]" id="contextwp-model">';
        echo '<option value="">' . esc_html__( 'Select a model...', 'contextwp' ) . '</option>';
        
        foreach ( $models as $model ) {
            $selected = ( $value === $model ) ? 'selected' : '';
            $label = $model_labels[ $model ] ?? $model;
            echo '<option value="' . esc_attr($model) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        
        echo '<p class="description">' . esc_html__( 'Select your model. Available models depend on your chosen provider.', 'contextwp' ) . '</p>';
        echo '</div>';
    }

    public function field_max_tokens() {
        $options = get_option( 'contextwp_settings' );
        $value = esc_attr( $options['max_tokens'] ?? 1024 );
        
        echo '<div class="contextwp-settings-field">';
        echo '<input type="number" name="contextwp_settings[max_tokens]" value="' . $value . '" class="small-text" min="1" max="32000" />';
        echo '<p class="description">' . esc_html__( 'Maximum tokens for generation (1-32000). Default: 1024', 'contextwp' ) . '</p>';
        echo '</div>';
    }

    public function field_temperature() {
        $options = get_option( 'contextwp_settings' );
        $value = esc_attr( $options['temperature'] ?? 1.0 );
        
        echo '<div class="contextwp-settings-field">';
        echo '<input type="number" step="0.01" name="contextwp_settings[temperature]" value="' . $value . '" class="small-text" min="0" max="2" />';
        echo '<p class="description">' . esc_html__( 'Sampling temperature (0-2). Lower values are more focused, higher values more creative. Default: 1.0', 'contextwp' ) . '</p>';
        echo '</div>';
    }

    public function field_smart_model_selection() {
        $options = get_option( 'contextwp_settings' );
        $value = isset( $options['smart_model_selection'] ) ? (bool) $options['smart_model_selection'] : true;
        
        echo '<div class="contextwp-settings-field">';
        echo '<label>';
        echo '<input type="checkbox" name="contextwp_settings[smart_model_selection]" value="1" ' . checked( $value, true, false ) . ' />';
        echo ' ' . esc_html__( 'Automatically select the most efficient model based on prompt length and complexity', 'contextwp' );
        echo '</label>';
        echo '<p class="description">' . esc_html__( 'When enabled, ContextWP will automatically choose between GPT-3.5 Turbo, GPT-4, Claude Sonnet, or Claude Opus based on your prompt. When disabled, it uses the manually selected model.', 'contextwp' ) . '</p>';
        echo '</div>';
    }
}

// Initialize settings only in admin
if ( is_admin() ) {
    new ContextWP_Admin_Settings();
} 