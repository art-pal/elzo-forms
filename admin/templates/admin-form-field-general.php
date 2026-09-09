<?php
    // Exit if accessed directly
    defined('ABSPATH') || exit;

    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included via admin-form-field.php within admin meta-box callback scope; variables here are function-scoped, not globals.

    // Field general settings
?>
<div class="elzo-forms-field-control-group <?php echo $read_only ? 'elzo-forms-field-setting-disabled' : ''; ?>">
    <label for="elzo-forms-field-required-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><input type="checkbox" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][required]" id="elzo-forms-field-required-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-check elzo-forms-field-header-part" data-disable-for-read-only <?php echo checked(!empty($required), true, false); ?> value="Checked" <?php if ($read_only): ?>disabled<?php endif; ?>> <?php esc_html_e('Required', 'elzo-forms'); ?></label>
</div>
<div class="elzo-forms-row elzo-forms-row-2-cols elzo-forms-field-control-group">
    <div class="elzo-forms-column">
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-type-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Type', 'elzo-forms'); ?></label>
            <select name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][type]" class="elzo-forms-field-control elzo-forms-field-header-part elzo-forms-field-type-select" id="elzo-forms-field-type-<?php echo esc_attr($field_id); ?>">
                <?php foreach(\ElzoForms\Utilities\Admin::get_field_types(null, 'label') as $option_value => $option_label): ?>
                    <option value="<?php echo esc_attr($option_value); ?>" <?php selected($field_type, $option_value); ?>><?php echo esc_html($option_label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="elzo-forms-column">
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-label-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Label', 'elzo-forms'); ?></label>
            <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][label]" value="<?php echo esc_attr($label); ?>" id="elzo-forms-field-label-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control elzo-forms-field-header-part">
        </div>
    </div>
</div>
<div class="elzo-forms-field-specific-settings-wrapper elzo-forms-field-control-group elzo-forms-field-sep-top" data-setting-category="general">
    <?php $field_object->render_field_settings('general'); ?>
</div>
