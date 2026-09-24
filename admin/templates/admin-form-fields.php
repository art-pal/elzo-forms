<?php
    // Exit if accessed directly
    defined('ABSPATH') || exit;

    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file is included via admin-form-meta-box-content.php inside elzo_forms_data_meta_box_callback() in admin.php (function scope). Variables here are not in global scope.

    // Create form object
    $form_object = new \ElzoForms\Form\Form($post);
    $steps = $form_object->get_steps();

    // Ensure at least one step exists
    if (empty($steps)) {
        $steps = [['label' => '', 'fields' => []]];
    }

    $steps_total = count($steps);
?>
<div id="elzo-forms-repeater" class="elzo-forms-repeater-wrapper elzo-forms-wrapper">
    <div id="elzo-forms-repeater-field-template" style="display:none">
        <?php require plugin_dir_path(__DIR__) . 'templates/admin-form-field.php'; ?>
    </div>
    <div class="elzo-forms-repeater-toggle-actions-wrapper">
        <a href="#" class="elzo-forms-toggle-all-button" data-elzo-toggle-all="expand" data-elzo-toggle-target=".elzo-forms-fields-repeater .elzo-forms-field"><?php esc_html_e('Expand all', 'elzo-forms'); ?></a>
        <a href="#" class="elzo-forms-toggle-all-button" data-elzo-toggle-all="collapse" data-elzo-toggle-target=".elzo-forms-fields-repeater .elzo-forms-field"><?php esc_html_e('Collapse all', 'elzo-forms'); ?></a>
    </div>
    <div class="elzo-forms-steps-repeater elzo-forms-repeater elzo-forms-form-fields" id="elzo-forms-steps-repeater">
        <?php
        /*
         * Field numbering runs across the whole form, steps included, and starts
         * at 1 because index 0 belongs to the hidden repeater template that the
         * save handler drops. The position is derived from the render order
         * rather than read from the stored "index": forms written outside the
         * admin UI (imports, fixtures, code) carry no index at all, which would
         * otherwise number every field 0 and make all of them post into the same
         * elzo_form_fields[step][fields][0] slot.
         */
        $field_position = 0;

        foreach($steps as $step_index => $step){
            // Add 1 to the step index to start from 1
            $step_index++;

            $label = !empty($step['label']) ? $step['label'] : '';
            $fields = !empty($step['fields']) ? $step['fields'] : [];

            if ($fields) {
                foreach($fields as $field_index => $field){
                    if (!is_array($field)) {
                        unset($fields[$field_index]);
                        continue;
                    }

                    $field_position++;

                    // Add step index and form-wide position to each field
                    $fields[$field_index]['step_index'] = $step_index;
                    $fields[$field_index]['index'] = $field_position;

                    // Apply filter to each field
                    $fields[$field_index] = apply_filters('elzo_forms_admin_field', $fields[$field_index]);

                    // Normalize field data through the field class so admin logic
                    // behavior comes from PHP field definitions.
                    $fields[$field_index] = \ElzoForms\Field\Field::from($fields[$field_index])->get_admin_field_data();
                }
            }
        ?>
            <div class="elzo-forms-step">
                <div class="elzo-forms-step-header" <?php echo $steps_total > 1 ? '' : 'style="display:none"'; ?> >
                    <div class="elzo-forms-sortable-dragger elzo-forms-step-header-dragger"></div>
                    <label for="arpal-forms-step-label-<?php echo esc_attr($step_index); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Step label', 'elzo-forms'); ?></label>
                    <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][label]" id="arpal-forms-step-label-<?php echo esc_attr($step_index); ?>" placeholder="<?php echo esc_attr__('Step', 'elzo-forms'); ?> 1" class="elzo-forms-step-field elzo-forms-field-control-inline" value="<?php echo esc_attr($label); ?>">
                    <input type="hidden" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][index]" class="step-index-value" value="<?php echo esc_attr($step_index); ?>">
                </div>
                <div class="elzo-forms-step-fields">
                    <div class="elzo-forms-fields-empty-message elzo-forms-repeater-empty-spacer" <?php echo $fields ? 'style="display:none"' : ''; ?> >
                        <?php esc_html_e('No fields added yet', 'elzo-forms'); ?>.
                    </div>
                    <div class="elzo-forms-fields-repeater elzo-forms-repeater">
                        <?php if ($fields) {
                            foreach ($fields as $field) {
                                include plugin_dir_path(__DIR__) . 'templates/admin-form-field.php';
                            }
                        } ?>
                    </div>
                    <div class="elzo-forms-step-fields-footer">
                        <button type="button" class="button button-primary elzo-forms-add-field-button" aria-haspopup="dialog" aria-expanded="false" aria-controls="elzo-forms-field-picker"><?php esc_html_e('Add Field', 'elzo-forms'); ?></button>
                        <div class="elzo-forms-step-fields-footer-step-actions">
                            <button type="button" class="button elzo-forms-step-duplicate-button"><?php esc_html_e('Duplicate Step', 'elzo-forms'); ?></button>
                            <button type="button" class="button elzo-forms-step-remove-button" <?php echo $steps_total > 1 ? '' : 'style="display:none"'; ?>><?php esc_html_e('Remove Step', 'elzo-forms'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
    <div class="elzo-forms-repeater-footer elzo-forms-steps-footer">
        <button type="button" class="button button-primary elzo-forms-add-step-button"><?php esc_html_e('Add Step', 'elzo-forms'); ?></button>
    </div>
    <?php require plugin_dir_path(__DIR__) . 'templates/admin-form-field-picker.php'; ?>
    <?php require plugin_dir_path(__DIR__) . 'templates/admin-condition-type-picker.php'; ?>
</div>
