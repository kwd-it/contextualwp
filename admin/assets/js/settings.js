/**
 * ContextualWP Settings Page JavaScript
 * Handles dynamic model filtering based on selected AI provider
 */

(function() {
    'use strict';

    // Model configurations for each provider
    const providerModels = {
        'OpenAI': [
            { value: 'gpt-4', label: 'GPT-4' },
            { value: 'gpt-3.5-turbo', label: 'GPT-3.5 Turbo' }
        ],
        'Claude': [
            { value: 'claude-3-opus', label: 'Claude 3 Opus' },
            { value: 'claude-3-sonnet', label: 'Claude 3 Sonnet' }
        ]
    };

    /**
     * Initialize the settings page functionality
     */
    function initSettings() {
        const providerSelect = document.getElementById('contextualwp-ai-provider');
        const modelSelect = document.getElementById('contextualwp-model');
        const advancedToggle = document.getElementById('contextualwp-advanced-toggle');
        const advancedSettings = document.getElementById('contextualwp-advanced-settings');

        if (!providerSelect || !modelSelect) {
            return;
        }

        // Store the current model value to preserve it if valid
        const currentModelValue = modelSelect.value;

        // Add event listener for provider changes
        providerSelect.addEventListener('change', function() {
            updateModelOptions(this.value, currentModelValue);
        });

        // Initialize with current provider selection
        updateModelOptions(providerSelect.value, currentModelValue);

        // Handle advanced settings toggle
        if (advancedToggle && advancedSettings) {
            advancedToggle.addEventListener('click', function() {
                const isVisible = advancedSettings.style.display !== 'none';
                
                if (isVisible) {
                    advancedSettings.style.display = 'none';
                    this.querySelector('.toggle-text').textContent = 'Show Advanced Settings';
                    this.querySelector('.toggle-icon').textContent = '▼';
                } else {
                    advancedSettings.style.display = 'block';
                    this.querySelector('.toggle-text').textContent = 'Hide Advanced Settings';
                    this.querySelector('.toggle-icon').textContent = '▲';
                }
            });
        }
    }

    /**
     * Update model dropdown options based on selected provider
     * @param {string} provider - Selected AI provider
     * @param {string} currentValue - Current model value to preserve if valid
     */
    function updateModelOptions(provider, currentValue) {
        const modelSelect = document.getElementById('contextualwp-model');
        const models = providerModels[provider] || [];

        // Clear existing options
        modelSelect.innerHTML = '';

        if (models.length === 0) {
            // Add placeholder for no models
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Select a model...';
            placeholder.disabled = true;
            placeholder.selected = true;
            modelSelect.appendChild(placeholder);
            return;
        }

        // Add model options
        let hasValidCurrentValue = false;
        models.forEach(model => {
            const option = document.createElement('option');
            option.value = model.value;
            option.textContent = model.label;
            
            // Check if this is the current value
            if (model.value === currentValue) {
                option.selected = true;
                hasValidCurrentValue = true;
            }
            
            modelSelect.appendChild(option);
        });

        // If current value is not valid for this provider, select first option
        if (!hasValidCurrentValue && models.length > 0) {
            modelSelect.selectedIndex = 0;
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initSettings();
        });
    } else {
        initSettings();
    }

})(); 