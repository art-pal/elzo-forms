<?php
/**
 * Admin Settings template.
 *
 * Renders the main settings page for Elzo Forms.
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.WP.GlobalVariablesOverride.Prohibited -- This template is loaded in function scope; its variables are not globals.

// Get settings
$form_settings = elzo_forms_get_notification_option_for_editor('elzo_forms_form_settings');
$texts_settings = elzo_forms_get_notification_option_for_editor('elzo_forms_texts_settings');
$style_settings = get_option('elzo_forms_style_settings', array());

// Define Tabs with URLs for navigation
$tabs = [
    'form' => [
        'label' => __('Form', 'elzo-forms'),
        'url' => admin_url('edit.php?post_type=elzo_form&page=elzo-forms-settings&tab=form'),
    ],
    'texts' => [
        'label' => __('Texts', 'elzo-forms'),
        'url' => admin_url('edit.php?post_type=elzo_form&page=elzo-forms-settings&tab=texts'),
    ],
    'styles' => [
        'label' => __('Styles', 'elzo-forms'),
        'url' => admin_url('edit.php?post_type=elzo_form&page=elzo-forms-settings&tab=styles'),
    ],
    'automations' => [
        'label' => __('Automations', 'elzo-forms'),
        'url' => admin_url('edit.php?post_type=elzo_form&page=elzo-forms-settings&tab=automations'),
    ],
    'modules' => [
        'label' => __('Modules', 'elzo-forms'),
        'url' => admin_url('edit.php?post_type=elzo_form&page=elzo-forms-settings&tab=modules'),
    ],
];

// Set Plugin Settings Flag to True
$elzo_plugin_settings = true;

?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($title); ?></h1>
    <?php
        $settings_updated = elzo_forms_get_admin_query_slug('settings-updated');
        $notification_option = 'elzo_forms_' . $current_tab . '_settings';
        $notification_draft = get_transient(elzo_forms_notification_option_draft_key($notification_option));
        if (is_array($notification_draft)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('These notification changes were not applied. Correct the variable errors below and save again; the proposed values remain in the editor.', 'elzo-forms') . '</p><ul>';
            foreach (\ElzoForms\Variables\Notification_Validation::validate($notification_draft) as $error) {
                echo '<li>' . esc_html($error['message']) . '</li>';
            }
            echo '</ul></div>';
        } else {
            settings_errors();
        }
        if ('true' === $settings_updated && !$notification_draft) {
    ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved successfully.', 'elzo-forms'); ?></p>
        </div>
    <?php } ?>
    
    <section class="elzo-forms-settings-tabs elzo-forms-tabs" style="margin-top:1rem">
        <div class="elzo-forms-tabs-header">
            <?php foreach($tabs as $tab_key => $tab_data): ?>
                <a href="<?php echo esc_url($tab_data['url']); ?>" class="elzo-forms-tab-button elzo-forms-tab-button-<?php echo esc_attr($tab_key); ?> <?php echo $current_tab === $tab_key ? 'active' : ''; ?>">
                    <?php echo esc_html($tab_data['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="elzo-forms-tabs-body">
            <div class="elzo-forms-tab" data-tab="<?php echo esc_attr($current_tab); ?>">
                <?php if ($current_tab === 'modules'): ?>
                    <?php include plugin_dir_path(__FILE__) . 'admin-settings-modules.php'; ?>
                <?php elseif ($current_tab === 'automations'): ?>
                    <?php include plugin_dir_path(__FILE__) . 'admin-settings-automations.php'; ?>
                <?php else: ?>
                    <form method="post" action="options.php">
                        <?php
                        // Use tab-specific settings group
                        $settings_group = 'elzo_forms_' . $current_tab . '_settings_group';
                        settings_fields($settings_group);
                        ?>
                        <?php include plugin_dir_path(__FILE__) . 'admin-settings-' . $current_tab . '.php'; ?>
                        <?php submit_button(); ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
