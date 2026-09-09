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
    $operators_map     = \ElzoForms\Utilities\Conditional_Logic::get_field_condition_operators_map();
    $operator_labels   = \ElzoForms\Utilities\Conditional_Logic::get_field_condition_operator_labels();

    // Page and URL values are typed by hand; hint the expected format. Mirrored in
    // ElzoFormsLogicValuePlaceholder() so it survives a condition type change.
    $rule_value_placeholder = '';
    if ($rule_type === 'page') {
        $rule_value_placeholder = strpos($rule_operator, 'post_type_') === 0 ? 'page, post' : '12, 15';
    } elseif ($rule_type === 'url') {
        $rule_value_placeholder = 'https://example.com/pricing/';
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
        <select name="<?php echo esc_attr($name_prefix); ?>[type]" class="elzo-forms-field-control elzo-forms-field-logic-condition-type-select">
            <option value="" <?php selected($rule_type, ''); ?> disabled><?php esc_html_e('Select condition type', 'elzo-forms'); ?></option>
            <?php foreach($condition_types as $type_val => $type_label): ?>
                <option value="<?php echo esc_attr($type_val); ?>" <?php selected($rule_type, $type_val); ?>><?php echo esc_html($type_label); ?></option>
            <?php endforeach; ?>
            <optgroup label="<?php echo esc_attr__('Pro', 'elzo-forms'); ?>">
                <option value="url" <?php selected($rule_type, 'url'); ?> disabled><?php esc_html_e('URL', 'elzo-forms'); ?></option>
                <option value="user" <?php selected($rule_type, 'user'); ?> disabled><?php esc_html_e('User', 'elzo-forms'); ?></option>
                <option value="cookie" <?php selected($rule_type, 'cookie'); ?> disabled><?php esc_html_e('Cookie', 'elzo-forms'); ?></option>
                <option value="date_time" <?php selected($rule_type, 'date_time'); ?> disabled><?php esc_html_e('Date and Time', 'elzo-forms'); ?></option>
            </optgroup>
        </select>
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
            <?php foreach($operators_map[$rule_type] ?? [] as $op_val): ?>
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

    <div class="elzo-forms-field-logic-group-rule-and">
        <button type="button" class="button elzo-forms-field-logic-add-rule-button"><?php esc_html_e('and', 'elzo-forms'); ?></button>
        <button type="button" class="button elzo-forms-field-logic-remove-rule-button" data-click-confirmation="<?php echo esc_attr__('Delete?', 'elzo-forms'); ?>"><span class="elzo-icon elzo-icon-close"></span></button>
    </div>
</div>