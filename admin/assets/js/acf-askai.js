jQuery(function($){
    function addAskAIIcons() {
        // ACF fields have .acf-field, label is .acf-label label, input is .acf-input :input
        $('.acf-field').each(function(){
            var $field = $(this);
            if ($field.find('.contextualwp-acf-askai').length) return; // already added
            var $label = $field.find('.acf-label label').first();
            if ($label.length === 0) return;
            var $icon = $('<span class="contextualwp-acf-askai" title="Ask AI about this field"></span>');
            $label.after($icon);
        });
    }
    addAskAIIcons();
    // In case of dynamic ACF fields (repeaters, etc.)
    $(document).on('acf/setup_fields', addAskAIIcons);

    $(document).on('click', '.contextualwp-acf-askai', function(e){
        e.preventDefault();
        var $icon = $(this);
        var $field = $icon.closest('.acf-field');
        var label = $field.find('.acf-label label').text().trim();
        var instructions = $field.find('.acf-label .description').text().trim();
        var $input = $field.find('.acf-input :input').first();
        var value = $input.val();
        var prompt = 'Field: ' + label + '\n';
        if (instructions) prompt += 'Instructions: ' + instructions + '\n';
        prompt += 'Current value: ' + (value || '[empty]');
        $icon.addClass('loading');
        // Remove any previous tooltip
        $field.find('.contextualwp-acf-askai-tooltip').remove();
        var $tooltip = $('<div class="contextualwp-acf-askai-tooltip" role="tooltip" tabindex="0">Asking AI...</div>');
        $icon.after($tooltip);
        $tooltip.focus();
        $.ajax({
            url: contextualwpACFAskAI.endpoint,
            method: 'POST',
            beforeSend: function(xhr){ xhr.setRequestHeader('X-WP-Nonce', contextualwpACFAskAI.nonce); },
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                context_id: contextualwpACFAskAI.postType + '-' + contextualwpACFAskAI.postId,
                prompt: prompt
            })
        }).done(function(res){
            if(res.ai && res.ai.output){
                $tooltip.html('<div class="contextualwp-acf-askai-response">' +
                    $('<div>').text(res.ai.output).html() +
                    '</div>' +
                    '<div class="contextualwp-acf-askai-actions">' +
                        '<button type="button" class="button button-small contextualwp-insert-into-post" aria-label="Insert AI output into post">Insert into post</button>' +
                        '<button type="button" class="button button-small contextualwp-replace-post-content" aria-label="Replace post content with AI output">Replace post content</button>' +
                    '</div>'
                );
                $tooltip.data('ai-output', res.ai.output);
            } else {
                $tooltip.text('No response');
            }
        }).fail(function(xhr){
            var msg = 'Error';
            if(xhr.responseJSON && xhr.responseJSON.message){
                msg = xhr.responseJSON.message;
            }
            $tooltip.text(msg);
        }).always(function(){
            $icon.removeClass('loading');
        });
    });
    // Dismiss tooltip on click outside
    $(document).on('mousedown', function(e){
        $('.contextualwp-acf-askai-tooltip').each(function(){
            if (!$(e.target).closest('.contextualwp-acf-askai-tooltip, .contextualwp-acf-askai').length) {
                $(this).remove();
            }
        });
    });

    // Insert/replace post content handlers
    $(document).on('click', '.contextualwp-insert-into-post', function(){
        var $tooltip = $(this).closest('.contextualwp-acf-askai-tooltip');
        var aiOutput = $tooltip.data('ai-output');
        if (!aiOutput) return;
        // Try block editor first
        if (window.wp && wp.data && wp.data.dispatch) {
            try {
                var current = wp.data.select('core/editor').getEditedPostAttribute('content') || '';
                wp.data.dispatch('core/editor').editPost({ content: current + '\n' + aiOutput });
                $tooltip.text('Inserted into post content!');
                return;
            } catch (e) {}
        }
        // Fallback: TinyMCE
        if (window.tinymce && tinymce.activeEditor) {
            tinymce.activeEditor.execCommand('mceInsertContent', false, aiOutput);
            $tooltip.text('Inserted into post content!');
        }
    });
    $(document).on('click', '.contextualwp-replace-post-content', function(){
        var $tooltip = $(this).closest('.contextualwp-acf-askai-tooltip');
        var aiOutput = $tooltip.data('ai-output');
        if (!aiOutput) return;
        // Try block editor first
        if (window.wp && wp.data && wp.data.dispatch) {
            try {
                wp.data.dispatch('core/editor').editPost({ content: aiOutput });
                $tooltip.text('Post content replaced!');
                return;
            } catch (e) {}
        }
        // Fallback: TinyMCE
        if (window.tinymce && tinymce.activeEditor) {
            tinymce.activeEditor.setContent(aiOutput);
            $tooltip.text('Post content replaced!');
        }
    });
}); 