jQuery(function($){
    $('#contextualwp-chat-generate').on('click', function(e){
        e.preventDefault();
        var prompt = $('#contextualwp-chat-prompt').val();
        var $btn = $(this);
        var $output = $('#contextualwp-chat-output');
        $output.empty();
        $btn.prop('disabled', true).text('Loading...');
        if (!prompt) {
            $output.text('Please enter a question.');
            $btn.prop('disabled', false).text('Send');
            return;
        }
        $.ajax({
            url: contextualwpChat.endpoint,
            method: 'POST',
            beforeSend: function(xhr){ xhr.setRequestHeader('X-WP-Nonce', contextualwpChat.nonce); },
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                context_id: 'multi', // special value for multi-post context
                prompt: prompt
            })
        }).done(function(res){
            if(res.ai && res.ai.output){
                $output.text(res.ai.output);
            } else {
                $output.text('No response');
            }
        }).fail(function(xhr){
            var msg = 'Error';
            if(xhr.responseJSON && xhr.responseJSON.message){
                msg = xhr.responseJSON.message;
            }
            $output.text(msg);
        }).always(function(){
            $btn.prop('disabled', false).text('Send');
        });
    });
});
