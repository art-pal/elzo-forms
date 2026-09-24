<?php
    // Exit if accessed directly
    defined('ABSPATH') || exit;

    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.WP.GlobalVariablesOverride.Prohibited -- This file is loaded in function scope; its variables are not globals.

    $tabs = [
        'fields' => __('Fields', 'elzo-forms'),
        'settings' => __('Settings', 'elzo-forms'),
        'texts' => __('Texts', 'elzo-forms'),
        'automations' => __('Automations', 'elzo-forms'),
        'modules' => __('Modules', 'elzo-forms'),
    ];

    // Create form object
    $notification_draft = get_transient(elzo_forms_notification_form_draft_key((int) $post->ID));
    if (is_array($notification_draft) && isset($notification_draft['data']) && current_user_can('edit_post', $post->ID)) {
        // Draft data is editor-only; the live form continues to use its saved JSON.
        $post = clone $post;
        $post->post_content = wp_json_encode($notification_draft['data']);
    }
    $form_object = new \ElzoForms\Form\Form($post);
    $steps = $form_object->get_steps();

    // Ensure at least one step exists
    if (empty($steps)) {
        $steps = [['label' => '', 'fields' => []]];
    }

    // Reopen a specific tab after a redirect, e.g. to review automation errors.
    $active_tab = isset($_GET['elzo-forms-tab']) ? sanitize_key(wp_unslash($_GET['elzo-forms-tab'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only UI state.
    if (!isset($tabs[$active_tab])) {
        $active_tab = array_key_first($tabs);
    }

    $steps_total = count($steps);
    $form_settings = $form_object->get_form_settings();
    $texts_settings = $form_object->get_form_texts();
?>
<div class="elzo-forms-form-meta-box-content">
    <?php wp_nonce_field('elzo_forms_save_meta_box', 'elzo_forms_meta_box_nonce'); ?>
    <div class="elzo-forms-tabs elzo-forms-form-meta-box-content-inner" id="elzo-forms-tabs">
        <div class="elzo-forms-tabs-header">
            <?php foreach($tabs as $tab_key => $tab_label): ?>
                <button type="button" class="elzo-forms-tab-button elzo-forms-tab-button-<?php echo esc_attr($tab_key); ?> <?php echo $tab_key === $active_tab ? 'active' : ''; ?>" data-tab-target="<?php echo esc_attr($tab_key); ?>"><?php echo esc_html($tab_label); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="elzo-forms-tabs-body">
            <?php foreach($tabs as $tab_key => $tab_label): ?>
                <div class="elzo-forms-tab" data-tab="<?php echo esc_attr($tab_key); ?>" <?php echo $tab_key === $active_tab ? '' : 'style="display:none"'; ?> >
                    <?php
                        if ($tab_key === 'modules') {
                            include plugin_dir_path(__DIR__) . 'templates/admin-form-modules.php';
                        } else {
                            include plugin_dir_path(__DIR__) . 'templates/admin-form-'.esc_attr($tab_key).'.php';
                        }
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
