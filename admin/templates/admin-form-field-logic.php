<?php
    // Exit if accessed directly
    defined('ABSPATH') || exit;

    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included via admin-form-field.php within admin meta-box callback scope; variables here are function-scoped, not globals.

    $logic = !empty($field['logic']);
    $logic_rules = !empty($field['rules']) ? $field['rules'] : [];
    $default_logic = [[[
        'type'     => 'input',
        'operator' => '==',
        'settings' => ['id' => 'elzo-forms-field-logic-' . $field_id, 'value' => 'Checked'],
    ]]];
?>
<div class="elzo-forms-field-control-group">
    <label for="elzo-forms-field-logic-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><input type="checkbox" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][logic]" value="Checked" id="elzo-forms-field-logic-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-check elzo-forms-field-logic-checkbox elzo-forms-field-header-part" <?php echo checked($logic, true, false); ?>> <?php esc_html_e('Conditional Logic', 'elzo-forms'); ?></label>
</div>
<div class="elzo-forms-field-control-group elzo-forms-field-logic-repeater-wrapper" data-ef-logic="<?php echo esc_attr(wp_json_encode($default_logic)); ?>" <?php echo $logic ? '' : 'style="display:none"' ?> >
    <div class="elzo-forms-logic-label"><?php esc_html_e('Show this field if', 'elzo-forms'); ?></div>
    <div class="elzo-forms-field-logic-repeater">
        <?php if ($logic_rules) { ?>
            <?php foreach ($logic_rules as $logic_group_index => $group) {
                include plugin_dir_path(__DIR__) . 'templates/admin-logic-group.php';
            } ?>
        <?php } else {
            include plugin_dir_path(__DIR__) . 'templates/admin-logic-group.php';
        } ?>
    </div>
    <div class="elzo-forms-field-logic-repeater-footer">
        <button type="button" class="button elzo-forms-field-logic-add-group-button"><?php esc_html_e('Add rule group', 'elzo-forms'); ?></button>
    </div>
</div>