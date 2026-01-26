/**
 * ContextualWP Settings Page JavaScript
 * Handles dynamic model filtering based on selected AI provider
 */

(function() {
    'use strict';

    // Model configurations from backend (single source of truth)
    const providerModels = window.ContextualWPModels || {};

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

        // Handle copy schema button
        initCopySchema();
    }

    /**
     * Update model dropdown options based on selected provider
     * @param {string} provider - Selected AI provider
     * @param {string} currentValue - Current model value to preserve if valid
     */
    function updateModelOptions(provider, currentValue) {
        const modelSelect = document.getElementById('contextualwp-model');
        const providerKey = provider.toLowerCase();
        const modelMap = providerModels[providerKey] || {};
        const models = Object.values(modelMap).map(name => ({
            value: name,
            label: name
        }));

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

    /**
     * Initialize copy schema functionality
     */
    function initCopySchema() {
        const copyButton = document.getElementById('contextualwp-copy-schema');
        const copyNotice = document.getElementById('contextualwp-copy-notice');
        const fallbackTextarea = document.getElementById('contextualwp-schema-fallback');

        if (!copyButton || !copyNotice) {
            return;
        }

        copyButton.addEventListener('click', async function() {
            const button = this;
            const originalText = button.textContent;

            // Disable button during fetch
            button.disabled = true;
            button.textContent = 'Loading...';
            copyNotice.style.display = 'none';

            try {
                // Fetch schema from REST API
                const settings = window.ContextualWPSettings || {};
                const restUrl = settings.restUrl || '/wp-json/contextualwp/v1/schema';
                const nonce = settings.nonce || '';

                const response = await fetch(restUrl, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': nonce,
                        'Content-Type': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch schema: ' + response.statusText);
                }

                const schema = await response.json();
                const schemaJson = JSON.stringify(schema, null, 2);

                // Try modern clipboard API first
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(schemaJson);
                    showSuccessNotice(copyNotice, button, originalText);
                } else {
                    // Fallback for older browsers
                    fallbackCopyToClipboard(schemaJson, fallbackTextarea);
                    showSuccessNotice(copyNotice, button, originalText);
                }
            } catch (error) {
                console.error('Error copying schema:', error);
                copyNotice.textContent = 'Error: ' + error.message;
                copyNotice.className = 'contextualwp-copy-notice contextualwp-copy-error';
                copyNotice.style.display = 'inline-block';
                button.disabled = false;
                button.textContent = originalText;
            }
        });
    }

    /**
     * Show success notice after copying
     */
    function showSuccessNotice(notice, button, originalText) {
        notice.textContent = 'Schema copied to clipboard';
        notice.className = 'contextualwp-copy-notice contextualwp-copy-success';
        notice.style.display = 'inline-block';

        // Re-enable button
        button.disabled = false;
        button.textContent = originalText;

        // Hide notice after 3 seconds
        setTimeout(function() {
            notice.style.display = 'none';
        }, 3000);
    }

    /**
     * Fallback copy method for older browsers
     */
    function fallbackCopyToClipboard(text, textarea) {
        if (!textarea) {
            return;
        }

        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '0';
        textarea.style.top = '0';
        textarea.style.opacity = '0';
        textarea.focus();
        textarea.select();

        try {
            const successful = document.execCommand('copy');
            if (!successful) {
                throw new Error('Copy command failed');
            }
        } catch (err) {
            throw new Error('Fallback copy failed: ' + err.message);
        } finally {
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
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