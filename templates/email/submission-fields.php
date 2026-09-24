<?php
/**
 * Submitted fields table shared by email templates and the all-fields variable.
 *
 * @package ElzoForms\Templates
 * @version 1.2.0
 * @var array $submission_data Submission fields.
 * @var \ElzoForms\Submission\Submission_Field_Presenter $field_presenter Email presenter.
 */
defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template arguments are function-scoped.
?>
                <table style="width:100%;border-collapse:collapse;">
                    <?php foreach($submission_data['fields'] as $field){
                        // Apply filter to each field
                        $field = apply_filters('elzo_forms_admin_email_field', $field);

                        $admin_label = !empty($field['admin_label']) ? $field['admin_label'] : '';
                        $label = !empty($field['label']) ? $field['label'] : '';
                    ?>
                        <tr>
                            <td style="padding:10px;border:1px solid #ddd;width:100px;padding-right:15px"><?php echo esc_html($admin_label ? $admin_label : $label); ?></td>
                            <td style="padding:10px;border:1px solid #ddd;"><?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Field renderers own escaping and return safe HTML for this channel.
                                echo $field_presenter->render($field);
                            ?></td>
                        </tr>
                    <?php } ?>
                </table>
