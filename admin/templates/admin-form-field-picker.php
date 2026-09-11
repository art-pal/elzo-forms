<?php
    // Exit if accessed directly
    defined('ABSPATH') || exit;

    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file is included via admin-form-fields.php inside elzo_forms_data_meta_box_callback() in admin.php (function scope). Variables here are not in global scope.

    /*
     * Field type picker, shared by every "Add Field" button and every field's
     * Type control.
     *
     * An option only names a field type ("textarea", "text:email", ...). The
     * admin script creates or rebuilds the field with that type, so nothing
     * here posts into the form.
     */
    $field_picker_groups = \ElzoForms\Admin\Field_Picker::get_item_groups();
?>
<div class="elzo-forms-field-picker" id="elzo-forms-field-picker" role="dialog" aria-label="<?php echo esc_attr__('Add field', 'elzo-forms'); ?>" data-label-add="<?php echo esc_attr__('Add field', 'elzo-forms'); ?>" data-label-change="<?php echo esc_attr__('Change field type', 'elzo-forms'); ?>" hidden>
    <div class="elzo-forms-field-picker-search">
        <i class="elzo-icon elzo-icon-search" aria-hidden="true"></i>
        <input type="search" id="elzo-forms-field-picker-search" class="elzo-forms-field-picker-search-input" placeholder="<?php echo esc_attr__('Search fields', 'elzo-forms'); ?>" aria-label="<?php echo esc_attr__('Search fields', 'elzo-forms'); ?>" role="combobox" aria-autocomplete="list" aria-expanded="true" aria-controls="elzo-forms-field-picker-list" autocomplete="off" autocapitalize="off" spellcheck="false">
    </div>
    <div id="elzo-forms-field-picker-list" class="elzo-forms-field-picker-list" role="listbox" aria-label="<?php echo esc_attr__('Field types', 'elzo-forms'); ?>">
        <div class="elzo-forms-field-picker-results" role="presentation" hidden></div>
        <?php foreach ($field_picker_groups as $field_picker_group): ?>
            <?php $field_picker_group_label_id = 'elzo-forms-field-picker-group-' . sanitize_html_class($field_picker_group['key']); ?>
            <div class="elzo-forms-field-picker-group" role="group" aria-labelledby="<?php echo esc_attr($field_picker_group_label_id); ?>" data-field-category="<?php echo esc_attr($field_picker_group['key']); ?>">
                <div id="<?php echo esc_attr($field_picker_group_label_id); ?>" class="elzo-forms-field-picker-group-label" role="presentation"><?php echo esc_html($field_picker_group['label']); ?></div>
                <?php foreach ($field_picker_group['items'] as $field_picker_item): ?>
                    <div id="<?php echo esc_attr($field_picker_item['id']); ?>" class="elzo-forms-field-picker-option" role="option" aria-selected="false" data-picker-type="<?php echo esc_attr($field_picker_item['type']); ?>" data-picker-keywords="<?php echo esc_attr(implode('|', $field_picker_item['keywords'])); ?>" data-picker-available="1">
                        <?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg($field_picker_item['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork from Field_Picker; unknown icon keys fall back to built-in artwork. ?>
                        <span class="elzo-forms-field-picker-option-label"><?php echo esc_html($field_picker_item['label']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="elzo-forms-field-picker-status" role="status" data-empty-message="<?php echo esc_attr__('No fields found', 'elzo-forms'); ?>"></p>
</div>
