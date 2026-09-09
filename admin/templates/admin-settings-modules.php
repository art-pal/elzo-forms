<?php
/**
 * Unified Modules Template
 * Used for both global plugin settings and per-form module configuration
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.WP.GlobalVariablesOverride.Prohibited -- This template is loaded in function scope; its variables are not globals.

// Determine context
$is_plugin_settings = !empty($elzo_plugin_settings);
$is_form_settings = !$is_plugin_settings;

// Get manager and modules
$manager = elzo_forms_modules_manager();
$modules = $manager ? $manager->all() : [];

if ($is_plugin_settings) {
    // Global settings context
    $global_enabled = get_option('elzo_forms_modules_enabled', []);

    // Handle save
    if (
        isset($_POST['elzo_forms_modules_save'])
        && current_user_can('manage_options')
        && check_admin_referer('elzo_forms_modules_settings', 'elzo_forms_modules_nonce')
    ) {
        $enabled = [];
        if (isset($_POST['elzo_forms_modules_enabled']) && is_array($_POST['elzo_forms_modules_enabled'])) {
            $enabled = (array) wp_unslash($_POST['elzo_forms_modules_enabled']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are cast to bool via !empty() in the loop below; nonce verified via check_admin_referer() above.
        }
        $sanitized_enabled = [];

        foreach ($modules as $id => $module) {
            $module_id = sanitize_key($id);
            $sanitized_enabled[$module_id] = !empty($enabled[$id]);

            // Save module settings if submitted
            $module_settings_key = 'elzo_forms_module_' . $id . '_settings';
            if (isset($_POST[$module_settings_key]) && is_array($_POST[$module_settings_key])) {
                $raw_settings = (array) wp_unslash($_POST[$module_settings_key]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized and schema-whitelisted by Module Manager below; nonce verified via check_admin_referer() above.
                $settings = $manager->sanitize_settings($raw_settings, $module->get_settings_schema());

                update_option($manager->get_settings_option_name($module_id), $settings);
            }
        }

        update_option('elzo_forms_modules_enabled', $sanitized_enabled);
        $global_enabled = $sanitized_enabled;

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Modules settings saved.', 'elzo-forms') . '</p></div>';
    }
} else {
    // Per-form settings context
    $global_enabled = get_option('elzo_forms_modules_enabled', []);
    $form_modules = isset($form_data['modules']) && is_array($form_data['modules']) ? $form_data['modules'] : [];
}

?>

<?php if ($is_plugin_settings): ?>
    <form method="post" action="">
        <?php wp_nonce_field('elzo_forms_modules_settings', 'elzo_forms_modules_nonce'); ?>
<?php endif; ?>

<?php if (empty($modules)): ?>
    <p><?php esc_html_e('No modules are registered.', 'elzo-forms'); ?></p>
<?php else: ?>
    <div class="elzo-forms-admin-search-section">
        <div class="elzo-forms-admin-search">
            <label for="elzo-forms-admin-settings-modules-search-input" class="elzo-forms-admin-search-label-icon"><i class="elzo-icon elzo-icon-search"></i></label>
            <input type="search" class="elzo-forms-admin-search-input" id="elzo-forms-admin-settings-modules-search-input" placeholder="<?php echo esc_attr__('Search modules', 'elzo-forms'); ?>">
            <button type="button" class="elzo-forms-admin-search-clear" style="display:none"><i class="elzo-icon elzo-icon-close"></i></button>
        </div>
        <div class="elzo-forms-admin-search-nothing-found" style="display:none">
            <p><?php esc_html_e('No modules found', 'elzo-forms'); ?></p>
        </div>
        <div class="elzo-forms-modules-grid elzo-forms-admin-search-list">
            <?php foreach ($modules as $id => $module):
                $schema = $module->get_settings_schema();
                $is_compatible = $module->is_compatible();

                if ($is_plugin_settings) {
                    // Global settings
                    $is_enabled = !empty($global_enabled[$id]);
                    $settings = get_option($manager->get_settings_option_name($id), []);
                    $settings = is_array($settings) ? $manager->sanitize_settings($settings, $schema) : [];
                    $show_settings = !empty($schema);
                    $config_warnings = $module->get_configuration_warnings($settings, $is_enabled, true);
                } else {
                    // Per-form settings
                    $m = isset($form_modules[$id]) && is_array($form_modules[$id]) ? $form_modules[$id] : [];
                    $status = isset($m['status']) && in_array($m['status'], ['inherit','enabled','disabled'], true) ? $m['status'] : 'inherit';
                    $global_on = !empty($global_enabled[$id]);
                    $effective_enabled = ($status === 'enabled') || ($status === 'inherit' && $global_on);
                    $per_form_settings = isset($m['settings']) && is_array($m['settings']) ? $manager->sanitize_settings($m['settings'], $schema) : [];
                    $global_settings = get_option($manager->get_settings_option_name($id), []);
                    $global_settings = is_array($global_settings) ? $manager->sanitize_settings($global_settings, $schema) : [];
                    $show_settings = !empty($schema);
                    $form_id_for_settings = isset($form_object) ? $form_object->get_id() : 0;

                    // Compute effective settings for warnings
                    $effective_settings = $manager && $form_id_for_settings > 0
                        ? $manager->settings_for_form($id, $form_id_for_settings)
                        : wp_parse_args($per_form_settings, $global_settings);
                    $config_warnings = $module->get_configuration_warnings($effective_settings, $effective_enabled, false);
                }

                // Include the module card template
                include plugin_dir_path(__FILE__) . 'admin-module.php';
            ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($is_plugin_settings): ?>
        <input type="hidden" name="elzo_forms_modules_save" value="1">
        <?php submit_button(); ?>
    </form>
<?php endif; ?>
