/**
 * ContextualWP ACF AskAI – adds "Ask AI" control next to supported ACF fields.
 * Uses ACF's ready_field and append_field lifecycle hooks for reliability with
 * repeaters, flexible content, and dynamically added fields.
 */
(function($){
    'use strict';

    var config = window.contextualwpACFAskAI || {};
    if (window.console && window.console.log) {
        window.console.log('[ContextualWP ACF AskAI] Script loaded. Config:', config && Object.keys(config).length ? 'present' : 'missing', 'debug:', !!(config && config.debug), 'acf:', typeof window.acf !== 'undefined' ? 'defined' : 'undefined');
    }
    var supportedTypes = (config.supportedTypes || ['text', 'textarea', 'wysiwyg', 'number', 'email', 'url', 'select', 'true_false']).map(function(t){ return t.toLowerCase(); });
    var debugEnabled = !!config.debug;

    var TYPE_CLASS_MAP = {
        'text': 'acf-field-text',
        'textarea': 'acf-field-textarea',
        'wysiwyg': 'acf-field-wysiwyg',
        'number': 'acf-field-number',
        'email': 'acf-field-email',
        'url': 'acf-field-url',
        'select': 'acf-field-select',
        'true_false': 'acf-field-true_false',
        'relationship': 'acf-field-relationship',
        'post_object': 'acf-field-post_object',
        'taxonomy': 'acf-field-taxonomy'
    };

    function log() {
        if (debugEnabled && window.console && window.console.log) {
            var args = ['[ContextualWP ACF AskAI]'].concat(Array.prototype.slice.call(arguments));
            window.console.log.apply(window.console, args);
        }
    }

    function getFieldType(field) {
        var type = '';
        if (field && field.get && typeof field.get === 'function') {
            type = field.get('type') || '';
        } else if (field && field.type) {
            type = field.type;
        } else if (field && field.$el) {
            var $el = $(field.$el);
            var k;
            for (k in TYPE_CLASS_MAP) {
                if (TYPE_CLASS_MAP.hasOwnProperty(k) && $el.hasClass(TYPE_CLASS_MAP[k])) return k;
            }
        }
        return String(type).toLowerCase();
    }

    function isSupportedType(field) {
        var type = getFieldType(field);
        if (!type && field && field.$el) {
            var $el = $(field.$el);
            var k;
            for (k in TYPE_CLASS_MAP) {
                if (TYPE_CLASS_MAP.hasOwnProperty(k) && $el.hasClass(TYPE_CLASS_MAP[k]) && supportedTypes.indexOf(k) !== -1) {
                    return true;
                }
            }
        }
        return type && supportedTypes.indexOf(type) !== -1;
    }

    function addAskAIControl(field) {
        var $el;
        if (field && field.$el) {
            $el = $(field.$el);
        } else if (field && field.jquery) {
            $el = field;
        } else {
            return;
        }
        if (!$el || !$el.length) return;

        if ($el.find('.contextualwp-acf-askai').length) return;
        if (!isSupportedType(field)) return;

        var $label = $el.find('.acf-label label').first();
        if ($label.length === 0) return;

        var $wrap = $('<span class="contextualwp-acf-askai-wrap"></span>');
        var $icon = $('<span class="contextualwp-acf-askai" title="Ask AI about this field" role="button" tabindex="0" aria-label="Ask AI about this field"></span>');
        $wrap.append($icon);
        $label.after($wrap);
        var name = field && field.get ? field.get('name') : (field.name || '');
        var type = getFieldType(field);
        log('Ask AI control added to field:', name, 'type:', type);
    }

    function attachFieldHooks() {
        if (typeof acf === 'undefined') {
            log('ACF not available, skipping hooks');
            return;
        }

        acf.addAction('ready_field', function(field) {
            addAskAIControl(field);
        });

        acf.addAction('append_field', function(field) {
            addAskAIControl(field);
        });

        // Process existing fields immediately – our script may load after ACF's
        // 'ready' has fired (e.g. when enqueued in footer), so we would otherwise
        // miss initial fields and only see append_field for new repeater rows.
        var fields = (acf.getFields && acf.getFields()) || [];
        if (!fields.length && $('.acf-field').length) {
            fields = $('.acf-field').map(function() { return $(this); }).get();
        }
        if (fields && fields.length) {
            for (var i = 0; i < fields.length; i++) {
                addAskAIControl(fields[i]);
            }
            log('Processed', fields.length, 'existing fields');
        }

        log('ACF ready_field and append_field hooks attached');
    }

    function init() {
        if (typeof acf !== 'undefined') {
            acf.addAction('ready', function() {
                attachFieldHooks();
            });
            // Run immediately too – we may have loaded after ACF's ready fired
            attachFieldHooks();
        } else {
            log('ACF global not found – ensure acf-input is loaded before this script');
        }

        $(document).on('click', '.contextualwp-acf-askai', onAskAIClick);
        $(document).on('keydown', '.contextualwp-acf-askai', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).trigger('click');
            }
        });

        $(document).on('mousedown', function(e) {
            $('.contextualwp-acf-askai-tooltip').each(function() {
                if (!$(e.target).closest('.contextualwp-acf-askai-tooltip, .contextualwp-acf-askai, .contextualwp-acf-askai-wrap').length) {
                    $(this).remove();
                }
            });
        });

    }

    function getFieldTypeFromEl($field) {
        if ($field.data('field-type')) return $field.data('field-type');
        var k;
        for (k in TYPE_CLASS_MAP) {
            if (TYPE_CLASS_MAP.hasOwnProperty(k) && $field.hasClass(TYPE_CLASS_MAP[k])) return k;
        }
        return 'text';
    }

    function getFieldValue($field) {
        var type = getFieldTypeFromEl($field);
        if (type === 'wysiwyg') {
            var $textarea = $field.find('.acf-input textarea.wp-editor-area');
            if ($textarea.length) {
                var id = $textarea.attr('id');
                if (id && typeof tinymce !== 'undefined' && tinymce.get(id)) {
                    return tinymce.get(id).getContent();
                }
                return $textarea.val() || '';
            }
        }
        if (type === 'relationship') {
            var titles = $field.find('.acf-relationship .values-list .acf-rel-item').map(function() {
                return $(this).clone().children().remove().end().text().trim();
            }).get();
            return titles.length ? titles.join(', ') : '';
        }
        if (type === 'post_object') {
            var $select = $field.find('.acf-input select').first();
            if ($select.length) {
                var vals = $select.val();
                if (Array.isArray(vals)) {
                    return $select.find('option:selected').map(function() { return $(this).text().trim(); }).get().join(', ') || '';
                }
                var $opt = $select.find('option:selected');
                return $opt.length ? $opt.text().trim() : '';
            }
            return '';
        }
        if (type === 'taxonomy') {
            var $checkboxes = $field.find('.acf-input input[type="checkbox"]:checked');
            if ($checkboxes.length) {
                return $checkboxes.map(function() {
                    return $(this).closest('label').text().trim() || $(this).val();
                }).get().join(', ') || '';
            }
            var $radio = $field.find('.acf-input input[type="radio"]:checked');
            if ($radio.length) {
                return $radio.closest('label').text().trim() || $radio.val() || '';
            }
            var $taxSelect = $field.find('.acf-input select').first();
            if ($taxSelect.length) {
                var taxVals = $taxSelect.val();
                if (Array.isArray(taxVals)) {
                    return $taxSelect.find('option:selected').map(function() { return $(this).text().trim(); }).get().join(', ') || '';
                }
                var $taxOpt = $taxSelect.find('option:selected');
                return $taxOpt.length ? $taxOpt.text().trim() : '';
            }
            return '';
        }
        if (type === 'select') {
            var $select = $field.find('.acf-input select').first();
            return $select.length ? ($select.val() || '') : '';
        }
        if (type === 'true_false') {
            var $cb = $field.find('.acf-input input[type="checkbox"]').first();
            return $cb.length ? ($cb.prop('checked') ? '1' : '0') : '';
        }
        var $input = $field.find('.acf-input input[type="text"], .acf-input input[type="number"], .acf-input input[type="email"], .acf-input input[type="url"], .acf-input textarea').first();
        return $input.length ? ($input.val() || '') : '';
    }

    function setFieldValue($field, value) {
        var type = getFieldTypeFromEl($field);
        if (type === 'wysiwyg') {
            var $textarea = $field.find('.acf-input textarea.wp-editor-area');
            if ($textarea.length) {
                var id = $textarea.attr('id');
                if (id && typeof tinymce !== 'undefined' && tinymce.get(id)) {
                    tinymce.get(id).setContent(value);
                    return;
                }
                $textarea.val(value);
                return;
            }
        }
        if (type === 'select') {
            var $select = $field.find('.acf-input select').first();
            if ($select.length) $select.val(value).trigger('change');
            return;
        }
        if (type === 'true_false') {
            var $cb = $field.find('.acf-input input[type="checkbox"]').first();
            if ($cb.length) $cb.prop('checked', value === '1' || value === 1).trigger('change');
            return;
        }
        var $input = $field.find('.acf-input input[type="text"], .acf-input input[type="number"], .acf-input input[type="email"], .acf-input input[type="url"], .acf-input textarea').first();
        if ($input.length) $input.val(value).trigger('change');
    }

    function onAskAIClick(e) {
        e.preventDefault();
        var $icon = $(this);
        var $field = $icon.closest('.acf-field');
        var label = $field.find('.acf-label label').text().trim();
        var instructions = $field.find('.acf-label .description').text().trim();
        var value = getFieldValue($field);

        $field.find('.contextualwp-acf-askai-tooltip').remove();
        var $tooltip = $('<div class="contextualwp-acf-askai-tooltip" role="dialog" tabindex="0">' +
            '<button type="button" class="contextualwp-acf-askai-close" aria-label="Close">×</button>' +
            '<p class="contextualwp-acf-askai-prompt-label">Ask a question about <strong>' + $('<span>').text(label).html() + '</strong></p>' +
            '<textarea class="contextualwp-acf-askai-input" rows="3" placeholder="Ask a question about this field..."></textarea>' +
            '<button type="button" class="button button-primary contextualwp-acf-askai-send">Ask AI</button>' +
            '</div>');
        $icon.closest('.contextualwp-acf-askai-wrap').append($tooltip);
        $tooltip.find('.contextualwp-acf-askai-input').focus();

        $tooltip.on('click', '.contextualwp-acf-askai-close', function() { $tooltip.remove(); });
        $tooltip.on('click', '.contextualwp-acf-askai-send', function() {
            var userQuestion = $tooltip.find('.contextualwp-acf-askai-input').val().trim();
            if (!userQuestion) return;
            $tooltip.off('click', '.contextualwp-acf-askai-send');
            sendAskAIRequest($icon, $field, $tooltip, userQuestion, { label: label, instructions: instructions, value: value });
        });
    }

    function sendAskAIRequest($icon, $field, $tooltip, userQuestion, fieldContext) {
        var prompt = userQuestion + '\n\n---\nField: ' + fieldContext.label;
        if (fieldContext.instructions) prompt += '\nInstructions: ' + fieldContext.instructions;
        prompt += '\nCurrent value: ' + (fieldContext.value || '[empty]');
        prompt += '\n\nGive a clear, definitive answer. Do not hedge or cover multiple options unless the answer genuinely depends on site-specific code you cannot inspect.';

        $icon.addClass('loading');
        $tooltip.html('<div class="contextualwp-acf-askai-loading">Asking AI…</div>').find('.contextualwp-acf-askai-loading').focus();

        var contextId = config.contextId || (config.postType + '-' + config.postId);
        if (!contextId || contextId === '-0') contextId = 'multi';

        $.ajax({
            url: config.endpoint,
            method: 'POST',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', config.nonce); },
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                context_id: contextId,
                prompt: prompt
            })
        }).done(function(res) {
            if (res.ai && res.ai.output) {
                $tooltip.html(
                    '<button type="button" class="contextualwp-acf-askai-close" aria-label="Close">×</button>' +
                    '<div class="contextualwp-acf-askai-response">' +
                    $('<div>').text(res.ai.output).html() +
                    '</div>'
                );
            } else {
                var errMsg = (res && res.message) ? res.message : 'No response';
                if (res && res.code) errMsg = (res.code + ': ') + errMsg;
                $tooltip.text(errMsg);
            }
        }).fail(function(xhr) {
            var msg = 'Request failed';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            else if (xhr.responseJSON && xhr.responseJSON.code) msg = xhr.responseJSON.code + ': ' + (xhr.responseJSON.message || '');
            else if (xhr.status) msg = 'HTTP ' + xhr.status + (xhr.statusText ? ' ' + xhr.statusText : '');
            $tooltip.text(msg);
        }).always(function() {
            $icon.removeClass('loading');
        });
    }

    $(function() { init(); });

})(jQuery);
