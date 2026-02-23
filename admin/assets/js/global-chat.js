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

    // Use current post/page on edit screens; only use multi when explicitly selected
    var serverContextId = (typeof contextualwpGlobalChat !== 'undefined' && contextualwpGlobalChat.contextId && String(contextualwpGlobalChat.contextId).trim() !== '') ? String(contextualwpGlobalChat.contextId).trim() : 'multi';
    var useMultiContext = (serverContextId === 'multi');

    // Fallback: if PHP sent multi but we're on a post/page edit screen, derive context from URL and body
    if (serverContextId === 'multi') {
        var postMatch = typeof window.location !== 'undefined' && window.location.search && window.location.search.match(/[?&]post=(\d+)/);
        var postIdFromUrl = postMatch ? postMatch[1] : '';
        var postTypeFromUrl = '';
        if (typeof window.location !== 'undefined' && window.location.search) {
            var ptMatch = window.location.search.match(/[?&]post_type=([a-z0-9_-]+)/i);
            if (ptMatch) postTypeFromUrl = ptMatch[1];
        }
        var postTypeFromBody = '';
        if (document.body && document.body.className) {
            var classes = document.body.className.split(/\s+/);
            for (var c = 0; c < classes.length; c++) {
                if (classes[c].indexOf('post-type-') === 0) {
                    postTypeFromBody = classes[c].replace('post-type-', '');
                    break;
                }
            }
        }
        var postType = postTypeFromBody || postTypeFromUrl;
        if (postType && (postIdFromUrl !== '' || (typeof contextualwpGlobalChat !== 'undefined' && contextualwpGlobalChat.postId !== undefined))) {
            var pid = postIdFromUrl || (contextualwpGlobalChat && String(contextualwpGlobalChat.postId));
            if (pid !== undefined && pid !== '') {
                serverContextId = postType + '-' + pid;
                useMultiContext = false;
            }
        }
        if (serverContextId === 'multi' && postType && (typeof contextualwpGlobalChat !== 'undefined' && (contextualwpGlobalChat.postId === 0 || contextualwpGlobalChat.postId === '0'))) {
            serverContextId = postType + '-0';
            useMultiContext = false;
        }
    }

    function getEffectiveContextId() {
        return useMultiContext ? 'multi' : serverContextId;
    }

    // Context switcher: only show on edit screens so user can explicitly choose site-wide
    var $contextRow = $('<div class="contextualwp-chat-context-row" style="margin-bottom:8px;font-size:12px;color:#666;"></div>');
    $('#contextualwp-floating-chat-prompt').before($contextRow);
    function updateContextSwitcher() {
        if (serverContextId === 'multi') {
            $contextRow.html('Context: Site-wide').hide();
            return;
        }
        $contextRow.show();
        if (useMultiContext) {
            $contextRow.html('Context: Site-wide. <a href="#" class="contextualwp-chat-context-link">Use current post</a>');
        } else {
            $contextRow.html('Context: Current post. <a href="#" class="contextualwp-chat-context-link">Use site-wide</a>');
        }
    }
    $(document).on('click', '.contextualwp-chat-context-link', function(e){
        e.preventDefault();
        if (serverContextId === 'multi') return;
        useMultiContext = !useMultiContext;
        updateContextSwitcher();
    });
    updateContextSwitcher();

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
                context_id: getEffectiveContextId(),
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
