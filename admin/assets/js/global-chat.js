jQuery(function($){
    // Insert and show the floating icon
    var $icon = $('<div id="contextualwp-floating-chat-icon" title="Open ContextualWP Chat"></div>');
    if ($('#contextualwp-floating-chat-icon').length === 0) {
        $('body').append($icon);
    }
    var $modal = $('#contextualwp-floating-chat-modal');
    var $container = $('#contextualwp-floating-chat');
    if ($container.length === 0) {
        $container = $('<div id="contextualwp-floating-chat"></div>').appendTo('body');
    }
    $container.show();
    $icon = $('#contextualwp-floating-chat-icon');
    $modal = $('#contextualwp-floating-chat-modal');
    var $expandBtn = $('#contextualwp-floating-chat-modal-expand');

    // Maximize / restore toggle
    $expandBtn.attr('aria-label', 'Expand chat').on('click', function(){
        var isMax = $modal.hasClass('contextualwp-chat-maximized');
        $modal.toggleClass('contextualwp-chat-maximized');
        if (isMax) {
            $expandBtn.attr({ 'title': 'Expand chat', 'aria-label': 'Expand chat' }).html('&#x2922;');
        } else {
            $expandBtn.attr({ 'title': 'Restore chat', 'aria-label': 'Restore chat' }).html('&#x2921;');
        }
    });

    // Open modal (use display:flex so flex layout works for scrollable messages)
    $icon.on('click', function(){
        $modal.attr({'role':'dialog','aria-modal':'true','aria-label':'ContextualWP Chat'});
        $modal.css('display', 'flex');
        setTimeout(function(){ $('#contextualwp-floating-chat-prompt').focus(); }, 100);
    });
    // Close modal
    $('#contextualwp-floating-chat-modal-close').attr('aria-label','Close chat').on('click', function(){
        $modal.css('display', 'none');
        $icon.focus();
    });
    // ESC closes modal
    $(document).on('keydown', function(e){
        if (e.key === 'Escape') $modal.css('display', 'none');
    });

    var $messages = $('#contextualwp-floating-chat-messages');

    function renderMarkdown(text) {
        if (typeof marked !== 'undefined' && marked.parse) {
            try {
                marked.setOptions({ breaks: true });
                return marked.parse(String(text || ''));
            } catch (err) {
                return $('<div>').text(text).html();
            }
        }
        return $('<div>').text(text).html().replace(/\n/g, '<br>');
    }

    function appendMessage(role, text) {
        var cls = role === 'user' ? 'contextualwp-chat-user' : 'contextualwp-chat-ai';
        var bubble = $('<div>').addClass('contextualwp-chat-bubble').addClass(cls);
        if (role === 'user') {
            bubble.text(text);
        } else {
            bubble.html(renderMarkdown(text));
        }
        $messages.append(bubble);
        $messages.scrollTop($messages[0].scrollHeight);
    }

    $('#contextualwp-floating-chat-send').attr('aria-label','Send message').on('click', function(){
        var prompt = $('#contextualwp-floating-chat-prompt').val();
        if (!prompt) return;
        appendMessage('user', prompt);
        $('#contextualwp-floating-chat-prompt').val('');
        var $btn = $(this);
        $btn.prop('disabled', true).text('Sending...');
        $.ajax({
            url: contextualwpGlobalChat.endpoint,
            method: 'POST',
            beforeSend: function(xhr){ xhr.setRequestHeader('X-WP-Nonce', contextualwpGlobalChat.nonce); },
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                context_id: 'multi',
                prompt: prompt,
                format: 'markdown'
            })
        }).done(function(res){
            if(res.ai && res.ai.output){
                appendMessage('ai', res.ai.output);
            } else {
                appendMessage('ai', 'No response');
            }
        }).fail(function(xhr){
            var msg = 'Error';
            if(xhr.responseJSON && xhr.responseJSON.message){
                msg = xhr.responseJSON.message;
            }
            appendMessage('ai', msg);
        }).always(function(){
            $btn.prop('disabled', false).text('Send');
        });
    });
});
