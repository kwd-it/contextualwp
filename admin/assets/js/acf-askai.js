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
        'radio': 'acf-field-radio',
        'true_false': 'acf-field-true_false',
        'relationship': 'acf-field-relationship',
        'post_object': 'acf-field-post_object',
        'taxonomy': 'acf-field-taxonomy',
        'image': 'acf-field-image',
        'file': 'acf-field-file',
        'google_map': 'acf-field-google_map'
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
        var $anchor;
        if ($label.length) {
            $anchor = $label;
        } else {
            /* Table layout: labels are in thead th, not in .acf-label. Use .acf-input as anchor. */
            $anchor = $el.find('.acf-input').first();
            if (!$anchor.length) $anchor = $el;
            if (!$anchor.length) return;
        }

        var $wrap = $('<span class="contextualwp-acf-askai-wrap"></span>');
        var $icon = $('<span class="contextualwp-acf-askai" title="Ask AI about this field" role="button" tabindex="0" aria-label="Ask AI about this field"></span>');
        $wrap.append($icon);
        if ($label.length) {
            $anchor.after($wrap);
        } else {
            $anchor.hasClass('acf-input') ? $anchor.before($wrap) : $anchor.prepend($wrap);
        }
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
        if (type === 'radio') {
            var $radio = $field.find('.acf-input input[type="radio"]:checked');
            return $radio.length ? ($radio.closest('label').text().trim() || $radio.val() || '') : '';
        }
        if (type === 'image') {
            var $imgInput = $field.find('.acf-image-uploader input[type="hidden"]').first();
            if ($imgInput.length) {
                var id = $imgInput.val();
                return id ? ('Attachment ID: ' + id) : '';
            }
            return '';
        }
        if (type === 'file') {
            var $fileInput = $field.find('.acf-file-uploader input[type="hidden"]').first();
            if ($fileInput.length) {
                var fid = $fileInput.val();
                return fid ? ('Attachment ID: ' + fid) : '';
            }
            return '';
        }
        if (type === 'google_map') {
            var $hidden = $field.find('.acf-google-map input[type="hidden"]').first();
            if ($hidden.length) {
                try {
                    var val = $hidden.val();
                    if (val) {
                        var parsed = typeof val === 'string' ? JSON.parse(val) : val;
                        return (parsed && parsed.address) ? parsed.address : (val || '');
                    }
                } catch (e) { /* fallback to search input */ }
            }
            var $search = $field.find('.acf-google-map .search');
            return $search.length ? ($search.val() || '') : '';
        }
        if (type === 'true_false') {
            var $cb = $field.find('.acf-input input[type="checkbox"]').first();
            return $cb.length ? ($cb.prop('checked') ? '1' : '0') : '';
        }
        var $input = $field.find('.acf-input input[type="text"], .acf-input input[type="number"], .acf-input input[type="email"], .acf-input input[type="url"], .acf-input textarea').first();
        return $input.length ? ($input.val() || '') : '';
    }

    /**
     * Collect full ACF field metadata for the AskAI prompt.
     * Uses acf.getField() when available; falls back to DOM-only extraction.
     *
     * @param {jQuery} $field The .acf-field wrapper element.
     * @return {Object} Plain object with type, label, name, key, instructions, required,
     *   default_value, placeholder, choices, conditional_logic_summary, type_specific, value.
     */
    function collectFieldMetadata($field) {
        var         meta = {
            type: getFieldTypeFromEl($field),
            label: '',
            name: '',
            key: '',
            instructions: '',
            required: false,
            default_value: '',
            placeholder: '',
            choices: null,
            conditional_logic_summary: '',
            controlled_fields_summary: '',
            type_specific: {},
            value: getFieldValue($field)
        };

        var label = $field.find('.acf-label label').text().trim();
        if (!label) {
            var $td = $field.closest('td');
            if ($td.length) {
                var colIndex = $td.index();
                var $th = $td.closest('table').find('thead th').eq(colIndex);
                label = $th.text().trim();
            }
        }
        meta.label = label || '';
        meta.instructions = $field.find('.acf-label .description').text().trim();

        var fieldKey = $field.data('key') || $field.attr('data-key') || '';
        var fieldObj = null;
        if (typeof acf !== 'undefined' && acf.getField && fieldKey) {
            fieldObj = acf.getField(fieldKey);
        }
        if (!fieldObj && typeof acf !== 'undefined' && acf.getField) {
            fieldObj = acf.getField($field);
        }

        if (fieldObj && fieldObj.get && typeof fieldObj.get === 'function') {
            meta.name = fieldObj.get('name') || '';
            meta.key = fieldObj.get('key') || fieldKey;
            meta.instructions = meta.instructions || (fieldObj.get('instructions') || '');
            meta.required = !!fieldObj.get('required');
            meta.default_value = fieldObj.get('default_value') || '';
            meta.placeholder = fieldObj.get('placeholder') || '';

            var choices = fieldObj.get('choices');
            if (choices && typeof choices === 'object' && Object.keys(choices).length) {
                meta.choices = choices;
            }

            var cond = fieldObj.get('conditional_logic');
            if (cond && Array.isArray(cond) && cond.length) {
                meta.conditional_logic_summary = formatConditionalLogicSummary(cond, fieldObj);
            }

            meta.controlled_fields_summary = collectControlledFieldsSummary(meta.key, $field, meta.type);

            var type = (fieldObj.get('type') || meta.type || '').toLowerCase();
            meta.type = type || meta.type;

            if (type === 'relationship' || type === 'post_object') {
                var postType = fieldObj.get('post_type');
                if (postType) {
                    var arr = Array.isArray(postType) ? postType : (typeof postType === 'string' ? postType.split(/[\s,]+/).filter(Boolean) : [postType]);
                    if (arr.length) meta.type_specific.allowed_post_types = arr;
                }
                var returnFormat = fieldObj.get('return_format');
                if (returnFormat) meta.type_specific.return_format = returnFormat;
            }
            if (type === 'taxonomy') {
                var tax = fieldObj.get('taxonomy');
                if (tax) meta.type_specific.taxonomy = tax;
                var ret = fieldObj.get('return_format');
                if (ret) meta.type_specific.return_format = ret;
            }
            if (type === 'image' || type === 'file') {
                var mime = fieldObj.get('mime_types');
                if (mime) meta.type_specific.mime_types = mime;
                var ret = fieldObj.get('return_format');
                if (ret) meta.type_specific.return_format = ret;
            }
            if (type === 'true_false') {
                var msg = fieldObj.get('message');
                if (msg) meta.type_specific.message = msg;
                if (fieldObj.get('ui')) {
                    meta.type_specific.ui_on_text = fieldObj.get('ui_on_text') || 'Yes';
                    meta.type_specific.ui_off_text = fieldObj.get('ui_off_text') || 'No';
                }
            }
        } else {
            meta.name = $field.data('name') || $field.attr('data-name') || '';
            meta.key = fieldKey;
        }

        return meta;
    }

    /**
     * Build a human-readable summary of ACF conditional logic rules.
     *
     * @param {Array} conditionalLogic ACF conditional_logic array.
     * @param {Object} fieldObj ACF field instance (for resolving field keys to names).
     * @return {string}
     */
    function formatConditionalLogicSummary(conditionalLogic, fieldObj) {
        if (!conditionalLogic || !Array.isArray(conditionalLogic)) return '';
        var parts = [];
        conditionalLogic.forEach(function(group) {
            if (!Array.isArray(group)) return;
            var groupParts = [];
            group.forEach(function(rule) {
                if (!rule || !rule.field) return;
                var fieldName = rule.field;
                if (typeof acf !== 'undefined' && acf.getField) {
                    var depField = acf.getField(rule.field);
                    if (depField && depField.get && depField.get('label')) {
                        fieldName = depField.get('label');
                    }
                }
                var op = rule.operator || '==';
                var val = rule.value !== undefined && rule.value !== '' ? String(rule.value) : '[any]';
                groupParts.push(fieldName + ' ' + op + ' ' + val);
            });
            if (groupParts.length) parts.push(groupParts.join(' AND '));
        });
        return parts.length ? parts.join(' OR ') : '';
    }

    /**
     * Collect a plain-English summary of which fields are shown/hidden when this field's value changes.
     * Finds sibling fields whose conditional logic references this field.
     * For true_false fields, uses "When this is ON/OFF, these fields are shown: …" format.
     *
     * @param {string} fieldKey This field's key.
     * @param {jQuery} $field This field's wrapper element.
     * @param {string} fieldType Optional field type (e.g. 'true_false') for format selection.
     * @return {string} Empty if none; editor-friendly summary.
     */
    function collectControlledFieldsSummary(fieldKey, $field, fieldType) {
        if (!fieldKey || typeof acf === 'undefined' || !acf.getField) return '';
        var $parent = $field.closest('.acf-fields');
        if (!$parent.length) return '';
        var $siblings = $parent.children('.acf-field').not($field);
        var isTrueFalse = (fieldType || '').toLowerCase() === 'true_false';

        if (isTrueFalse) {
            var onFields = [];
            var offFields = [];
            $siblings.each(function() {
                var $sib = $(this);
                var sibKey = $sib.data('key') || $sib.attr('data-key');
                if (!sibKey) return;
                var sibField = acf.getField(sibKey);
                if (!sibField || !sibField.get) return;
                var cond = sibField.get('conditional_logic');
                if (!cond || !Array.isArray(cond)) return;
                var sibLabel = sibField.get('label') || $sib.find('.acf-label label').first().text().trim() || sibKey;
                var shownWhenOn = false;
                var shownWhenOff = false;
                cond.forEach(function(group) {
                    if (!Array.isArray(group)) return;
                    group.forEach(function(rule) {
                        if (!rule || rule.field !== fieldKey) return;
                        var val = rule.value !== undefined && rule.value !== '' ? String(rule.value) : '';
                        var op = rule.operator || '==';
                        if (val === '1' || val === 1) {
                            if (op === '==') shownWhenOn = true; else if (op === '!=') shownWhenOff = true;
                        } else if (val === '0' || val === 0 || val === '') {
                            if (op === '==') shownWhenOff = true; else if (op === '!=') shownWhenOn = true;
                        } else {
                            if (op === '==') shownWhenOn = true; else if (op === '!=') shownWhenOff = true;
                        }
                    });
                });
                if (shownWhenOn) onFields.push(sibLabel);
                if (shownWhenOff) offFields.push(sibLabel);
            });
            var parts = [];
            if (onFields.length) parts.push('When this is ON, these fields are shown: ' + onFields.join(', '));
            if (offFields.length) parts.push('When this is OFF, these fields are shown: ' + offFields.join(', '));
            return parts.length ? parts.join('. ') : '';
        }

        var parts = [];
        $siblings.each(function() {
            var $sib = $(this);
            var sibKey = $sib.data('key') || $sib.attr('data-key');
            if (!sibKey) return;
            var sibField = acf.getField(sibKey);
            if (!sibField || !sibField.get) return;
            var cond = sibField.get('conditional_logic');
            if (!cond || !Array.isArray(cond)) return;
            var sibLabel = sibField.get('label') || $sib.find('.acf-label label').first().text().trim() || sibKey;
            var whenShown = [];
            cond.forEach(function(group) {
                if (!Array.isArray(group)) return;
                group.forEach(function(rule) {
                    if (!rule || rule.field !== fieldKey) return;
                    var op = rule.operator || '==';
                    var val = rule.value !== undefined && rule.value !== '' ? String(rule.value) : 'any value';
                    whenShown.push(op === '==' ? 'value is "' + val + '"' : (op === '!=' ? 'value is not "' + val + '"' : op + ' "' + val + '"'));
                });
            });
            if (whenShown.length) parts.push(sibLabel + ': shown when ' + whenShown.join(' or '));
        });
        return parts.length ? parts.join('. ') : '';
    }

    /**
     * Build the AskAI prompt from user question and field metadata.
     *
     * @param {string} userQuestion
     * @param {Object} meta Field metadata from collectFieldMetadata.
     * @return {string}
     */
    function buildFieldHelperPrompt(userQuestion, meta) {
        var lines = [userQuestion, '', '---', 'ACF Field context:'];
        lines.push('Type: ' + (meta.type || 'unknown'));
        lines.push('Label: ' + (meta.label || '[unnamed]'));
        if (meta.name) lines.push('Name: ' + meta.name);
        if (meta.instructions) lines.push('Instructions: ' + meta.instructions);
        if (meta.required) lines.push('Required: yes');
        if (meta.default_value) lines.push('Default: ' + meta.default_value);
        if (meta.placeholder) lines.push('Placeholder: ' + meta.placeholder);
        if (meta.choices && Object.keys(meta.choices).length) {
            var choiceStr = Object.keys(meta.choices).map(function(v) {
                var lbl = meta.choices[v];
                return v === lbl ? v : v + ' => ' + lbl;
            }).join(', ');
            lines.push('Choices: ' + choiceStr);
        }
        if (meta.conditional_logic_summary) {
            lines.push('This field is shown when: ' + meta.conditional_logic_summary);
        }
        if (meta.controlled_fields_summary) {
            lines.push('When this field\'s value changes, these fields are shown/hidden: ' + meta.controlled_fields_summary);
        }
        var ts = meta.type_specific;
        if (ts && Object.keys(ts).length) {
            if (ts.allowed_post_types) lines.push('Allowed post types: ' + ts.allowed_post_types.join(', '));
            if (ts.taxonomy) lines.push('Taxonomy: ' + ts.taxonomy);
            if (ts.mime_types) lines.push('Allowed mime types: ' + ts.mime_types);
            if (ts.return_format) lines.push('Return format: ' + ts.return_format);
            if (ts.message) lines.push('Message: ' + ts.message);
            if (ts.ui_on_text || ts.ui_off_text) lines.push('Toggle labels: ON = "' + (ts.ui_on_text || 'Yes') + '", OFF = "' + (ts.ui_off_text || 'No') + '"');
        }
        lines.push('Current value: ' + (meta.value || '[empty]'));
        lines.push('');
        lines.push('Instructions for the AI (editor-focused):');
        var ft = (meta.type || '').toLowerCase();
        var choiceToggleTypes = ['true_false', 'checkbox', 'radio', 'select', 'button_group'];
        var hasCondOrControl = !!(meta.conditional_logic_summary || meta.controlled_fields_summary);
        var showWhatChanges = choiceToggleTypes.indexOf(ft) >= 0 || hasCondOrControl;

        if (showWhatChanges) {
            lines.push('- Reply in two short parts: (1) what this field controls, (2) what changes when its value is toggled (if known from metadata above).');
            lines.push('- Do not restate basic field mechanics. Do not mention field keys, field group names, IDs, or JSON.');
            if (hasCondOrControl) {
                lines.push('- The conditional logic above is authoritative. Describe it confidently in plain English. Do not hedge with "depends on implementation" or suggest searching the codebase or theme.');
            } else {
                lines.push('- If there is no conditional logic or instructions describing the effect, state briefly that the effect is not defined in the form. Suggest only: preview the page or check related fields. Do not suggest searching the codebase or theme.');
            }
            if (ft === 'true_false') {
                lines.push('- For true/false: no invented examples. Only describe effects from metadata. If inferring from label/name, label it as inference and add a step to confirm.');
            }
        } else {
            lines.push('- Reply briefly: what this field controls. Do not include a "what changes" section—omit it entirely for this field.');
            lines.push('- Do not restate basic field mechanics. Do not mention field keys, field group names, IDs, or JSON.');
        }
        lines.push('- Keep responses concise and practical for content editors.');

        return lines.join('\n');
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
        if (type === 'radio') {
            var $radios = $field.find('.acf-input input[type="radio"]');
            $radios.prop('checked', false);
            var $match = $radios.filter(function() { return $(this).val() === String(value); });
            if ($match.length) $match.first().prop('checked', true).trigger('change');
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

    /**
     * Position the Ask AI tooltip within the viewport using fixed positioning.
     * Prevents horizontal overflow and flips above the trigger when there is not enough space below.
     *
     * @param {jQuery} $tooltip The tooltip element.
     * @param {jQuery} $trigger The trigger element (icon wrap) to position relative to.
     */
    function positionAskAITooltipToViewport($tooltip, $trigger) {
        $tooltip.appendTo(document.body);
        var w = $tooltip.outerWidth();
        var h = $tooltip.outerHeight();
        var triggerRect = $trigger[0].getBoundingClientRect();
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var gap = 4;
        var padding = 8;

        var left = triggerRect.left;
        if (left + w > vw - padding) left = vw - w - padding;
        if (left < padding) left = padding;

        var belowSpace = vh - (triggerRect.bottom + gap);
        var aboveSpace = triggerRect.top - gap;
        var top;
        if (belowSpace >= h || belowSpace >= aboveSpace) {
            top = triggerRect.bottom + gap;
        } else {
            top = triggerRect.top - gap - h;
        }
        if (top < padding) top = padding;
        if (top + h > vh - padding) top = vh - h - padding;

        $tooltip.css({ position: 'fixed', left: left + 'px', top: top + 'px' });
    }

    function onAskAIClick(e) {
        e.preventDefault();
        var $icon = $(this);
        var $trigger = $icon.closest('.contextualwp-acf-askai-wrap');
        var $field = $icon.closest('.acf-field');
        var meta = collectFieldMetadata($field);
        var label = meta.label || 'this field';

        $('.contextualwp-acf-askai-tooltip').remove();
        var $tooltip = $('<div class="contextualwp-acf-askai-tooltip" role="dialog" tabindex="0">' +
            '<button type="button" class="contextualwp-acf-askai-close" aria-label="Close">×</button>' +
            '<p class="contextualwp-acf-askai-prompt-label">Ask a question about <strong>' + $('<span>').text(label).html() + '</strong></p>' +
            '<textarea class="contextualwp-acf-askai-input" rows="3" placeholder="Ask a question about this field..."></textarea>' +
            '<button type="button" class="button button-primary contextualwp-acf-askai-send">Ask AI</button>' +
            '</div>');
        positionAskAITooltipToViewport($tooltip, $trigger);
        $tooltip.find('.contextualwp-acf-askai-input').focus();

        $tooltip.on('click', '.contextualwp-acf-askai-close', function() { $tooltip.remove(); });
        $tooltip.on('click', '.contextualwp-acf-askai-send', function() {
            var userQuestion = $tooltip.find('.contextualwp-acf-askai-input').val().trim();
            if (!userQuestion) return;
            $tooltip.off('click', '.contextualwp-acf-askai-send');
            sendAskAIRequest($icon, $field, $tooltip, userQuestion, meta);
        });
    }

    function sendAskAIRequest($icon, $field, $tooltip, userQuestion, fieldContext) {
        var prompt = buildFieldHelperPrompt(userQuestion, fieldContext);

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
                prompt: prompt,
                source: 'acf_field_helper',
                field_name: fieldContext.name || '',
                field_type: (fieldContext.type || '').toLowerCase()
            })
        }).done(function(res) {
            var output = (res.ai && res.ai.output) ? String(res.ai.output).trim() : '';
            if (output) {
                $tooltip.html(
                    '<button type="button" class="contextualwp-acf-askai-close" aria-label="Close">×</button>' +
                    '<div class="contextualwp-acf-askai-response">' +
                    $('<div>').text(res.ai.output).html() +
                    '</div>'
                );
            } else if (res.ai && !output) {
                $tooltip.html(
                    '<button type="button" class="contextualwp-acf-askai-close" aria-label="Close">×</button>' +
                    '<div class="contextualwp-acf-askai-response">' +
                    '<p>The AI did not produce a visible answer (likely used all tokens on reasoning). Try a shorter question or a different model in Settings.</p>' +
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
