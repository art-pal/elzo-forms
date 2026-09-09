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
    $form_object = new \ElzoForms\Form\Form($post);
    $steps = $form_object->get_steps();

    // Ensure at least one step exists
    if (empty($steps)) {
        $steps = [['label' => '', 'fields' => []]];
    }

    $steps_total = count($steps);
    $form_settings = $form_object->get_form_settings();
    $texts_settings = $form_object->get_form_texts();
?>
<div class="elzo-forms-form-meta-box-content">
    <?php wp_nonce_field('elzo_forms_save_meta_box', 'elzo_forms_meta_box_nonce'); ?>
    <div class="elzo-forms-tabs elzo-forms-form-meta-box-content-inner" id="elzo-forms-tabs">
        <div class="elzo-forms-tabs-header">
            <?php $index = 0; foreach($tabs as $tab_key => $tab_label): $index++; ?>
                <button type="button" class="elzo-forms-tab-button elzo-forms-tab-button-<?php echo esc_attr($tab_key); ?> <?php echo $index == 1 ? 'active' : ''; ?>" data-tab-target="<?php echo esc_attr($tab_key); ?>"><?php echo esc_html($tab_label); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="elzo-forms-tabs-body">
            <?php $index = 0; foreach($tabs as $tab_key => $tab_label): $index++; ?>
                <div class="elzo-forms-tab" data-tab="<?php echo esc_attr($tab_key); ?>" <?php echo $index == 1 ? '' : 'style="display:none"'; ?> >
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
