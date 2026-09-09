<?php
/**
 * Frontend functionality.
 *
 * Handles frontend scripts, styles, and form display logic.
 */

defined('ABSPATH') || exit;

// Register the plugin's scripts and styles
function elzo_forms_enqueue_scripts() {
    $dirname = __DIR__;
    $plugin_path = plugin_dir_path($dirname);
    $plugin_url = plugin_dir_url($dirname);

    // Get the style version timestamp from options
    $style_version = get_option('elzo_forms_style_version');

    // Build the URL to the uploaded CSS file if version exists
    if ($style_version) {
        $upload_dir = wp_upload_dir();
        $css_file_url = $upload_dir['baseurl'] . '/elzo-forms/assets/elzo-forms-' . $style_version . '.min.css';
        wp_enqueue_style('elzo-forms-style', $css_file_url, array(), $style_version, 'all');
    } else {
        // Fallback to plugin CSS if no version is set
        if(file_exists($plugin_path.'assets/css/elzo-forms.min.css')){
            wp_enqueue_style('elzo-forms-style', $plugin_url . 'assets/css/elzo-forms.min.css', array(), ELZO_FORMS_VERSION, 'all');
        } else {
            wp_enqueue_style('elzo-forms-style', $plugin_url . 'assets/css/elzo-forms.css', array(), ELZO_FORMS_VERSION, 'all');
        }
    }

    // Enqueue the plugin's JS scripts
    wp_enqueue_script('elzo-forms-script', $plugin_url . 'assets/js/elzo-forms.js', array('wp-hooks'), ELZO_FORMS_VERSION, true);

    // Page context for Page/URL visibility conditions. Non-singular views have no
    // queried post, which is a valid context (page ID 0), not a missing one.
    $current_page_id = (int) get_queried_object_id();

    // Pass nonce to JavaScript for AJAX security
    $frontend_script_data = array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'uploadNonce' => wp_create_nonce('elzo_forms_upload_file'),
        'texts' => \ElzoForms\Utilities\Helpers::get_frontend_texts(),
        'logicOperators' => \ElzoForms\Utilities\Conditional_Logic::get_supported_operators(),
        'logicContext' => array(
            'isLoggedIn' => is_user_logged_in(),
            'userRoles'  => is_user_logged_in() ? array_values((array) wp_get_current_user()->roles) : array(),
            'userId'     => is_user_logged_in() ? get_current_user_id() : 0,
            'serverNowUtc' => time(),
            'currentPageId' => $current_page_id,
            'currentPostType' => $current_page_id ? (string) get_post_type($current_page_id) : '',
        ),
    );

    wp_localize_script('elzo-forms-script', 'ElzoFormsAjax', $frontend_script_data);
}
add_action('wp_enqueue_scripts', 'elzo_forms_enqueue_scripts');

// Wrapper function for column width class helper
function elzo_forms_column_width_class($width = null) {
    return \ElzoForms\Utilities\Helpers::get_column_width_class($width);
}
