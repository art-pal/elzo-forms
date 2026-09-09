<?php
    // Exit if accessed directly
    defined('ABSPATH') || exit;

    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included via admin-form-field.php within admin meta-box callback scope, so variables here are function-scoped, not globals.

    // Field View Settings
    $field_custom_id = !empty($field['custom_id']) ? $field['custom_id'] : null;
    $field_custom_class = !empty($field['custom_class']) ? $field['custom_class'] : null;
    $wrapper_custom_id = !empty($field['wrapper_custom_id']) ? $field['wrapper_custom_id'] : null;
    $wrapper_custom_class = !empty($field['wrapper_custom_class']) ? $field['wrapper_custom_class'] : null;
    $field_widths = \ElzoForms\Utilities\Admin::get_field_widths();
    $field_width = !empty($field['width']) && is_array($field['width']) ? array_filter($field['width']) : [];
?>
<div class="elzo-forms-field-specific-settings-wrapper elzo-forms-field-control-group elzo-forms-field-sep-bottom" data-setting-category="view">
    <?php $field_object->render_field_settings('view'); ?>
</div>
<div class="elzo-forms-field-control-group-wrapper elzo-forms-field-sep-bottom elzo-forms-field-pb-0">
    <label for="elzo-forms-field-width-<?php echo esc_attr($field_id); ?>-xs" class="elzo-forms-field-control-label"><?php esc_html_e('Field Width', 'elzo-forms'); ?></label>
    <div class="elzo-forms-row elzo-forms-row-3-cols elzo-forms-row-mb">
        <?php $last_keypoint = '1/1'; foreach(\ElzoForms\Utilities\Helpers::get_responsive_keypoints() as $keypoint => $label): if(isset($field_width[$keypoint])) $last_keypoint = $field_width[$keypoint]; ?>
            <div class="elzo-forms-column">
                <div class="elzo-forms-field-control-group">
                    <label for="elzo-forms-field-width-<?php echo esc_attr($field_id); ?>-<?php echo esc_attr($keypoint); ?>" class="elzo-forms-field-control-label elzo-forms-field-control-sub-label"><?php
                        /* translators: %s: Screen size label. */
                        echo sprintf(esc_html__('%s screens', 'elzo-forms'), esc_html($label));
                    ?></label>
                    <select name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][width][<?php echo esc_attr($keypoint); ?>]" class="elzo-forms-field-control elzo-forms-field-header-part elzo-forms-width-subfield" id="elzo-forms-field-width-<?php echo esc_attr($field_id); ?>-<?php echo esc_attr($keypoint); ?>">
                        <option value=""><?php esc_html_e('Auto', 'elzo-forms'); ?> (<?php echo esc_html($last_keypoint); ?>)</option>
                        <?php foreach($field_widths as $option_value => $option_label): ?>
                            <option value="<?php echo esc_attr($option_value); ?>" <?php if($option_value) selected((!empty($field_width) && !empty($field_width[$keypoint]) ? $field_width[$keypoint] : null), $option_value); ?>><?php echo esc_html($option_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<div class="elzo-forms-row elzo-forms-row-2-cols elzo-forms-field-control-group">
    <div class="elzo-forms-column">
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-custom-id-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Field ID', 'elzo-forms'); ?></label>
            <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][custom_id]" value="<?php echo esc_attr($field_custom_id); ?>" class="elzo-forms-field-control" id="elzo-forms-field-custom-id-<?php echo esc_attr($field_id); ?>">
        </div>
    </div>
    <div class="elzo-forms-column">
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-custom-class-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Field Class', 'elzo-forms'); ?></label>
            <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][custom_class]" value="<?php echo esc_attr($field_custom_class); ?>" id="elzo-forms-field-custom-class-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
        </div>
    </div>
</div>
<div class="elzo-forms-row elzo-forms-row-2-cols elzo-forms-field-control-group">
    <div class="elzo-forms-column">
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-wrapper-custom-id-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Wrapper ID', 'elzo-forms'); ?></label>
            <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][wrapper_custom_id]" value="<?php echo esc_attr($wrapper_custom_id); ?>" class="elzo-forms-field-control" id="elzo-forms-field-wrapper-custom-id-<?php echo esc_attr($field_id); ?>">
        </div>
    </div>
    <div class="elzo-forms-column">
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-wrapper-custom-class-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Wrapper Class', 'elzo-forms'); ?></label>
            <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][wrapper_custom_class]" value="<?php echo esc_attr($wrapper_custom_class); ?>" id="elzo-forms-field-wrapper-custom-class-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
        </div>
    </div>
</div>