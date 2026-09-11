<?php
/**
 * Frontend functionality.
 *
 * Handles frontend scripts, styles, and form display logic.
 */

defined('ABSPATH') || exit;

// Enqueue the complete interactive runtime on the frontend. Shortcodes can be
// rendered from widgets and templates, so preserving the existing global hook
// is safer than attempting to detect forms only in the main post content.
function elzo_forms_enqueue_scripts() {
    \ElzoForms\Assets\Frontend_Assets::enqueue();
}
add_action('wp_enqueue_scripts', 'elzo_forms_enqueue_scripts');

// Wrapper function for column width class helper
function elzo_forms_column_width_class($width = null) {
    return \ElzoForms\Utilities\Helpers::get_column_width_class($width);
}
