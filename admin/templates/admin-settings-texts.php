<?php

defined('ABSPATH') || exit;

    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included via admin settings/meta-box callback chains, so variables here are function-scoped, not globals.

    // Texts settings
    $default_texts_settings = !empty($elzo_plugin_settings) ? \ElzoForms\Services\Settings::get_default_texts() : \ElzoForms\Services\Settings::get_texts_settings();
?>
<div class="elzo-forms-admin-search-section">
    <div class="elzo-forms-admin-search">
        <label for="elzo-forms-admin-settings-form-search-input" class="elzo-forms-admin-search-label-icon"><i class="elzo-icon elzo-icon-search"></i></label>
        <input type="search" class="elzo-forms-admin-search-input" id="elzo-forms-admin-settings-form-search-input" placeholder="<?php echo esc_attr__('Search text settings', 'elzo-forms'); ?>">
        <button type="button" class="elzo-forms-admin-search-clear" style="display:none"><i class="elzo-icon elzo-icon-close"></i></button>
    </div>
    <div class="elzo-forms-admin-search-nothing-found" style="display:none">
        <p><?php esc_html_e('No text settings found', 'elzo-forms'); ?></p>
    </div>
    <table class="form-table">
        <tbody class="elzo-forms-admin-search-list">
            <tr>
                <th scope="row"><label for="elzo_forms_texts_settings_submit_button_text"><?php esc_html_e('Submit button text', 'elzo-forms'); ?></label></th>
                <td>
                    <input type="text" class="regular-text" name="elzo_forms_texts_settings[submit_button_text]" id="elzo_forms_texts_settings_submit_button_text" value="<?php echo !empty($texts_settings['submit_button_text']) ? esc_attr($texts_settings['submit_button_text']) : ''; ?>" placeholder="<?php echo esc_attr($default_texts_settings['submit_button_text']); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_texts_settings_next_step_button_text"><?php esc_html_e('Next step button text', 'elzo-forms'); ?></label></th>
                <td>
                    <input type="text" class="regular-text" name="elzo_forms_texts_settings[next_step_button_text]" id="elzo_forms_texts_settings_next_step_button_text" value="<?php echo !empty($texts_settings['next_step_button_text']) ? esc_attr($texts_settings['next_step_button_text']) : ''; ?>" placeholder="<?php echo esc_attr($default_texts_settings['next_step_button_text']); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_texts_settings_success_submit_message"><?php esc_html_e('Success submit message', 'elzo-forms'); ?></label></th>
                <td>
                    <textarea name="elzo_forms_texts_settings[success_submit_message]" class="regular-text" id="elzo_forms_texts_settings_success_submit_message" rows="3" placeholder="<?php echo esc_attr($default_texts_settings['success_submit_message']); ?>"><?php echo !empty($texts_settings['success_submit_message']) ? esc_textarea($texts_settings['success_submit_message']) : ''; ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_texts_settings_error_submit_message"><?php esc_html_e('Error submit message', 'elzo-forms'); ?></label></th>
                <td>
                    <textarea name="elzo_forms_texts_settings[error_submit_message]" class="regular-text" id="elzo_forms_texts_settings_error_submit_message" rows="3" placeholder="<?php echo esc_attr($default_texts_settings['error_submit_message']); ?>"><?php echo !empty($texts_settings['error_submit_message']) ? esc_textarea($texts_settings['error_submit_message']) : ''; ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_texts_settings_required_field_message"><?php esc_html_e('Required field message', 'elzo-forms'); ?></label></th>
                <td>
                    <textarea name="elzo_forms_texts_settings[required_field_message]" class="regular-text" id="elzo_forms_texts_settings_required_field_message" rows="3" placeholder="<?php echo esc_attr($default_texts_settings['required_field_message']); ?>"><?php echo !empty($texts_settings['required_field_message']) ? esc_textarea($texts_settings['required_field_message']) : ''; ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_texts_settings_email_notification_subject"><?php esc_html_e('Email notification subject', 'elzo-forms'); ?></label></th>
                <td>
                    <input data-elzo-variables="notification" type="text" class="regular-text" name="elzo_forms_texts_settings[email_notification_subject]" id="elzo_forms_texts_settings_email_notification_subject" value="<?php echo !empty($texts_settings['email_notification_subject']) ? esc_attr($texts_settings['email_notification_subject']) : ''; ?>" placeholder="<?php echo esc_attr($default_texts_settings['email_notification_subject']); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_texts_settings_email_notification_message"><?php esc_html_e('Email notification message', 'elzo-forms'); ?></label></th>
                <td>
                    <textarea data-elzo-variables="notification" name="elzo_forms_texts_settings[email_notification_message]" class="regular-text" id="elzo_forms_texts_settings_email_notification_message" rows="3" placeholder="<?php echo esc_attr($default_texts_settings['email_notification_message']); ?>"><?php echo !empty($texts_settings['email_notification_message']) ? esc_textarea($texts_settings['email_notification_message']) : ''; ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_texts_settings_file_upload_text"><?php esc_html_e('File upload text', 'elzo-forms'); ?></label></th>
                <td>
                    <input type="text" class="regular-text" name="elzo_forms_texts_settings[file_upload_text]" id="elzo_forms_texts_settings_file_upload_text" value="<?php echo !empty($texts_settings['file_upload_text']) ? esc_attr($texts_settings['file_upload_text']) : ''; ?>" placeholder="<?php echo esc_attr($default_texts_settings['file_upload_text']); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_texts_settings_file_upload_button_text"><?php esc_html_e('File upload button text', 'elzo-forms'); ?></label></th>
                <td>
                    <input type="text" class="regular-text" name="elzo_forms_texts_settings[file_upload_button_text]" id="elzo_forms_texts_settings_file_upload_button_text" value="<?php echo !empty($texts_settings['file_upload_button_text']) ? esc_attr($texts_settings['file_upload_button_text']) : ''; ?>" placeholder="<?php echo esc_attr($default_texts_settings['file_upload_button_text']); ?>">
                </td>
            </tr>
        </tbody>
    </table>
</div>