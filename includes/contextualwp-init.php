<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load helper classes
require_once CONTEXTUALWP_DIR . 'includes/helpers/utilities.php';
require_once CONTEXTUALWP_DIR . 'includes/helpers/providers.php';
require_once CONTEXTUALWP_DIR . 'includes/helpers/smart-model-selector.php';

// Autoload endpoint classes
require_once CONTEXTUALWP_DIR . 'includes/endpoints/list-contexts.php';
require_once CONTEXTUALWP_DIR . 'includes/endpoints/get-context.php';
require_once CONTEXTUALWP_DIR . 'includes/endpoints/manifest.php';
require_once CONTEXTUALWP_DIR . 'includes/endpoints/generate-context.php';
require_once CONTEXTUALWP_DIR . 'includes/endpoints/schema.php';
require_once CONTEXTUALWP_DIR . 'includes/endpoints/acf-schema.php';
require_once CONTEXTUALWP_DIR . 'includes/endpoints/site-diagnostics.php';

// Load admin settings only in admin
if ( is_admin() ) {
    require_once CONTEXTUALWP_DIR . 'admin/settings.php';
    require_once CONTEXTUALWP_DIR . 'admin/global-chat.php';
}

// Register REST API routes
add_action( 'rest_api_init', function () {
    ( new ContextualWP\Endpoints\List_Contexts )->register_route();
    ( new ContextualWP\Endpoints\Get_Context )->register_route();
    ( new ContextualWP\Endpoints\Manifest )->register_route();
    ( new ContextualWP\Endpoints\Generate_Context )->register_route();
    ( new ContextualWP\Endpoints\Schema )->register_route();
    ( new ContextualWP\Endpoints\ACF_Schema )->register_route();
    ( new ContextualWP\Endpoints\Site_Diagnostics )->register_route();
});


