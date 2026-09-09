<?php
/**
 * Elzo Forms
 *
 * @package       ElzoForms
 * @author        Elzo Forms
 * @license       GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Elzo Forms
 * Plugin URI:        https://elzoforms.com
 * Description:       A WordPress plugin for creating forms.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Author:            Elzo Forms
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       elzo-forms
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

if (!defined('ELZO_FORMS_VERSION')) {
    define('ELZO_FORMS_VERSION', '1.0.0');
}

/**
 * Stop loading the free runtime when Elzo Forms PRO is active.
 */
function elzo_forms_maybe_disable_other_plugin(): bool {
    $other_slug = 'elzo-forms-pro/elzo-forms.php';
    $return_after = true;
    if (function_exists('is_plugin_active') && is_plugin_active($other_slug)) {
        add_action('admin_notices', function () {
            $message = __('Elzo Forms is active, so Elzo Forms (free) is not loaded. You can deactivate Elzo Forms (free).', 'elzo-forms');
            $notice_class = 'warning';

            echo '<div class="notice notice-' . esc_attr($notice_class) . '"><p>' . esc_html($message) . '</p></div>';
        });
        return $return_after;
    }
    return false;
}

/**
 * Disable conflicting plugin based on the current variant.
 */
if (elzo_forms_maybe_disable_other_plugin()) {
    return;
}

/**
 * Initialize Modules Manager
 */
add_action('init', function() {
    $manager = new \ElzoForms\Module\Manager();

    // Register core modules
    $manager->register(new \ElzoForms\Module\Modules\Akismet\Akismet_Module());
    $manager->register(new \ElzoForms\Module\Modules\Recaptcha\Recaptcha_Module());

    // Allow core and third parties to register modules
    do_action('elzo_forms/modules/register', $manager);

    // Boot all modules (global context)
    $manager->boot_all();

    // Store manager instance for later retrieval
    $GLOBALS['elzo_forms_modules_manager'] = $manager;
}, 1);

/**
 * Helper to get modules manager
 */
function elzo_forms_modules_manager(): ?\ElzoForms\Module\Manager {
    return $GLOBALS['elzo_forms_modules_manager'] ?? null;
}

/**
 * Get form HTML by ID.
 *
 * @param int   $form_id Form ID
 * @param array $options Optional. Array of options to customize form output.
 *                       - max_width: Maximum width (e.g., '600px' or 600)
 *                       - form_align: 'left', 'center', or 'right'
 *                       - text_align: 'left', 'center', or 'right'
 * @return string Form HTML output
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Intentional public API following WordPress get_the_*/the_* naming convention.
function get_elzo_form($form_id, $options = []) {
    $atts = array_merge(['id' => intval($form_id), 'echo' => false], $options);
    return \ElzoForms\Form\Form::render_by_id($atts);
}

/**
 * Echo form HTML by ID.
 *
 * @param int   $form_id Form ID
 * @param array $options Optional. Array of options to customize form output.
 *                       - max_width: Maximum width (e.g., '600px' or 600)
 *                       - form_align: 'left', 'center', or 'right'
 *                       - text_align: 'left', 'center', or 'right'
 * @return void
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Intentional public API following WordPress get_the_*/the_* naming convention.
function the_elzo_form($form_id, $options = []) {
    $atts = array_merge(['id' => intval($form_id), 'echo' => true], $options);
    \ElzoForms\Form\Form::render_by_id($atts);
}

/**
 * PSR-4 autoloader for ElzoForms namespaces
 */
spl_autoload_register(function ($class) {

    // Handle ElzoForms namespace
    if (strpos($class, 'ElzoForms\\') === 0) {
        $relative = str_replace('ElzoForms\\', '', $class);
        $relative_path = str_replace('\\', '/', $relative);
        $file = plugin_dir_path(__FILE__) . 'src/' . $relative_path . '.php';
        if (is_readable($file)) {
            require_once $file;
        }
    }
});

/**
 * Get the plugin version.
 *
 * Returns the runtime plugin version defined during bootstrap.
 *
 * @return string The plugin version
 */
function elzo_forms_version() {
    return ELZO_FORMS_VERSION;
}

/**
 * Main instance of Elzo Forms.
 *
 * Returns the main instance of ElzoForms to prevent the need to use globals.
 *
 * @return \ElzoForms\Core\ElzoForms
 */
function elzo_forms() {
    return \ElzoForms\Core\ElzoForms::instance();
}

/**
 * Plugin activation hook.
 */
register_activation_hook(__FILE__, function () {
    require_once plugin_dir_path(__FILE__) . 'src/Core/ElzoForms.php';
    \ElzoForms\Core\ElzoForms::activate();
});

/**
 * Plugin deactivation hook.
 */
register_deactivation_hook(__FILE__, function () {
    if (function_exists('wp_clear_scheduled_hook')) {
        wp_clear_scheduled_hook('elzo_forms_cleanup_unattached_uploads');
    }
});

// Initialize the plugin.
elzo_forms();
