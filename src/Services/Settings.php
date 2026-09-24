<?php
/**
 * Settings service for managing form settings, styles, and texts.
 *
 * @package ElzoForms\Services
 */

namespace ElzoForms\Services;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Settings {

    /**
     * Get form settings (merges global + form-specific settings).
     */
    public static function get_form_settings(array $args = []): ?array {
        $args = wp_parse_args($args, [
            'form_id' => null,
            'form_post' => null
        ]);

        if (!$args['form_id'] && !$args['form_post']) {
            return null;
        }

        $form_post = !empty($args['form_post']) && is_object($args['form_post'])
            ? $args['form_post']
            : get_post($args['form_id']);

        $form = $form_post->post_content ? \ElzoForms\Form\Form_Data_Normalizer::decode_form_json((string) $form_post->post_content) : null;
        $form_settings = !empty($form['settings']) ? $form['settings'] : [];
        $global_settings = get_option('elzo_forms_form_settings', []);
        $global_settings = is_array($global_settings) ? $global_settings : [];

        // Unite global and form blacklists
        foreach (['blocked_ips', 'blocked_words'] as $blacklist_type) {
            if (!empty($global_settings[$blacklist_type]) || !empty($form_settings[$blacklist_type])) {
                $global_list = self::parse_blacklist_value($global_settings[$blacklist_type] ?? '');
                $form_list = self::parse_blacklist_value($form_settings[$blacklist_type] ?? '');
                $merged_list = array_unique(array_merge($global_list, $form_list));
                $form_settings[$blacklist_type] = array_filter($merged_list);
                $global_settings[$blacklist_type] = null;
            }
        }

        $default_settings = self::get_default_settings();
        $form_settings = wp_parse_args(array_filter($form_settings, function($value) {
            return $value !== null && $value !== '';
        }), $global_settings);
        $form_settings = wp_parse_args($form_settings, $default_settings);

        return apply_filters('elzo_forms_form_settings', $form_settings, $form_post);
    }

    /**
     * Get default style values.
     */
    public static function get_default_styles(?string $key = null) {
        $default_styles = [
            'primary_color' => '#0073aa',
            'primary_color_dark' => '#005580',
            'primary_color_hover' => '#1a81b3',
            'primary_color_50' => 'rgba(0, 116, 170, 0.5)',
            'primary_color_25' => 'rgba(0, 116, 170, 0.25)',
            'primary_text_color' => '#ffffff',
            'error_color' => '#dc3545',
            'error_color_light' => '#ffeaec',
            'error_color_50' => 'rgba(220, 53, 70, 0.5)',
            'error_color_25' => 'rgba(220, 53, 70, 0.25)',
            'success_color' => '#0073aa',
            'success_color_light' => '#e0edf4',
            'light_color' => '#f1f1f1',
            'input_border_color' => '#cccccc',
            'input_text_color' => '#000000',
            'input_background_color' => '#ffffff',
            'floating_background_color' => '#ffffff',
            'floating_background_color_hover' => '#eeeeee',
            'floating_text_color' => '#000000',
            'input_border_width' => 1,
            'input_border_width_05' => 1,
            'input_border_radius' => 5,
            'input_border_radius_05' => 2.5,
            'input_font_size' => 16,
        ];

        return $key ? ($default_styles[$key] ?? null) : $default_styles;
    }

    /**
     * Get style settings (merges default + global + form-specific).
     */
    public static function get_style_settings(array $args = []): array {
        $args = wp_parse_args($args, [
            'form_id' => null,
            'form_post' => null
        ]);

        $default_styles = self::get_default_styles();
        $global_styles = get_option('elzo_forms_style_settings', []);
        $global_styles = is_array($global_styles) ? $global_styles : [];

        $global_styles = wp_parse_args(array_filter($global_styles, function($value) {
            return $value !== null && $value !== '';
        }), $default_styles);

        if (!empty($args['form_id']) || !empty($args['form_post'])) {
            $form_post = !empty($args['form_post']) && is_object($args['form_post'])
                ? $args['form_post']
                : get_post($args['form_id']);

            $form = $form_post && $form_post->post_content ? \ElzoForms\Form\Form_Data_Normalizer::decode_form_json((string) $form_post->post_content) : null;
            $form_styles = !empty($form['styles']) ? $form['styles'] : [];

            $form_styles = wp_parse_args(array_filter($form_styles, function($value) {
                return $value !== null && $value !== '';
            }), $global_styles);

            return apply_filters('elzo_forms_form_styles', $form_styles, $form_post);
        }

        return $global_styles;
    }

    /**
     * Get default settings values.
     */
    public static function get_default_settings(?string $key = null) {
        $default_settings = [
            'form_custom_id' => '',
            'form_custom_class' => '',
            'form_redirect_url' => '',
            'form_redirect_delay' => 0,
            'form_field_class' => '',
            'form_field_error_class' => '',
            'form_alert_type' => 'modal',
            'clear_form_after_submission' => 'yes',
            'hide_form_after_submission' => 'no',
            'form_submission_type' => 'ajax',
            'email_notifications' => 'yes',
            'email_notification_recipients' => '',
            'email_notification_reply_to' => '',
            'submission_read_state' => 'yes',
            'blocked_ips' => '',
            'blocked_words' => '',
            'blocked_submission_action' => 'spam',
            'min_submission_interval' => 20,
            'min_submission_delay' => 0,
        ];

        // Allow modules and third-party plugins to extend default settings
        $default_settings = apply_filters('elzo_forms_default_settings', $default_settings);

        return $key ? ($default_settings[$key] ?? null) : $default_settings;
    }

    /**
     * Get default text values.
     */
    public static function get_default_texts(?string $key = null) {
        $default_texts = [
            'submit_button_text' => __('Submit', 'elzo-forms'),
            'next_step_button_text' => __('Next', 'elzo-forms'),
            'previous_step_button_title' => __('Previous', 'elzo-forms'),
            'success_submit_message' => __('Form submitted successfully', 'elzo-forms'),
            'error_submit_message' => __('An error occurred while submitting the form', 'elzo-forms'),
            'required_field_message' => __('This field is required', 'elzo-forms'),
            'email_notification_subject' => __('New form submission', 'elzo-forms'),
            'email_notification_message' => __('A new form submission has been received', 'elzo-forms'),
            'file_upload_text' => __('Drag and drop files here or click to upload', 'elzo-forms'),
            'file_upload_button_text' => __('Select files', 'elzo-forms'),
        ];

        return $key ? ($default_texts[$key] ?? null) : $default_texts;
    }

    /**
     * Get text settings (merges default + global + form-specific).
     */
    public static function get_texts_settings(array $args = []): array {
        $args = wp_parse_args($args, [
            'form_id' => null,
            'form_post' => null
        ]);

        $default_texts = self::get_default_texts();
        $global_texts = get_option('elzo_forms_texts_settings', []);
        $global_texts = is_array($global_texts) ? $global_texts : [];

        $global_texts = wp_parse_args(array_filter($global_texts, function($value) {
            return $value !== null && $value !== '';
        }), $default_texts);

        if (!empty($args['form_id']) || !empty($args['form_post'])) {
            $form_post = !empty($args['form_post']) && is_object($args['form_post'])
                ? $args['form_post']
                : get_post($args['form_id']);

            $form = $form_post && $form_post->post_content ? \ElzoForms\Form\Form_Data_Normalizer::decode_form_json((string) $form_post->post_content) : null;
            $form_texts = !empty($form['texts']) ? $form['texts'] : [];

            $form_texts = wp_parse_args(array_filter($form_texts, function($value) {
                return $value !== null && $value !== '';
            }), $global_texts);

            return apply_filters('elzo_forms_form_texts', $form_texts, $form_post);
        }

        return $global_texts;
    }

    /**
     * Parse blocked list values from textarea-like input into string entries.
     *
     * Supports comma-separated values, real newlines, and escaped newline
     * sequences ("\\n", "\\r", "\\r\\n") that may appear after JSON transport.
     *
     * @param mixed $value Raw option/form setting value.
     * @return array<int, string>
     */
    private static function parse_blacklist_value($value): array {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $value = (string) $value;
            $normalized = str_replace(["\\r\\n", "\\n", "\\r", "\r\n", "\n", "\r"], ',', $value);
            $parts = explode(',', $normalized);
        }

        $parts = array_map('trim', $parts);
        return array_values(array_filter($parts, static function ($item) {
            return $item !== '';
        }));
    }
}
