<?php
    // Exit if accessed directly
    defined('ABSPATH') || exit;

    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included via admin-form-field.php within admin meta-box callback scope; variables here are function-scoped, not globals.

    $admin_label = !empty($field['admin_label']) ? $field['admin_label'] : '';
    $field_key = !empty($field['field_key']) ? $field['field_key'] : '';
    $primary_field = !empty($field['primary_field']);
    $submission_table_field = !empty($field['submission_table_field']);
?>
<div class="elzo-forms-row elzo-forms-row-2-cols elzo-forms-field-control-group">
    <div class="elzo-forms-column">
        <div class="elzo-forms-field-control-group <?php echo $read_only ? 'elzo-forms-field-setting-disabled' : ''; ?>">
            <label for="elzo-forms-field-primary-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><input type="checkbox" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][primary_field]" value="Checked" id="elzo-forms-field-primary-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-check elzo-forms-field-primary-checkbox elzo-forms-field-header-part" data-disable-for-read-only <?php echo checked($primary_field, true, false); ?> <?php if ($read_only): ?>disabled<?php endif; ?>> <?php esc_html_e('Primary Field', 'elzo-forms'); ?></label>
            <div class="elzo-forms-field-control-guideline"><?php esc_html_e('Use this field to identify the submission', 'elzo-forms'); ?></div>
        </div>
    </div>
    <div class="elzo-forms-column">
        <div class="elzo-forms-field-control-group <?php echo $read_only ? 'elzo-forms-field-setting-disabled' : ''; ?>">
            <label for="elzo-forms-field-submission-table-field-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><input type="checkbox" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][submission_table_field]" value="Checked" id="elzo-forms-field-submission-table-field-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-check elzo-forms-field-submission-table-checkbox elzo-forms-field-header-part" data-disable-for-read-only <?php echo checked($submission_table_field, true, false); ?> <?php if ($read_only): ?>disabled<?php endif; ?>> <?php esc_html_e('Submission Table Field', 'elzo-forms'); ?></label>
            <div class="elzo-forms-field-control-guideline"><?php esc_html_e('Make this field visible in the admin submission table', 'elzo-forms'); ?></div>
        </div>
    </div>
</div>
<div class="elzo-forms-row elzo-forms-row-2-cols elzo-forms-field-control-group">
    <div class="elzo-forms-column">
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-admin-label-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Admin Label', 'elzo-forms'); ?></label>
            <div class="elzo-forms-field-control-guideline"><?php esc_html_e('This label is used for administrative purposes, and may be an alternative (shorter) version of the field label', 'elzo-forms'); ?></div>
            <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][admin_label]" value="<?php echo esc_attr($admin_label); ?>" class="elzo-forms-field-control elzo-forms-field-admin-label elzo-forms-field-header-part" id="elzo-forms-field-admin-label-<?php echo esc_attr($field_id); ?>">
        </div>
    </div>
    <div class="elzo-forms-column">
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-key-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Field Key', 'elzo-forms'); ?></label>
            <div class="elzo-forms-field-control-guideline"><?php esc_html_e('Technical key for integrations and internal references. Auto-generated as a slug and unique per form.', 'elzo-forms'); ?></div>
            <div class="elzo-forms-field-control-guideline"><?php esc_html_e('Used by variables and integrations. Changing this key may break existing references.', 'elzo-forms'); ?></div>
            <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][field_key]" value="<?php echo esc_attr($field_key); ?>" class="elzo-forms-field-control elzo-forms-field-key" id="elzo-forms-field-key-<?php echo esc_attr($field_id); ?>" data-field-key-manual="<?php echo $field_key !== '' ? '1' : '0'; ?>" placeholder="<?php esc_attr_e('Auto-generated', 'elzo-forms'); ?>" maxlength="64">
        </div>
    </div>
</div>
