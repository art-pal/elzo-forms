<?php
    // Exit if accessed directly
    defined('ABSPATH') || exit;

    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file is included via admin-logic-group.php -> admin-form-field-logic.php -> admin-form-field.php inside admin meta-box callback scope; variables here are not global.

    $logic_group_index = isset($logic_group_index) ? $logic_group_index : 0;
    $rule_index = isset($rule_index) ? $rule_index : 0;

    $rule = isset($rule) && is_array($rule) ? $rule : [];

    $rule_type     = isset($rule['type'])     ? sanitize_key((string) $rule['type'])     : 'field';
    $rule_operator = isset($rule['operator']) ? (string) $rule['operator']               : '==';
    $rule_settings = isset($rule['settings']) && is_array($rule['settings']) ? $rule['settings'] : [];

    $rule_field_id    = isset($rule_settings['field_id']) ? (string) $rule_settings['field_id'] : '';
    $rule_value       = isset($rule_settings['value'])    ? (string) $rule_settings['value']    : '';
    if ($rule_value === '' && isset($rule_settings['start_time'])) {
        $rule_value = (string) $rule_settings['start_time'];
    }
    $rule_cookie_name = isset($rule_settings['name'])     ? (string) $rule_settings['name']     : '';

    $condition_types   = \ElzoForms\Utilities\Conditional_Logic::get_field_condition_types();
    $condition_type_item = \ElzoForms\Admin\Condition_Type_Picker::get_item($rule_type);
    $operators_map     = \ElzoForms\Utilities\Conditional_Logic::get_field_condition_operators_map();
    $operator_labels   = \ElzoForms\Utilities\Conditional_Logic::get_field_condition_operator_labels();

    // URL values are typed by hand; hint the expected format. Mirrored in
    // ElzoFormsLogicValuePlaceholder() so it survives a condition type change.
    // Page rules get a searchable content picker or a post type dropdown built by
    // the admin script over this input, which then only carries the stored value.
    $rule_value_placeholder = $rule_type === 'url' ? 'https://example.com/pricing/' : '';

    // A saved rule whose type is not registered right now (PRO or an addon
    // inactive) has no operator list and no controls for its own settings. Its
    // stored operator and settings are posted back as they are so a Save keeps
    // the rule intact; see Conditional_Logic::is_storable_condition_type().
    $is_registered_type    = isset($condition_types[$rule_type]);
    $rule_operators        = $operators_map[$rule_type] ?? [];
    $keep_stored_operator  = !$is_registered_type && $rule_operator !== '' && !in_array($rule_operator, $rule_operators, true);
    $stored_extra_settings = [];
    if (!$is_registered_type) {
        foreach ($rule_settings as $setting_key => $setting_value) {
            $setting_key = sanitize_key((string) $setting_key);
            if ($setting_key !== '' && !in_array($setting_key, ['field_id', 'name', 'value'], true) && is_scalar($setting_value)) {
                $stored_extra_settings[$setting_key] = (string) $setting_value;
            }
        }
    }

    $needs_field_id = $rule_type === 'field';
    $needs_name     = $rule_type === 'cookie';
    $needs_value    = !in_array($rule_type, ['auth'], true)
                      && !in_array($rule_operator, ['exists', 'not_exists'], true);

    $name_prefix = "elzo_form_fields[{$step_index}][fields][{$field_index}][rules][{$logic_group_index}][{$rule_index}]";
?>
<div class="elzo-forms-field-logic-group-rule elzo-forms-field-logic-condition-item"
     data-condition-type="<?php echo esc_attr($rule_type); ?>"
     data-operators-map="<?php echo esc_attr(wp_json_encode($operators_map)); ?>"
     data-operator-labels="<?php echo esc_attr(wp_json_encode($operator_labels)); ?>">

    <div class="elzo-forms-field-logic-condition-type-wrapper">
        <select name="<?php echo esc_attr($name_prefix); ?>[type]" class="elzo-forms-field-control elzo-forms-field-logic-condition-type-select" hidden>
            <option value="" <?php selected($rule_type, ''); ?> disabled><?php esc_html_e('Select condition type', 'elzo-forms'); ?></option>
            <?php if ($rule_type !== '' && !isset($condition_types[$rule_type])): ?>
                <option value="<?php echo esc_attr($rule_type); ?>" selected><?php echo esc_html($condition_type_item['label']); ?></option>
            <?php endif; ?>
            <?php foreach($condition_types as $type_val => $type_label): ?>
                <option value="<?php echo esc_attr($type_val); ?>" <?php selected($rule_type, $type_val); ?>><?php echo esc_html($type_label); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="elzo-forms-condition-type-button" aria-haspopup="dialog" aria-expanded="false" aria-controls="elzo-forms-condition-type-picker">
            <span class="screen-reader-text"><?php esc_html_e('Condition type:', 'elzo-forms'); ?> </span>
            <?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg($condition_type_item['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?>
            <span class="elzo-forms-condition-type-button-label"><?php echo esc_html($condition_type_item['label']); ?></span>
            <i class="elzo-icon elzo-icon-chevron-down" aria-hidden="true"></i>
        </button>
    </div>

    <div class="elzo-forms-field-logic-condition-field-id-wrapper" <?php echo !$needs_field_id ? 'style="display:none"' : ''; ?>>
        <?php
        /*
         * Options are built in JS (ElzoFormsFieldSelects) from the fields currently
         * in the DOM, so the list spans every step and follows edits without a save.
         * Rendering them here as well would only cover the step this field sits in,
         * leaving a rule that points at another step with no matching option: the
         * browser would fall back to the first one and JS would then keep that wrong
         * field. data-selected-value carries the stored ID until the options exist.
         */
        ?>
        <select name="<?php echo esc_attr($name_prefix); ?>[settings][field_id]"
                class="elzo-forms-field-control elzo-forms-field-select elzo-forms-field-rule-field-select elzo-forms-field-logic-condition-field-select"
                data-selected-value="<?php echo esc_attr($rule_field_id); ?>"></select>
    </div>

    <div class="elzo-forms-field-logic-condition-name-wrapper" <?php echo !$needs_name ? 'style="display:none"' : ''; ?>>
        <input type="text"
               name="<?php echo esc_attr($name_prefix); ?>[settings][name]"
               value="<?php echo esc_attr($rule_cookie_name); ?>"
               class="elzo-forms-field-control elzo-forms-field-logic-condition-name-input"
               placeholder="<?php esc_attr_e('Cookie name', 'elzo-forms'); ?>" />
    </div>

    <div class="elzo-forms-field-logic-group-rule-operator">
        <select name="<?php echo esc_attr($name_prefix); ?>[operator]"
                class="elzo-forms-field-control elzo-forms-field-logic-group-rule-operator-select elzo-forms-field-logic-condition-operator-select">
            <?php if ($keep_stored_operator): ?>
                <option value="<?php echo esc_attr($rule_operator); ?>" selected><?php echo esc_html($operator_labels[$rule_operator] ?? $rule_operator); ?></option>
            <?php endif; ?>
            <?php foreach($rule_operators as $op_val): ?>
                <option value="<?php echo esc_attr($op_val); ?>" <?php selected($rule_operator, $op_val); ?>><?php echo esc_html($operator_labels[$op_val] ?? $op_val); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="elzo-forms-field-logic-group-rule-value" <?php echo !$needs_value ? 'style="display:none"' : ''; ?>>
        <input type="text"
               name="<?php echo esc_attr($name_prefix); ?>[settings][value]"
               value="<?php echo esc_attr($rule_value); ?>"
               <?php echo $rule_value_placeholder ? 'placeholder="' . esc_attr($rule_value_placeholder) . '"' : ''; ?>
               class="elzo-forms-field-control elzo-forms-field-logic-group-rule-value-input" />
    </div>

    <?php foreach ($stored_extra_settings as $setting_key => $setting_value): ?>
        <input type="hidden"
               name="<?php echo esc_attr($name_prefix); ?>[settings][<?php echo esc_attr($setting_key); ?>]"
               value="<?php echo esc_attr($setting_value); ?>"
               class="elzo-forms-field-logic-stored-setting"
               data-stored-type="<?php echo esc_attr($rule_type); ?>" />
    <?php endforeach; ?>

    <?php if ($rule_type !== '' && !$is_registered_type): ?>
        <p class="elzo-forms-field-logic-unavailable-notice" data-stored-type="<?php echo esc_attr($rule_type); ?>">
            <span class="dashicons dashicons-warning" aria-hidden="true"></span>
            <span><?php
                echo esc_html(!empty($condition_type_item['pro'])
                    ? __('Requires Elzo Forms PRO. The condition is kept, but counts as not met until PRO is active.', 'elzo-forms')
                    : __('This condition type is not available. The condition is kept, but counts as not met until the plugin that adds it is active.', 'elzo-forms'));
            ?></span>
        </p>
    <?php endif; ?>

    <div class="elzo-forms-field-logic-group-rule-and">
        <button type="button" class="button elzo-forms-field-logic-add-rule-button"><?php esc_html_e('and', 'elzo-forms'); ?></button>
        <button type="button" class="button elzo-forms-field-logic-remove-rule-button" data-click-confirmation="<?php echo esc_attr__('Delete?', 'elzo-forms'); ?>"><span class="elzo-icon elzo-icon-close"></span></button>
    </div>
</div>
