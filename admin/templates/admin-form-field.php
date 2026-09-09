<?php
    // Exit if accessed directly
    defined('ABSPATH') || exit;

    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.WP.GlobalVariablesOverride.Prohibited -- This file is loaded in function scope; its variables are not globals.

    // Create field object
    $field_object = \ElzoForms\Field\Field::from(!empty($field) ? $field : []);

    // Get field data array
    $field = $field_object->get_admin_field_data();

    $step_index = $field_object->get('step_index', 0);
    $field_index = $field_object->get('index', 0);
    $field_id = $field_object->get_id();
    $field_type = $field_object->get_type();
    $width = \ElzoForms\Utilities\Admin::get_field_header_width($field['width'] ?? null);
    $admin_label = $field_object->get_admin_label();
    $label = $field_object->get_label();
    $placeholder = $field_object->get_placeholder();
    $title = $field_object->get_admin_title();
    $required = $field_object->is_required();
    $read_only = $field_object->is_read_only();
    $tabs = \ElzoForms\Utilities\Admin::get_field_tabs();

    // Convert options to string
    if(isset($field['options']) && is_array($field['options'])){
        $field['options'] = \ElzoForms\Utilities\Admin::options_to_string($field['options']);
    }
?>
<div class="elzo-forms-field" id="elzo-forms-field-<?php echo esc_attr($field_id); ?>" data-id="<?php echo esc_attr($field_id); ?>" data-read-only="<?php echo $read_only ? '1' : '0'; ?>">
    <textarea class="field-initial-value" style="display:none"><?php echo esc_textarea(wp_json_encode($field, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
    <div class="elzo-forms-field-header">
        <div class="elzo-forms-sortable-dragger elzo-forms-field-header-dragger"></div>
        <strong class="elzo-forms-field-header-title">
            <span class="elzo-forms-field-header-title-inner"><?php echo esc_html($field_index . '. ' . \ElzoForms\Utilities\Admin::get_field_types($field_type, 'label') . ($title ? ': ' . $title : '')); ?></span><?php echo $width ? esc_html(' (' . $width . ')') : ''; ?><?php echo !empty($field['logic']) ? esc_html(' [?=]') : ''; ?><?php echo $required ? esc_html(' *') : ''; ?>
        </strong>
        <div class="elzo-forms-field-header-end">
            <button type="button" class="elzo-forms-field-header-button elzo-forms-field-duplicate-button"><i class="elzo-icon elzo-icon-copy"></i></button>
            <button type="button" class="elzo-forms-field-header-button elzo-forms-field-remove-button" data-click-confirmation="<?php echo esc_attr__('Delete?', 'elzo-forms'); ?>"><i class="elzo-icon elzo-icon-close"></i></button>
            <button type="button" class="elzo-forms-field-header-button elzo-forms-field-toggle-button"><i class="elzo-icon elzo-icon-chevron-down elzo-forms-field-toggle-icon"></i></button>
        </div>
        <button type="button" class="elzo-forms-field-header-toggler"></button>
    </div>
    <div class="elzo-forms-field-body" style="display:none">
        <input type="hidden" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][id]" class="field-id-value" value="<?php echo esc_attr($field_id); ?>">
        <input type="hidden" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][index]" class="field-index-value" value="<?php echo esc_attr($field_index); ?>">
        
        <div class="elzo-forms-tabs">
            <div class="elzo-forms-tabs-header">
                <?php $index = 0; foreach($tabs as $tab_key => $tab_label): $index++; ?>
                    <button type="button" class="elzo-forms-tab-button elzo-forms-tab-button-<?php echo esc_attr($tab_key); ?> <?php echo $tab_key == 'logic' && !empty($field['logic']) ? 'has-logic' : ''; ?> <?php echo $index == 1 ? 'active' : ''; ?>" data-tab-target="<?php echo esc_attr($tab_key); ?>"><?php echo esc_html($tab_label); ?></button>
                <?php endforeach; ?>
            </div>
            <div class="elzo-forms-tabs-body">
                <?php $index = 0; foreach($tabs as $tab_key => $tab_label): $index++; ?>
                    <div class="elzo-forms-tab" data-tab="<?php echo esc_attr($tab_key); ?>" <?php echo $index == 1 ? '' : 'style="display:none"'; ?> >
                        <?php include plugin_dir_path(__DIR__) . 'templates/admin-form-field-'.$tab_key.'.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
