<?php

defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included via admin settings/meta-box callback chains, so variables here are function-scoped, not globals.

    // Form settings
    $form_global_settings = get_option('elzo_forms_form_settings', array());
    $form_default_settings = \ElzoForms\Services\Settings::get_default_settings();

    $alert_types = \ElzoForms\Utilities\Admin::get_alert_types();

    $email_notifications = isset($form_settings['email_notifications']) ? $form_settings['email_notifications'] : (!empty($elzo_plugin_settings) ? $form_default_settings['email_notifications'] : '');
    $form_submission_type = isset($form_settings['form_submission_type']) ? $form_settings['form_submission_type'] : (!empty($elzo_plugin_settings) ? $form_default_settings['form_submission_type'] : '');
    $form_alert_type = isset($form_settings['form_alert_type']) ? $form_settings['form_alert_type'] : (!empty($elzo_plugin_settings) ? $form_default_settings['form_alert_type'] : '');
    $clear_form_after_submission = isset($form_settings['clear_form_after_submission']) ? $form_settings['clear_form_after_submission'] : (!empty($elzo_plugin_settings) ? $form_default_settings['clear_form_after_submission'] : '');
    $hide_form_after_submission = isset($form_settings['hide_form_after_submission']) ? $form_settings['hide_form_after_submission'] : (!empty($elzo_plugin_settings) ? $form_default_settings['hide_form_after_submission'] : '');
    $blocked_ips = isset($form_settings['blocked_ips']) ? $form_settings['blocked_ips'] : '';
    $blocked_words = isset($form_settings['blocked_words']) ? $form_settings['blocked_words'] : '';
    $blocked_submission_action = isset($form_settings['blocked_submission_action']) ? $form_settings['blocked_submission_action'] : (!empty($elzo_plugin_settings) ? 'remove' : 'spam');
    $min_submission_interval = isset($form_settings['min_submission_interval']) ? $form_settings['min_submission_interval'] : '';
    $min_submission_delay = isset($form_settings['min_submission_delay']) ? $form_settings['min_submission_delay'] : '';
?>
<div class="elzo-forms-admin-search-section">
    <div class="elzo-forms-admin-search">
        <label for="elzo-forms-admin-settings-form-search-input" class="elzo-forms-admin-search-label-icon"><i class="elzo-icon elzo-icon-search"></i></label>
        <input type="search" class="elzo-forms-admin-search-input" id="elzo-forms-admin-settings-form-search-input" placeholder="<?php echo esc_attr__('Search form settings', 'elzo-forms'); ?>">
        <button type="button" class="elzo-forms-admin-search-clear" style="display:none"><i class="elzo-icon elzo-icon-close"></i></button>
    </div>
    <div class="elzo-forms-admin-search-nothing-found" style="display:none">
        <p><?php esc_html_e('No settings found', 'elzo-forms'); ?></p>
    </div>
    <h3 class="elzo-forms-admin-search-heading"><?php esc_html_e('Redirect settings', 'elzo-forms'); ?></h3>
    <table class="form-table">
        <tbody class="elzo-forms-admin-search-list">
            <tr>
                <th scope="row"><label for="elzo_forms_form_settings_form_redirect_url"><?php esc_html_e('Form redirect URL', 'elzo-forms'); ?></label></th>
                <td>
                    <input type="text" class="regular-text" name="elzo_forms_form_settings[form_redirect_url]" id="elzo_forms_form_settings_form_redirect_url" value="<?php echo !empty($form_settings['form_redirect_url']) ? esc_attr($form_settings['form_redirect_url']) : ''; ?>">
                    <p class="description"><?php esc_html_e('Enter the URL where the form will be redirected after successful submission. Leave empty to stay on the same page.', 'elzo-forms'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_form_settings_form_redirect_delay"><?php esc_html_e('Form redirect delay', 'elzo-forms'); ?></label></th>
                <td>
                    <input type="number" class="small-text" name="elzo_forms_form_settings[form_redirect_delay]" id="elzo_forms_form_settings_form_redirect_delay" value="<?php echo !empty($form_settings['form_redirect_delay']) ? esc_attr($form_settings['form_redirect_delay']) : '0'; ?>" min="0" placeholder="0">
                    <p class="description"><?php esc_html_e('Enter the number of seconds to wait before redirecting the form (in case with AJAX submission).', 'elzo-forms'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <h3 class="elzo-forms-admin-search-heading"><?php esc_html_e('Email notifications', 'elzo-forms'); ?></h3>
    <table class="form-table">
        <tbody class="elzo-forms-admin-search-list">
            <tr>
                <th scope="row"><label for="elzo_forms_form_settings_email_notifications"><?php esc_html_e('Email notifications', 'elzo-forms'); ?></label></th>
                <td>
                    <select name="elzo_forms_form_settings[email_notifications]" id="elzo_forms_form_settings_email_notifications">
                        <?php if(empty($elzo_plugin_settings)){ // Display only on the form edit page ?>
                            <option value=""><?php echo esc_html__('Default', 'elzo-forms'); ?> (<?php
                                echo !empty($form_global_settings['email_notifications']) && in_array($form_global_settings['email_notifications'], ['yes', 'no'])
                                    ? esc_html(ucfirst($form_global_settings['email_notifications']))
                                    : esc_html__('Yes', 'elzo-forms');
                            ?>)</option>
                        <?php } ?>
                        <option value="yes" <?php selected($email_notifications, 'yes'); ?>><?php esc_html_e('Yes', 'elzo-forms'); ?></option>
                        <option value="no" <?php selected($email_notifications, 'no'); ?>><?php esc_html_e('No', 'elzo-forms'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="elzo_forms_form_settings_email_notification_recipients"><?php esc_html_e('Email notification recipients', 'elzo-forms'); ?></label>
                </th>
                <td>
                    <textarea name="elzo_forms_form_settings[email_notification_recipients]" class="regular-text" id="elzo_forms_form_settings_email_notification_recipients" rows="3"><?php echo !empty($form_settings['email_notification_recipients']) ? esc_textarea($form_settings['email_notification_recipients']) : ''; ?></textarea>
                    <p class="description"><?php esc_html_e('Enter email addresses separated by commas. By default, the email address of the site administrator is used.', 'elzo-forms'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <h3 class="elzo-forms-admin-search-heading"><?php esc_html_e('Form appearance settings', 'elzo-forms'); ?></h3>
    <table class="form-table">
        <tbody class="elzo-forms-admin-search-list">
            <?php if(empty($elzo_plugin_settings)){ // Display only on the form edit page ?>
                <tr>
                    <th scope="row"><label for="elzo_forms_form_settings_form_custom_id"><?php esc_html_e('Form custom ID', 'elzo-forms'); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" name="elzo_forms_form_settings[form_custom_id]" id="elzo_forms_form_settings_form_custom_id" value="<?php echo !empty($form_settings['form_custom_id']) ? esc_attr($form_settings['form_custom_id']) : ''; ?>">
                        <p class="description"><?php esc_html_e('Enter the custom ID for the form. This ID will be used as ID attribute of the form element.', 'elzo-forms'); ?></p>
                    </td>
                </tr>
            <?php } ?>
            <tr>
                <th scope="row"><label for="elzo_forms_form_settings_form_custom_class"><?php esc_html_e('Form custom class', 'elzo-forms'); ?></label></th>
                <td>
                    <input type="text" class="regular-text" name="elzo_forms_form_settings[form_custom_class]" id="elzo_forms_form_settings_form_custom_class" value="<?php echo !empty($form_settings['form_custom_class']) ? esc_attr($form_settings['form_custom_class']) : ''; ?>">
                    <p class="description"><?php esc_html_e('Enter the class name or names separated by spaces.', 'elzo-forms'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_form_settings_form_field_class"><?php esc_html_e('Form field class', 'elzo-forms'); ?></label></th>
                <td>
                    <input type="text" class="regular-text" name="elzo_forms_form_settings[form_field_class]" id="elzo_forms_form_settings_form_field_class" value="<?php echo !empty($form_settings['form_field_class']) ? esc_attr($form_settings['form_field_class']) : ''; ?>">
                    <p class="description"><?php esc_html_e('Enter the class name or names separated by spaces.', 'elzo-forms'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_form_settings_form_field_error_class"><?php esc_html_e('Form field error class', 'elzo-forms'); ?></label></th>
                <td>
                    <input type="text" class="regular-text" name="elzo_forms_form_settings[form_field_error_class]" id="elzo_forms_form_settings_form_field_error_class" value="<?php echo !empty($form_settings['form_field_error_class']) ? esc_attr($form_settings['form_field_error_class']) : ''; ?>" >
                    <p class="description"><?php esc_html_e('Enter the class name or names separated by spaces.', 'elzo-forms'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_form_settings_form_alert_type"><?php esc_html_e('Form alert type', 'elzo-forms'); ?></label></th>
                <td>
                    <select name="elzo_forms_form_settings[form_alert_type]" id="elzo_forms_form_settings_form_alert_type">
                        <?php if(empty($elzo_plugin_settings)){ ?>
                            <option value=""><?php echo esc_html__('Default', 'elzo-forms'); ?> (<?php
                                echo !empty($form_global_settings['form_alert_type']) && isset($alert_types[$form_global_settings['form_alert_type']])
                                    ? esc_html($alert_types[$form_global_settings['form_alert_type']])
                                    : esc_html__('None', 'elzo-forms');
                            ?>)</option>
                        <?php } ?>
                        <?php foreach($alert_types as $alert_value => $alert_label): ?>
                            <option value="<?php echo esc_attr($alert_value); ?>" <?php selected($form_alert_type, $alert_value); ?>><?php echo esc_html($alert_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Select the type of alert to display after form submission or validation.', 'elzo-forms'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_form_settings_clear_form_after_submission"><?php esc_html_e("Clear form after submission", 'elzo-forms'); ?></label></th>
                <td>
                    <select name="elzo_forms_form_settings[clear_form_after_submission]" id="elzo_forms_form_settings_clear_form_after_submission">
                        <?php if(empty($elzo_plugin_settings)){ ?>
                            <option value=""><?php echo esc_html__('Default', 'elzo-forms'); ?> (<?php
                                echo !empty($form_global_settings['clear_form_after_submission']) && in_array($form_global_settings['clear_form_after_submission'], ['yes', 'no'])
                                    ? esc_html(ucfirst($form_global_settings['clear_form_after_submission']))
                                    : esc_html__('Yes', 'elzo-forms');
                            ?>)</option>
                        <?php } ?>
                        <option value="yes" <?php selected($clear_form_after_submission, 'yes'); ?>><?php esc_html_e('Yes', 'elzo-forms'); ?></option>
                        <option value="no" <?php selected($clear_form_after_submission, 'no'); ?>><?php esc_html_e('No', 'elzo-forms'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Choose whether to clear form fields after a successful submission.', 'elzo-forms'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_form_settings_hide_form_after_submission"><?php esc_html_e('Hide form after submission', 'elzo-forms'); ?></label></th>
                <td>
                    <select name="elzo_forms_form_settings[hide_form_after_submission]" id="elzo_forms_form_settings_hide_form_after_submission">
                        <?php if(empty($elzo_plugin_settings)){ ?>
                            <option value=""><?php echo esc_html__('Default', 'elzo-forms'); ?> (<?php
                                echo !empty($form_global_settings['hide_form_after_submission']) && in_array($form_global_settings['hide_form_after_submission'], ['yes', 'no'])
                                    ? esc_html(ucfirst($form_global_settings['hide_form_after_submission']))
                                    : esc_html__('No', 'elzo-forms');
                            ?>)</option>
                        <?php } ?>
                        <option value="yes" <?php selected($hide_form_after_submission, 'yes'); ?>><?php esc_html_e('Yes', 'elzo-forms'); ?></option>
                        <option value="no" <?php selected($hide_form_after_submission, 'no'); ?>><?php esc_html_e('No', 'elzo-forms'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Choose whether to hide the form after successful submission.', 'elzo-forms'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <h3 class="elzo-forms-admin-search-heading"><?php esc_html_e('Spam protection settings', 'elzo-forms'); ?></h3>
    <table class="form-table">
        <tbody class="elzo-forms-admin-search-list">
            <tr>
                <th scope="row">
                    <label for="elzo_forms_form_settings_blocked_ips"><?php esc_html_e('Blocked IPs', 'elzo-forms'); ?></label>
                </th>
                <td>
                    <textarea name="elzo_forms_form_settings[blocked_ips]" class="regular-text" id="elzo_forms_form_settings_blocked_ips" rows="5" placeholder="<?php echo empty($elzo_plugin_settings) && !empty($form_global_settings['blocked_ips']) ? esc_attr($form_global_settings['blocked_ips']) : esc_attr__('Enter IP addresses separated by commas (e.g. 192.168.0.1, 10.0.0.2)', 'elzo-forms'); ?>"><?php echo !empty($blocked_ips) ? esc_textarea($blocked_ips) : ''; ?></textarea>
                    <p class="description"><?php esc_html_e('List IP addresses to block from submitting forms. Enter addresses separated by commas; submissions from matching IPs will be rejected.', 'elzo-forms'); ?></p>
                </td>
            </tr>
            <?php // Blocked words ?>
            <tr>
                <th scope="row">
                    <label for="elzo_forms_form_settings_blocked_words"><?php esc_html_e('Blocked Words', 'elzo-forms'); ?></label>
                </th>
                <td>
                    <textarea name="elzo_forms_form_settings[blocked_words]" class="regular-text" id="elzo_forms_form_settings_blocked_words" rows="5" placeholder="<?php echo empty($elzo_plugin_settings) && !empty($form_global_settings['blocked_words']) ? esc_attr($form_global_settings['blocked_words']) : esc_attr__('Enter words or phrases separated by commas (e.g. spam, test)', 'elzo-forms'); ?>"><?php echo !empty($blocked_words) ? esc_textarea($blocked_words) : ''; ?></textarea>
                    <p class="description"><?php
                        /* translators: 1: Exact word pattern, 2: Starts-with pattern, 3: Ends-with pattern, 4: Contains pattern. */
                        echo sprintf(esc_html__('Enter items separated by commas; %1$s - exact word, %2$s - start with, %3$s - end with, %4$s - contains. Submissions containing any of these will be rejected.', 'elzo-forms'), '<strong>'.esc_html__('word', 'elzo-forms').'</strong>', '<strong>'.esc_html__('word', 'elzo-forms').'*</strong>', '<strong>*'.esc_html__('word', 'elzo-forms').'</strong>', '<strong>*'.esc_html__('word', 'elzo-forms').'*</strong>');
                    ?></p>
                </td>
            </tr>
            <?php // Blocked action ?>
            <tr>
                <th scope="row"><label for="elzo_forms_form_settings_blocked_submission_action"><?php esc_html_e('Blocked submission action', 'elzo-forms'); ?></label></th>
                <td>
                    <select name="elzo_forms_form_settings[blocked_submission_action]" id="elzo_forms_form_settings_blocked_submission_action">
                        <?php if(empty($elzo_plugin_settings)){ // Display only on the form edit page ?>
                            <option value=""><?php echo esc_html__('Default', 'elzo-forms'); ?> (<?php
                                echo !empty($form_global_settings['blocked_submission_action'])
                                    ? esc_html(ucfirst($form_global_settings['blocked_submission_action']))
                                    : esc_html__('Remove', 'elzo-forms');
                            ?>)</option>
                        <?php } ?>
                        <option value="remove" <?php selected($blocked_submission_action, 'remove'); ?>><?php esc_html_e('Remove', 'elzo-forms'); ?></option>
                        <option value="spam" <?php selected($blocked_submission_action, 'spam'); ?>><?php esc_html_e('Mark as Spam', 'elzo-forms'); ?></option>
                    </select>
                </td>
            </tr>
            <?php // Minimum submission interval ?>
            <tr>
                <th scope="row"><label for="elzo_forms_form_settings_min_submission_interval"><?php esc_html_e('Minimum submission interval', 'elzo-forms'); ?></label></th>
                <td>
                    <input type="number" class="small-text" name="elzo_forms_form_settings[min_submission_interval]" id="elzo_forms_form_settings_min_submission_interval" value="<?php echo !empty($min_submission_interval) ? esc_attr($min_submission_interval) : ''; ?>" min="0" placeholder="<?php echo empty($elzo_plugin_settings) && !empty($form_global_settings['min_submission_interval']) ? esc_attr($form_global_settings['min_submission_interval']) : '20'; ?>">
                    <p class="description"><?php esc_html_e('Enter the minimum number of seconds required between submissions from the same IP address. Set to 0 to disable this check.', 'elzo-forms'); ?></p>
                </td>
            </tr>
            <?php // Minimum submission delay ?>
            <tr>
                <th scope="row"><label for="elzo_forms_form_settings_min_submission_delay"><?php esc_html_e('Minimum submission delay', 'elzo-forms'); ?></label></th>
                <td>
                    <input type="number" class="small-text" name="elzo_forms_form_settings[min_submission_delay]" id="elzo_forms_form_settings_min_submission_delay" value="<?php echo !empty($min_submission_delay) ? esc_attr($min_submission_delay) : ''; ?>" min="0" placeholder="<?php echo empty($elzo_plugin_settings) && !empty($form_global_settings['min_submission_delay']) ? esc_attr($form_global_settings['min_submission_delay']) : '5'; ?>">
                    <p class="description"><?php esc_html_e('Enter the minimum number of seconds that must pass from form load to submission to prevent bot submissions. Set to 0 to disable this check.', 'elzo-forms'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>
    <h3 class="elzo-forms-admin-search-heading"><?php esc_html_e('Other settings', 'elzo-forms'); ?></h3>
    <table class="form-table">
        <tbody class="elzo-forms-admin-search-list">
            <tr>
                <th scope="row"><label for="elzo_forms_form_settings_form_submission_type"><?php esc_html_e('Form submission type', 'elzo-forms'); ?></label></th>
                <td>
                    <select name="elzo_forms_form_settings[form_submission_type]" id="elzo_forms_form_settings_form_submission_type">
                        <?php if(empty($elzo_plugin_settings)){ ?>
                            <option value=""><?php echo esc_html__('Default', 'elzo-forms'); ?> (<?php
                                echo !empty($form_global_settings['form_submission_type'])
                                    ? esc_html(ucfirst($form_global_settings['form_submission_type']))
                                    : esc_html__('AJAX', 'elzo-forms');
                            ?>)</option>
                        <?php } ?>
                        <option value="ajax" <?php selected($form_submission_type, 'ajax'); ?>><?php esc_html_e('AJAX', 'elzo-forms'); ?></option>
                        <option value="standard" <?php selected($form_submission_type, 'standard'); ?>><?php esc_html_e('Standard', 'elzo-forms'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Select the form submission method. AJAX will submit the form without page reload using JavaScript. Default will submit the form without JavaScript with page reload.', 'elzo-forms'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>
</div>