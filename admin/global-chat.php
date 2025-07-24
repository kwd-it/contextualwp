<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ContextWP_Global_Chat {
    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_global_chat_assets' ] );
        add_action( 'admin_footer', [ $this, 'output_chat_modal_markup' ] );
    }

    public function enqueue_global_chat_assets() {
        $prompt_templates = apply_filters('contextwp_prompt_templates', [
            [ 'label' => 'Summarize this content', 'prompt' => 'Summarize the content in a few sentences.' ],
            [ 'label' => 'Improve writing', 'prompt' => 'Rewrite this content to improve clarity and style.' ],
            [ 'label' => 'Suggest SEO keywords', 'prompt' => 'Suggest relevant SEO keywords for this content.' ],
        ]);
        wp_enqueue_style(
            'contextwp-global-chat',
            CONTEXTWP_URL . 'admin/assets/css/global-chat.css',
            [],
            CONTEXTWP_VERSION
        );
        wp_enqueue_script(
            'contextwp-global-chat',
            CONTEXTWP_URL . 'admin/assets/js/global-chat.js',
            [ 'jquery' ],
            CONTEXTWP_VERSION,
            true
        );
        wp_localize_script(
            'contextwp-global-chat',
            'contextwpGlobalChat',
            [
                'endpoint' => rest_url( 'contextwp/v1/generate_context' ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'screen'   => get_current_screen() ? get_current_screen()->id : '',
                'postId'   => isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0,
                'postType' => isset( $_GET['post'] ) ? get_post_type( absint( $_GET['post'] ) ) : '',
                'promptTemplates' => $prompt_templates,
            ]
        );
    }

    public function output_chat_modal_markup() {
        ?>
        <div id="contextwp-floating-chat" style="display:none">
            <div id="contextwp-floating-chat-icon" title="Open ContextWP Chat"></div>
            <div id="contextwp-floating-chat-modal" style="display:none">
                <div id="contextwp-floating-chat-modal-header">
                    <span>ContextWP Chat</span>
                    <button id="contextwp-floating-chat-modal-close" type="button">&times;</button>
                </div>
                <div id="contextwp-floating-chat-modal-body">
                    <div id="contextwp-floating-chat-messages" style="margin-bottom:12px;"></div>
                    <textarea id="contextwp-floating-chat-prompt" placeholder="Ask anything about your website..." rows="4"></textarea>
                    <button id="contextwp-floating-chat-send" class="button button-primary">Send</button>
                </div>
            </div>
        </div>
        <?php
    }
}

class ContextWP_ACF_AskAI {
    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_acf_askai_script' ] );
    }
    public function enqueue_acf_askai_script( $hook ) {
        // Only load on post.php and post-new.php
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        wp_enqueue_style(
            'contextwp-acf-askai',
            CONTEXTWP_URL . 'admin/assets/css/acf-askai.css',
            [],
            CONTEXTWP_VERSION
        );
        wp_enqueue_script(
            'contextwp-acf-askai',
            CONTEXTWP_URL . 'admin/assets/js/acf-askai.js',
            [ 'jquery' ],
            CONTEXTWP_VERSION,
            true
        );
        wp_localize_script(
            'contextwp-acf-askai',
            'contextwpACFAskAI',
            [
                'endpoint' => rest_url( 'contextwp/v1/generate_context' ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'postId'   => isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0,
                'postType' => isset( $_GET['post'] ) ? get_post_type( absint( $_GET['post'] ) ) : '',
            ]
        );
    }
}
new ContextWP_ACF_AskAI();

new ContextWP_Global_Chat(); 