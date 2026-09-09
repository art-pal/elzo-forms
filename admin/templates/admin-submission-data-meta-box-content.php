<?php

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included inside admin submission meta-box callback scope, so variables here are function-scoped, not globals.

?>
<div class="elzo-forms-form-meta-box-content elzo-forms-wrapper">
    <?php wp_nonce_field('elzo_forms_submission_meta_box', 'elzo_forms_submission_meta_box_nonce'); ?>
    <?php if($fields){ ?>
        <table class="elzo-submission-data-table" id="elzo-submission-data-table">
            <?php foreach($fields as $field_index => $field){
                $admin_label = !empty($field['admin_label']) ? $field['admin_label'] : '';
                $label = !empty($field['label']) ? $field['label'] : '';
                $value = !empty($field['value']) ? is_array($field['value']) ? implode(', ', $field['value']) : $field['value'] : '';
            ?>
                <tr class="elzo-submission-row">
                    <td class="elzo-submission-line-label">
                        <strong><?php
                            /* translators: %s: Field label or ID. */
                            echo esc_html($admin_label ?: $label) ?: sprintf(esc_html__('Field %s', 'elzo-forms'), esc_html($field['id']));
                        ?></strong>:
                    </td>
                    <td class="elzo-submission-line-value">
                        <div class="elzo-submission-line-value-text"><?php echo $value ? esc_html($value) : '-'; ?></div>
                        <?php if(is_array($field['value'])){ ?>
                            <div class="elzo-submission-line-value-field elzo-forms-repeater-wrapper" style="display:none">
                                <div id="elzo-forms-repeater-field-template" style="display:none">
                                    <div class="elzo-forms-repeater-item">
                                        <textarea class="elzo-forms-field-control" name="fields[<?php echo esc_attr($field_index); ?>][]"></textarea>
                                    </div>
                                </div>
                                <div class="elzo-forms-repeater">
                                    <?php foreach($field['value'] as $item){ ?>
                                        <div class="elzo-forms-repeater-item">
                                            <textarea class="elzo-forms-field-control elzo-submission-line-value-subfield" name="fields[<?php echo esc_attr($field_index); ?>][]"><?php echo esc_textarea($item); ?></textarea>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } else { ?>
                            <textarea class="elzo-submission-line-value-field elzo-forms-field-control" name="fields[<?php echo esc_attr($field_index); ?>]" style="display:none"><?php echo esc_textarea($value); ?></textarea>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </table>
    <?php } else {
        echo esc_html__('There are no fields or JSON data could not be parsed. Raw submission content:', 'elzo-forms');
        echo '<pre style="white-space: pre-wrap;">' . esc_html($submission_content) . '</pre>';
    } ?>
    <?php if($fields || !empty($form_title)){ ?>
        <div class="elzo-submission-footer-actions-wrapper">
            <div class="elzo-submission-footer-actions-start">
                <?php if(!empty($form_title)){ ?>
                    <div class="elzo-submission-form-info">
                        <?php if(!empty($form_edit_url)){ ?>
                            <a href="<?php echo esc_url($form_edit_url); ?>" target="_blank" class="elzo-submission-view-form-link"><strong><?php echo esc_html($form_title); ?></strong></a>
                        <?php } else { ?>
                            <strong><?php echo esc_html($form_title); ?></strong>
                        <?php } ?>
                    </div>
                <?php } ?>
                <em class="elzo-submission-submitted-in">
                    <?php
                        /* translators: %s: Time taken to submit the form. */
                        echo !empty($submission_data['submitted_in']) ? sprintf(esc_html__('Submitted in %s s', 'elzo-forms'), esc_html($submission_data['submitted_in'])) : '';
                    ?>
                </em>
            </div>
            <?php if($fields){ ?>
                <button type="button" class="button elzo-submission-edit-toggle" id="elzo-submission-edit-toggle" data-toggle-text="<?php echo esc_attr__('Close', 'elzo-forms'); ?>"><?php esc_html_e('Edit', 'elzo-forms'); ?></button>
            <?php } ?>
        </div>
    <?php } ?>
</div>