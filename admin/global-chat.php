<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ContextualWP_Global_Chat {
    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_global_chat_assets' ] );
        add_action( 'admin_footer', [ $this, 'output_chat_modal_markup' ] );
    }

    public function enqueue_global_chat_assets() {
        $prompt_templates = apply_filters('contextualwp_prompt_templates', [
            [ 'label' => 'Summarize this content', 'prompt' => 'Summarize the content in a few sentences.' ],
            [ 'label' => 'Improve writing', 'prompt' => 'Rewrite this content to improve clarity and style.' ],
            [ 'label' => 'Suggest SEO keywords', 'prompt' => 'Suggest relevant SEO keywords for this content.' ],
        ]);
        wp_enqueue_style(
            'contextualwp-global-chat',
            CONTEXTUALWP_URL . 'admin/assets/css/global-chat.css',
            [],
            CONTEXTUALWP_VERSION
        );
        wp_enqueue_script(
            'marked',
            'https://cdn.jsdelivr.net/npm/marked@12.0.0/lib/marked.umd.js',
            [],
            '12.0.0',
            true
        );
        wp_enqueue_script(
            'contextualwp-global-chat',
            CONTEXTUALWP_URL . 'admin/assets/js/global-chat.js',
            [ 'jquery', 'marked' ],
            CONTEXTUALWP_VERSION,
            true
        );
        $screen = get_current_screen();
        $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
        $post_type = $post_id ? get_post_type( $post_id ) : '';
        // When editing a single post, ensure we have a type (e.g. get_post_type can be empty for some CPTs or revisions)
        if ( $post_id && empty( $post_type ) ) {
            $post_obj = get_post( $post_id );
            if ( $post_obj && ! empty( $post_obj->post_type ) ) {
                $post_type = $post_obj->post_type;
            }
        }
        if ( empty( $post_type ) && $screen && ! empty( $screen->post_type ) ) {
            $post_type = $screen->post_type;
        }
        if ( empty( $post_type ) && isset( $_GET['post_type'] ) ) {
            $post_type = sanitize_key( $_GET['post_type'] );
        }
        // Fallback: global $typenow (set by WordPress on edit.php/post-new.php for CPT)
        if ( empty( $post_type ) && ! empty( $GLOBALS['typenow'] ) ) {
            $post_type = sanitize_key( $GLOBALS['typenow'] );
        }
        // Fallback: infer from screen id (e.g. "edit-land_planning" or "land_planning")
        if ( empty( $post_type ) && $screen && ! empty( $screen->id ) ) {
            $sid = $screen->id;
            if ( strpos( $sid, 'edit-' ) === 0 ) {
                $candidate = substr( $sid, 5 );
                if ( get_post_type_object( $candidate ) ) {
                    $post_type = $candidate;
                }
            } elseif ( get_post_type_object( $sid ) ) {
                $post_type = $sid;
            }
        }
        $post_type = $post_type ? sanitize_key( $post_type ) : '';
        // On edit screens use current post/page as context_id; elsewhere use multi
        $context_id = $post_type !== '' ? $post_type . '-' . $post_id : 'multi';

        wp_localize_script(
            'contextualwp-global-chat',
            'contextualwpGlobalChat',
            [
                'endpoint' => rest_url( 'contextualwp/v1/generate_context' ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'screen'   => $screen ? $screen->id : '',
                'postId'   => $post_id,
                'postType' => $post_type,
                'contextId' => $context_id,
                'promptTemplates' => $prompt_templates,
            ]
        );
    }

    public function output_chat_modal_markup() {
        ?>
        <div id="contextualwp-floating-chat" style="display:none">
            <div id="contextualwp-floating-chat-icon" title="Open ContextualWP Chat"></div>
            <div id="contextualwp-floating-chat-modal" style="display:none">
                <div id="contextualwp-floating-chat-modal-header">
                    <span>ContextualWP Chat</span>
                    <div id="contextualwp-floating-chat-modal-actions">
                        <button id="contextualwp-floating-chat-modal-expand" type="button" title="Expand chat" aria-label="Expand chat">&#x2922;</button>
                        <button id="contextualwp-floating-chat-modal-close" type="button" title="Close chat" aria-label="Close chat">&times;</button>
                    </div>
                </div>
                <div id="contextualwp-floating-chat-modal-body">
                    <div id="contextualwp-floating-chat-messages" style="margin-bottom:12px;"></div>
                    <textarea id="contextualwp-floating-chat-prompt" placeholder="Ask anything about your website..." rows="4"></textarea>
                    <button id="contextualwp-floating-chat-send" class="button button-primary">Send</button>
                </div>
            </div>
        </div>
        <?php
    }
}

class ContextualWP_ACF_AskAI {
    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_acf_askai_script' ], 20 );
    }

    /**
     * Check if ACF is active (acf-input will be available).
     */
    private function is_acf_active() {
        return function_exists( 'acf_get_setting' ) || class_exists( 'ACF' );
    }

    public function enqueue_acf_askai_script( $hook ) {
        // Only load on post.php and post-new.php
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }
        if ( ! $this->is_acf_active() ) {
            return;
        }

        $post_id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
        $post_type = $post_id ? get_post_type( $post_id ) : '';
        if ( empty( $post_type ) ) {
            $screen = get_current_screen();
            $post_type = ( $screen && isset( $screen->post_type ) ) ? $screen->post_type : ( isset( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : 'post' );
        }

        // Use current post/page as context on edit screens (including new post as post-0)
        $context_id = $post_type . '-' . $post_id;

        $debug = defined( 'CONTEXTUALWP_ACF_ASKAI_DEBUG' ) && CONTEXTUALWP_ACF_ASKAI_DEBUG;

        wp_enqueue_style(
            'contextualwp-acf-askai',
            CONTEXTUALWP_URL . 'admin/assets/css/acf-askai.css',
            [],
            CONTEXTUALWP_VERSION
        );
        wp_enqueue_script(
            'contextualwp-acf-askai',
            CONTEXTUALWP_URL . 'admin/assets/js/acf-askai.js',
            [ 'jquery', 'acf-input' ],
            CONTEXTUALWP_VERSION,
            true
        );
        wp_localize_script(
            'contextualwp-acf-askai',
            'contextualwpACFAskAI',
            [
                'endpoint'   => rest_url( 'contextualwp/v1/generate_context' ),
                'nonce'      => wp_create_nonce( 'wp_rest' ),
                'postId'     => $post_id,
                'postType'   => $post_type,
                'contextId'  => $context_id,
                'debug'      => $debug,
                'supportedTypes' => apply_filters( 'contextualwp_acf_askai_field_types', [ 'text', 'textarea', 'wysiwyg', 'number', 'email', 'url', 'select', 'radio', 'true_false', 'relationship', 'post_object', 'taxonomy', 'image', 'file', 'google_map' ] ),
            ]
        );
    }
}
new ContextualWP_ACF_AskAI();

new ContextualWP_Global_Chat();