jQuery(function($){
    // Insert and show the floating icon
    var $icon = $('<div id="contextwp-floating-chat-icon" title="Open ContextWP Chat"></div>');
    if ($('#contextwp-floating-chat-icon').length === 0) {
        $('body').append($icon);
    }
    var $modal = $('#contextwp-floating-chat-modal');
    var $container = $('#contextwp-floating-chat');
    if ($container.length === 0) {
        // fallback: create container if not present
        $container = $('<div id="contextwp-floating-chat"></div>').appendTo('body');
    }
    $container.show();
    $icon = $('#contextwp-floating-chat-icon');
    $modal = $('#contextwp-floating-chat-modal');

    // Open modal
    $icon.on('click', function(){
        $modal.attr({'role':'dialog','aria-modal':'true','aria-label':'ContextWP Chat'});
        $modal.show();
        setTimeout(function(){ $('#contextwp-floating-chat-prompt').focus(); }, 100);
    });
    // Close modal
    $('#contextwp-floating-chat-modal-close').attr('aria-label','Close chat').on('click', function(){
        $modal.hide();
        $icon.focus();
    });
    // ESC closes modal
    $(document).on('keydown', function(e){
        if (e.key === 'Escape') $modal.hide();
    });

    var $messages = $('#contextwp-floating-chat-messages');
    function appendMessage(role, text) {
        var cls = role === 'user' ? 'contextwp-chat-user' : 'contextwp-chat-ai';
        var bubble = $('<div>').addClass('contextwp-chat-bubble').addClass(cls).text(text);
        $messages.append(bubble);
        $messages.scrollTop($messages[0].scrollHeight);
    }
    $('#contextwp-floating-chat-send').attr('aria-label','Send message').on('click', function(){
        var prompt = $('#contextwp-floating-chat-prompt').val();
        if (!prompt) return;
        appendMessage('user', prompt);
        $('#contextwp-floating-chat-prompt').val('');
        var $btn = $(this);
        $btn.prop('disabled', true).text('Sending...');
        $.ajax({
            url: contextwpGlobalChat.endpoint,
            method: 'POST',
            beforeSend: function(xhr){ xhr.setRequestHeader('X-WP-Nonce', contextwpGlobalChat.nonce); },
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