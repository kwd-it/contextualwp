jQuery(function($){
    // Insert and show the floating icon
    var $icon = $('<div id="contextualwp-floating-chat-icon" title="Open ContextualWP Chat"></div>');
    if ($('#contextualwp-floating-chat-icon').length === 0) {
        $('body').append($icon);
    }
    var $modal = $('#contextualwp-floating-chat-modal');
    var $container = $('#contextualwp-floating-chat');
    if ($container.length === 0) {
        // fallback: create container if not present
        $container = $('<div id="contextualwp-floating-chat"></div>').appendTo('body');
    }
    $container.show();
    $icon = $('#contextualwp-floating-chat-icon');
    $modal = $('#contextualwp-floating-chat-modal');

    // Open modal
    $icon.on('click', function(){
        $modal.attr({'role':'dialog','aria-modal':'true','aria-label':'ContextualWP Chat'});
        $modal.show();
        setTimeout(function(){ $('#contextualwp-floating-chat-prompt').focus(); }, 100);
    });
    // Close modal
    $('#contextualwp-floating-chat-modal-close').attr('aria-label','Close chat').on('click', function(){
        $modal.hide();
        $icon.focus();
    });
    // ESC closes modal
    $(document).on('keydown', function(e){
        if (e.key === 'Escape') $modal.hide();
    });

    var $messages = $('#contextualwp-floating-chat-messages');
    function appendMessage(role, text) {
        var cls = role === 'user' ? 'contextualwp-chat-user' : 'contextualwp-chat-ai';
        var bubble = $('<div>').addClass('contextualwp-chat-bubble').addClass(cls).text(text);
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
                prompt: prompt
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