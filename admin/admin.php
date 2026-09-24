<?php
/**
 * Admin functionality.
 *
 * Handles WordPress admin interface for forms, submissions, and settings.
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Add settings pages for the plugin
function elzo_forms_admin_menu() {
    // Single Settings submenu item with tab-based navigation
    add_submenu_page(
        'edit.php?post_type=elzo_form',
        __('Settings', 'elzo-forms'),
        __('Settings', 'elzo-forms'),
        'manage_options',
        'elzo-forms-settings',
        'elzo_forms_settings_page'
    );
}
add_action('admin_menu', 'elzo_forms_admin_menu');

function elzo_forms_get_admin_query_slug($key, $default = '') {
    if (!is_string($key) || $key === '') {
        return $default;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin query access for routing/UI context.
    if (!isset($_GET[$key])) {
        return $default;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only admin query access; value sanitized via sanitize_key() below.
    $value = wp_unslash($_GET[$key]);

    if (!is_string($value) || $value === '') {
        return $default;
    }

    return sanitize_key($value);
}

function elzo_forms_get_admin_query_text($key, $default = '') {
    if (!is_string($key) || $key === '') {
        return $default;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin query access for routing/UI context.
    if (!isset($_GET[$key])) {
        return $default;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only admin query access; value sanitized via sanitize_text_field() below.
    $value = wp_unslash($_GET[$key]);

    if (!is_string($value) || $value === '') {
        return $default;
    }

    return sanitize_text_field($value);
}

function elzo_forms_get_admin_query_absint($key, $default = 0) {
    if (!is_string($key) || $key === '') {
        return $default;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin query access for routing/UI context.
    if (!isset($_GET[$key])) {
        return $default;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only admin query access; value validated with is_numeric() and normalized via absint() below.
    $value = wp_unslash($_GET[$key]);

    if (!is_string($value) || $value === '' || !is_numeric($value)) {
        return $default;
    }

    return absint($value);
}
function elzo_forms_installed_version(): string {
    if (function_exists('elzo_forms_version')) {
        return (string) elzo_forms_version();
    }

    return defined('ELZO_FORMS_VERSION') ? (string) ELZO_FORMS_VERSION : '0.0.0';
}

function elzo_forms_current_editor_form_post_id(): int {
    global $post;

    if ($post instanceof \WP_Post && $post->post_type === 'elzo_form') {
        return (int) $post->ID;
    }

    return elzo_forms_get_admin_query_absint('post');
}

function elzo_forms_inspect_form_compatibility(array $payload): \ElzoForms\Form\Compatibility\FormCompatibilityResult {
    $inspector = new \ElzoForms\Form\Compatibility\FormCompatibilityInspector();

    return $inspector->inspect($payload, elzo_forms_installed_version());
}

function elzo_forms_notify_compatibility_warnings(\ElzoForms\Form\Compatibility\FormCompatibilityResult $result, int $form_id = 0): void {
    if (!$result->has_warnings()) {
        return;
    }

    foreach ($result->get_warnings() as $warning) {
        try {
            do_action(
                'elzo_forms_form_compatibility_warning',
                $warning,
                $result,
                $form_id
            );
        } catch (\Throwable $throwable) {
            // Diagnostic hooks must never block editor, import, Test Runner, or runtime.
            unset($throwable);
        }
    }
}

function elzo_forms_render_form_compatibility_notice(): void {
    $post_id = elzo_forms_current_editor_form_post_id();
    if ($post_id <= 0) {
        return;
    }

    $post = get_post($post_id);
    if (!$post instanceof \WP_Post || $post->post_type !== 'elzo_form') {
        return;
    }

    $payload = \ElzoForms\Form\Form_Data_Normalizer::decode_form_json((string) $post->post_content);
    if (!is_array($payload)) {
        return;
    }

    $result = elzo_forms_inspect_form_compatibility($payload);
    if (!$result->has_warnings()) {
        return;
    }

    elzo_forms_notify_compatibility_warnings($result, $post_id);
    ?>
    <div class="notice notice-warning">
        <?php foreach ($result->get_warnings() as $warning) : ?>
            <p><?php echo esc_html((string) ($warning['message'] ?? '')); ?></p>
        <?php endforeach; ?>
    </div>
    <?php
}
add_action('admin_notices', 'elzo_forms_render_form_compatibility_notice');

// Fix page title based on current tab
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The filter passes both title arguments.
function elzo_forms_fix_settings_page_title($admin_title, $title) {
    $screen = get_current_screen();
    $post_type = elzo_forms_get_admin_query_slug('post_type');
    $page = elzo_forms_get_admin_query_slug('page');
    $current_tab = elzo_forms_get_admin_query_slug('tab', 'form');

    if (!$screen || $post_type !== 'elzo_form') {
        return $admin_title;
    }

    // Check if current page is our settings page
    if ($page !== 'elzo-forms-settings') {
        return $admin_title;
    }

    // Map of tabs to titles
    $tab_titles = [
        'form' => __('Form Settings', 'elzo-forms'),
        'texts' => __('Text Settings', 'elzo-forms'),
        'styles' => __('Style Settings', 'elzo-forms'),
        'automations' => __('Automations', 'elzo-forms'),
        'modules' => __('Modules', 'elzo-forms'),
    ];

    // Return tab-specific title if available
    if (isset($tab_titles[$current_tab])) {
        // Remove "Settings" from the original title and prepend tab title
        $admin_title = str_replace(__('Settings', 'elzo-forms'), '', $admin_title);
        return $tab_titles[$current_tab] . $admin_title;
    }

    return $admin_title;
}
add_filter('admin_title', 'elzo_forms_fix_settings_page_title', 10, 2);

// Register the plugin's settings
function elzo_forms_sanitize_css_color_value($value) {
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value)) {
        return strtolower($value);
    }

    if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(0|0?\.\d+|1(?:\.0+)?))?\s*\)$/', $value, $matches)) {
        $r = (int) $matches[1];
        $g = (int) $matches[2];
        $b = (int) $matches[3];

        if ($r > 255 || $g > 255 || $b > 255) {
            return '';
        }

        return $value;
    }

    return '';
}

function elzo_forms_sanitize_email_recipients($value) {
    // Keep invalid input visible so validation can report it instead of dropping it.
    return \ElzoForms\Variables\Recipient_Templates::normalize((string) $value);
}

function elzo_forms_notification_option_draft_key(string $option): string {
    return 'elzo_forms_variables_option_' . get_current_user_id() . '_' . $option;
}

function elzo_forms_get_notification_option_for_editor(string $option): array {
    $draft = get_transient(elzo_forms_notification_option_draft_key($option));
    $value = is_array($draft) ? $draft : get_option($option, []);
    return is_array($value) ? $value : [];
}

function elzo_forms_validate_notification_option($value, string $option) {
    if (!is_array($value)) {
        return $value;
    }
    $errors = \ElzoForms\Variables\Notification_Validation::validate($value);
    $draft_key = elzo_forms_notification_option_draft_key($option);
    if (!$errors) {
        delete_transient($draft_key);
        return $value;
    }
    set_transient($draft_key, $value, DAY_IN_SECONDS);
    add_settings_error($option, 'notification_variables_invalid', __('These notification changes were not applied. Correct the variable errors below and save again; the proposed values remain in the editor.', 'elzo-forms'));
    foreach ($errors as $index => $error) {
        add_settings_error($option, 'notification_variable_' . $index, $error['message']);
    }
    return get_option($option, []);
}

function elzo_forms_notification_form_draft_key(int $post_id): string {
    return 'elzo_forms_variables_form_' . get_current_user_id() . '_' . $post_id;
}

function elzo_forms_render_notification_variables_notice(): void {
    $post_id = elzo_forms_get_admin_query_absint('post');
    if (!$post_id || get_post_type($post_id) !== 'elzo_form' || !current_user_can('edit_post', $post_id)) {
        return;
    }
    $draft = get_transient(elzo_forms_notification_form_draft_key($post_id));
    if (!is_array($draft) || empty($draft['errors'])) {
        return;
    }
    echo '<div class="notice notice-error"><p>' . esc_html__('The form data was not saved because notification variables are invalid. The proposed changes remain in the editor; correct the errors and save again.', 'elzo-forms') . '</p><ul>';
    foreach ($draft['errors'] as $error) {
        echo '<li>' . esc_html($error['message']) . '</li>';
    }
    echo '</ul></div>';
}
add_action('admin_notices', 'elzo_forms_render_notification_variables_notice');

function elzo_forms_notification_variables_redirect(string $location, int $post_id = 0): string {
    if (get_transient(elzo_forms_notification_form_draft_key($post_id))) {
        return remove_query_arg('message', $location);
    }
    return $location;
}
add_filter('redirect_post_location', 'elzo_forms_notification_variables_redirect', 10, 2);

function elzo_forms_get_settings_sanitization_rules($option_name, $value = null) {
    $rules = [];

    if ($option_name === 'elzo_forms_form_settings') {
        $rules = [
            'form_custom_id' => 'text',
            'form_custom_class' => 'text',
            'form_redirect_url' => 'url',
            'form_redirect_delay' => 'absint',
            'form_field_class' => 'text',
            'form_field_error_class' => 'text',
            'form_alert_type' => [
                'type' => 'enum',
                'allowed' => array_keys((array) \ElzoForms\Utilities\Admin::get_alert_types()),
                'default' => \ElzoForms\Services\Settings::get_default_settings('form_alert_type'),
            ],
            'clear_form_after_submission' => [
                'type' => 'enum',
                'allowed' => ['yes', 'no'],
                'default' => \ElzoForms\Services\Settings::get_default_settings('clear_form_after_submission'),
            ],
            'hide_form_after_submission' => [
                'type' => 'enum',
                'allowed' => ['yes', 'no'],
                'default' => \ElzoForms\Services\Settings::get_default_settings('hide_form_after_submission'),
            ],
            'form_submission_type' => [
                'type' => 'enum',
                'allowed' => ['ajax', 'standard'],
                'default' => \ElzoForms\Services\Settings::get_default_settings('form_submission_type'),
            ],
            'email_notifications' => [
                'type' => 'enum',
                'allowed' => ['yes', 'no'],
                'default' => \ElzoForms\Services\Settings::get_default_settings('email_notifications'),
            ],
            'email_notification_recipients' => 'elzo_forms_sanitize_email_recipients',
            'email_notification_reply_to' => 'elzo_forms_sanitize_email_recipients',
            'submission_read_state' => [
                'type' => 'enum',
                'allowed' => ['yes', 'no'],
                'default' => \ElzoForms\Services\Settings::get_default_settings('submission_read_state'),
            ],
            'blocked_ips' => 'textarea',
            'blocked_words' => 'textarea',
            'blocked_submission_action' => [
                'type' => 'enum',
                'allowed' => ['remove', 'spam'],
                'default' => \ElzoForms\Services\Settings::get_default_settings('blocked_submission_action'),
            ],
            'min_submission_interval' => 'absint',
            'min_submission_delay' => 'absint',
        ];
    } elseif ($option_name === 'elzo_forms_texts_settings') {
        $rules = [
            'submit_button_text' => 'textarea',
            'next_step_button_text' => 'textarea',
            'previous_step_button_title' => 'textarea',
            'success_submit_message' => 'textarea',
            'error_submit_message' => 'textarea',
            'required_field_message' => 'textarea',
            'email_notification_subject' => 'textarea',
            'email_notification_message' => 'textarea',
            'file_upload_text' => 'textarea',
            'file_upload_button_text' => 'textarea',
        ];
    } elseif ($option_name === 'elzo_forms_style_settings') {
        $rules = [
            'disable_theme_styles' => 'bool',
            'primary_color' => 'css_color',
            'primary_color_dark' => 'css_color',
            'primary_color_hover' => 'css_color',
            'primary_color_50' => 'css_color',
            'primary_color_25' => 'css_color',
            'primary_text_color' => 'css_color',
            'error_color' => 'css_color',
            'error_color_light' => 'css_color',
            'error_color_50' => 'css_color',
            'error_color_25' => 'css_color',
            'success_color' => 'css_color',
            'success_color_light' => 'css_color',
            'light_color' => 'css_color',
            'light_color_hex' => 'css_color',
            'light_color_opacity' => [
                'type' => 'int_range',
                'min' => 0,
                'max' => 100,
                'default' => 100,
            ],
            'input_border_color' => 'css_color',
            'input_border_color_hex' => 'css_color',
            'input_border_color_opacity' => [
                'type' => 'int_range',
                'min' => 0,
                'max' => 100,
                'default' => 100,
            ],
            'input_text_color' => 'css_color',
            'input_text_color_hex' => 'css_color',
            'input_text_color_opacity' => [
                'type' => 'int_range',
                'min' => 0,
                'max' => 100,
                'default' => 100,
            ],
            'input_background_color' => 'css_color',
            'input_background_color_hex' => 'css_color',
            'input_background_color_opacity' => [
                'type' => 'int_range',
                'min' => 0,
                'max' => 100,
                'default' => 100,
            ],
            'floating_background_color' => 'css_color',
            'floating_background_color_hover' => 'css_color',
            'floating_text_color' => 'css_color',
            'input_style' => [
                'type' => 'enum',
                'allowed' => ['default', 'without-border', 'border-bottom'],
                'default' => 'default',
            ],
            'input_border_width' => 'absint',
            'input_border_width_05' => 'float',
            'input_border_radius' => 'absint',
            'input_border_radius_05' => 'float',
            'input_font_size' => 'absint',
        ];
    }

    $rules = apply_filters('elzo_forms_settings_sanitization_rules', $rules, $option_name, $value);
    return apply_filters('elzo_forms_settings_sanitization_rules_' . $option_name, $rules, $value);
}

function elzo_forms_sanitize_setting_value_by_rule($value, $rule, $option_name, $field_key) {
    if (is_callable($rule)) {
        return call_user_func($rule, $value, $option_name, $field_key);
    }

    if (is_array($rule) && !empty($rule['callback']) && is_callable($rule['callback'])) {
        return call_user_func($rule['callback'], $value, $option_name, $field_key, $rule);
    }

    $type = is_array($rule) ? ($rule['type'] ?? 'text') : $rule;

    switch ($type) {
        case 'textarea':
            return sanitize_textarea_field((string) $value);

        case 'url':
            return esc_url_raw((string) $value);

        case 'absint':
            return absint($value);

        case 'int_range':
            $number = absint($value);
            $min = is_array($rule) && isset($rule['min']) ? absint($rule['min']) : 0;
            $max = is_array($rule) && isset($rule['max']) ? absint($rule['max']) : $number;

            if ($number < $min) {
                $number = $min;
            }
            if ($number > $max) {
                $number = $max;
            }

            return $number;

        case 'float':
            return floatval($value);

        case 'bool':
            return !empty($value);

        case 'enum':
            $allowed = is_array($rule) && isset($rule['allowed']) && is_array($rule['allowed'])
                ? $rule['allowed']
                : [];
            $candidate = sanitize_text_field((string) $value);

            if ($candidate !== '' && in_array($candidate, $allowed, true)) {
                return $candidate;
            }

            if (is_array($rule) && array_key_exists('default', $rule)) {
                return $rule['default'];
            }

            return '';

        case 'css_color':
            return elzo_forms_sanitize_css_color_value($value);

        case 'text':
        default:
            return sanitize_text_field((string) $value);
    }
}

function elzo_forms_sanitize_settings_option($value, $option_name) {
    $rules = elzo_forms_get_settings_sanitization_rules($option_name, $value);

    if (!is_array($value)) {
        $default_rule = apply_filters('elzo_forms_settings_default_sanitization_rule', 'text', $option_name, '', $value);
        return elzo_forms_sanitize_setting_value_by_rule($value, $default_rule, $option_name, '');
    }

    $sanitized = [];
    foreach ($value as $field_key => $field_value) {
        $field_key = sanitize_key($field_key);

        if (is_array($field_value)) {
            $sanitized[$field_key] = map_deep($field_value, 'sanitize_text_field');
            continue;
        }

        $rule = $rules[$field_key] ?? apply_filters('elzo_forms_settings_default_sanitization_rule', 'text', $option_name, $field_key, $field_value);
        $sanitized[$field_key] = elzo_forms_sanitize_setting_value_by_rule($field_value, $rule, $option_name, $field_key);
    }

    return $sanitized;
}

function elzo_forms_sanitize_general_settings($value) {
    return elzo_forms_sanitize_settings_option($value, 'elzo_forms_settings');
}

function elzo_forms_sanitize_form_settings($value) {
    return elzo_forms_validate_notification_option(elzo_forms_sanitize_settings_option($value, 'elzo_forms_form_settings'), 'elzo_forms_form_settings');
}

function elzo_forms_sanitize_texts_settings($value) {
    return elzo_forms_validate_notification_option(elzo_forms_sanitize_settings_option($value, 'elzo_forms_texts_settings'), 'elzo_forms_texts_settings');
}

function elzo_forms_sanitize_style_settings($value) {
    $rules = elzo_forms_get_settings_sanitization_rules('elzo_forms_style_settings', $value);
    $value = elzo_forms_sanitize_settings_option($value, 'elzo_forms_style_settings');

    if (!is_array($value)) {
        return $value;
    }

    return array_intersect_key($value, $rules);
}

function elzo_forms_register_settings() {
    // General settings
    register_setting(
        'elzo_forms_general_settings_group',
        'elzo_forms_settings',
        [
            'sanitize_callback' => 'elzo_forms_sanitize_general_settings',
        ]
    );

    // Form settings
    register_setting(
        'elzo_forms_form_settings_group',
        'elzo_forms_form_settings',
        [
            'sanitize_callback' => 'elzo_forms_sanitize_form_settings',
        ]
    );

    // Texts settings
    register_setting(
        'elzo_forms_texts_settings_group',
        'elzo_forms_texts_settings',
        [
            'sanitize_callback' => 'elzo_forms_sanitize_texts_settings',
        ]
    );

    // Styles settings
    register_setting(
        'elzo_forms_styles_settings_group',
        'elzo_forms_style_settings',
        [
            'sanitize_callback' => 'elzo_forms_sanitize_style_settings',
        ]
    );
}
add_action('admin_init', 'elzo_forms_register_settings');

// Handle the update of 'elzo_forms_style_settings'
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The option hook passes both values.
function elzo_forms_style_settings_updated($old_value, $new_value) {
    // Update and compile the plugin styles
    elzo_forms_update_and_compile_styles();
}
add_action('update_option_elzo_forms_style_settings', 'elzo_forms_style_settings_updated', 10, 2);

// Handle the initial add of 'elzo_forms_style_settings'
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The option hook passes both values.
function elzo_forms_style_settings_added($option, $value) {
    elzo_forms_update_and_compile_styles();
}
add_action('add_option_elzo_forms_style_settings', 'elzo_forms_style_settings_added', 10, 2);

// Compile styles on plugin update
function elzo_forms_update_compile_styles($upgrader_object, $options) {
    $current_plugin_path = plugin_basename(plugin_dir_path(__DIR__) . 'elzo-forms.php');
    $action = isset($options['action']) ? $options['action'] : '';
    $type = isset($options['type']) ? $options['type'] : '';
    $plugins = (isset($options['plugins']) && is_array($options['plugins'])) ? $options['plugins'] : [];

    if ($action !== 'update' || $type !== 'plugin' || empty($plugins)) {
        return;
    }

    foreach ($plugins as $plugin) {
        if ($plugin === $current_plugin_path) {
            elzo_forms_update_and_compile_styles();
            break;
        }
    }
}
add_action('upgrader_process_complete', 'elzo_forms_update_compile_styles', 10, 2);

// Render the plugin's submenu page
function elzo_forms_settings_page() {
    // add_submenu_page() already gates this callback, but the included templates
    // process POST, so authorize the whole render path explicitly.
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'elzo-forms'));
    }

    // Get current tab from URL parameter, default to 'form'
    $current_tab = elzo_forms_get_admin_query_slug('tab', 'form');

    // Allowed tabs
    $allowed_tabs = ['form', 'texts', 'styles', 'automations', 'modules'];

    // Validate tab
    if (!in_array($current_tab, $allowed_tabs, true)) {
        $current_tab = 'form';
    }

    // Tab titles
    $tab_titles = [
        'form' => __('Form Settings', 'elzo-forms'),
        'texts' => __('Text Settings', 'elzo-forms'),
        'styles' => __('Style Settings', 'elzo-forms'),
        'automations' => __('Automations', 'elzo-forms'),
        'modules' => __('Modules', 'elzo-forms'),
    ];

    $title = $tab_titles[$current_tab] ?? __('Settings', 'elzo-forms');

    include plugin_dir_path(__FILE__) . 'templates/admin-settings.php';
}

// Register the plugin's admin scripts and styles
function elzo_forms_admin_enqueue_scripts() {
    $screen = get_current_screen();
    $is_form_editor = $screen && $screen->post_type === 'elzo_form' && $screen->base === 'post';
    $is_variables_settings = $screen && $screen->id === 'elzo_form_page_elzo-forms-settings'
        && in_array(elzo_forms_get_admin_query_slug('tab', 'form'), ['form', 'texts'], true);
    $load_editor = $is_form_editor || $is_variables_settings;
    if ($load_editor) {
        wp_enqueue_editor();
    }

    // Enqueue the WordPress drag-and-drop script
    wp_enqueue_script('jquery-ui-sortable');

    // Enqueue the WordPress color picker script
    wp_enqueue_script('wp-color-picker');

    $is_submission_screen = $screen
        && $screen->post_type === 'elzo_submission'
        && in_array($screen->base, array('post', 'edit'), true);
    $submission_spam_status = array(
        'isSubmissionScreen' => $is_submission_screen,
        'spamLabel' => __('Spam', 'elzo-forms'),
        'currentPostStatus' => '',
    );

    if ($screen && $screen->post_type === 'elzo_submission' && $screen->base === 'post') {
        $post_id = elzo_forms_get_admin_query_absint('post');
        $current_post = $post_id > 0 ? get_post($post_id) : null;

        if ($current_post && $current_post->post_type === 'elzo_submission') {
            $submission_spam_status['currentPostStatus'] = sanitize_key($current_post->post_status);
        }
    }

    // Enqueue the plugin's admin scripts and styles
    wp_enqueue_style('elzo-forms-admin', plugin_dir_url(__DIR__) . 'assets/css/elzo-forms-admin.css', array(), defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? (string) filemtime(dirname(__DIR__) . '/assets/css/elzo-forms-admin.css') : ELZO_FORMS_VERSION, 'all');
    wp_enqueue_style('elzo-forms-admin-icons', plugin_dir_url(__DIR__) . 'assets/icons/elzo-forms-icons.css', array(), ELZO_FORMS_VERSION, 'all');
    wp_enqueue_script('elzo-forms-admin', plugin_dir_url(__DIR__) . 'assets/js/elzo-forms-admin.js', $load_editor ? ['wp-tinymce'] : [], defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? (string) filemtime(dirname(__DIR__) . '/assets/js/elzo-forms-admin.js') : ELZO_FORMS_VERSION, true);

    if ($load_editor) {
        wp_enqueue_style('elzo-forms-variables', plugin_dir_url(__DIR__) . 'assets/css/elzo-forms-variables.css', array('elzo-forms-admin'), defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? (string) filemtime(dirname(__DIR__) . '/assets/css/elzo-forms-variables.css') : ELZO_FORMS_VERSION);
        wp_enqueue_script('elzo-forms-variables', plugin_dir_url(__DIR__) . 'assets/js/elzo-forms-variables.js', array('elzo-forms-admin', 'wp-tinymce'), defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? (string) filemtime(dirname(__DIR__) . '/assets/js/elzo-forms-variables.js') : ELZO_FORMS_VERSION, true);
        wp_localize_script('elzo-forms-variables', 'ElzoFormsVariablesConfig', array(
            'items' => \ElzoForms\Variables\Catalog::items(),
            'texts' => array(
                'variables' => __('Variables', 'elzo-forms'),
                'template' => __('Template', 'elzo-forms'),
                'editTemplate' => __('Edit as text', 'elzo-forms'),
                'visualEditor' => __('Visual editor', 'elzo-forms'),
                'close' => __('Close', 'elzo-forms'),
                'search' => __('Search variables', 'elzo-forms'),
                'noVariables' => __('No variables found.', 'elzo-forms'),
                'loading' => __('Loading variables...', 'elzo-forms'),
                'loadFailed' => __('Variables could not be loaded. Close the picker and try again.', 'elzo-forms'),
                'customPath' => __('Enter a variable path', 'elzo-forms'),
                'variablePath' => __('Variable path', 'elzo-forms'),
                'insert' => __('Insert', 'elzo-forms'),
                'fields' => __('Fields', 'elzo-forms'),
            ),
        ));
    }
    // Pass nonce and other data to admin script
    wp_localize_script('elzo-forms-admin', 'ElzoFormsAdmin', array(
        'nonce' => wp_create_nonce('elzo_forms_admin'),
        'ajaxurl' => admin_url('admin-ajax.php'),
        /**
         * Maximum characters to display for field names/labels in conditional logic UI.
         * Full text is preserved in title attribute for hover tooltip.
         *
         * @filter elzo_forms_logic_label_max_length
         * @type int Default: 50 characters
         */
        'logicLabelMaxLength' => apply_filters('elzo_forms_logic_label_max_length', 50),
        'selectFieldPlaceholder' => __('Select a field', 'elzo-forms'),
        'logicValueUnavailable' => __('Not available for this field type', 'elzo-forms'),
        'logicOperatorUnavailable' => __('Not available', 'elzo-forms'),
        // Count operators compare a number of values, so their value input is
        // a number rather than the free text every other operator takes.
        'logicCountOperators' => \ElzoForms\Utilities\Conditional_Logic::get_count_operators(),
        'contentPostTypes' => \ElzoForms\Utilities\Admin::get_content_post_type_options(),
        'contentPicker' => array(
            'searchPlaceholder' => __('Search...', 'elzo-forms'),
            'searchHint' => __('Type at least 2 characters to search.', 'elzo-forms'),
            'loading' => __('Searching...', 'elzo-forms'),
            'noResults' => __('Nothing found.', 'elzo-forms'),
            'failed' => __('Search failed.', 'elzo-forms'),
            /* translators: %d: Post ID of content that no longer exists or is not readable. */
            'unavailable' => __('#%d (unavailable)', 'elzo-forms'),
            'remove' => __('Remove', 'elzo-forms'),
            'clear' => __('Clear selection', 'elzo-forms'),
            'emptySingle' => __('Nothing selected', 'elzo-forms'),
            'selectPostType' => __('Select a post type', 'elzo-forms'),
            /* translators: %s: Post type slug that is no longer registered. */
            'postTypeUnavailable' => __('%s (unavailable)', 'elzo-forms'),
        ),
        'submissionSpamStatus' => $submission_spam_status,
        'texts' => array(
            'removeFieldConfirmation' => __('Are you sure you want to remove this field?', 'elzo-forms'),
            'removeStepConfirmation' => __('Are you sure you want to remove this step?', 'elzo-forms'),
            'nothingFound' => __('Nothing found', 'elzo-forms'),
        ),
    ));
}
add_action('admin_enqueue_scripts', 'elzo_forms_admin_enqueue_scripts');

// Create a custom meta box for the plugin
function elzo_forms_meta_box() {
    // Form Data meta box
    add_meta_box(
        'elzo-forms-data-meta-box',
        __('Form Data', 'elzo-forms'),
        'elzo_forms_data_meta_box_callback',
        'elzo_form',
        'normal',
        'high'
    );

    // Submission Data meta box
    add_meta_box(
        'elzo-forms-submission-data-meta-box',
        __('Submission Data', 'elzo-forms'),
        'elzo_submission_data_meta_box_callback',
        'elzo_submission',
        'normal',
        'high'
    );

    // Submission Author meta box
    add_meta_box(
        'elzo-forms-submitter-info-meta-box',
        __('Submitter Info', 'elzo-forms'),
        'elzo_submitter_info_meta_box_callback',
        'elzo_submission',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'elzo_forms_meta_box');

// Render the Form Data meta box
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress passes the post to meta box callbacks.
function elzo_forms_data_meta_box_callback($post) {
    include plugin_dir_path(__DIR__) . 'admin/templates/admin-form-meta-box-content.php';
}

// Render the Submission Data meta box
function elzo_submission_data_meta_box_callback($post) {
    // Initialize $submission_id variable
    $submission_id = $post->ID;

    // Get the submission data
    $submission = new \ElzoForms\Submission\Submission($post);
    $submission_content = $submission->get_raw_content();
    $submission_data = $submission->to_array();
    $fields = $submission->get_fields();

    $form_id = $submission->get_form_id();
    $form_title = $form_id ? (is_numeric($form_id) ? get_the_title($form_id) : apply_filters('elzo_forms_custom_form_label', $form_id)) : '';
    $form_edit_url = $form_id ? (is_numeric($form_id) ? get_edit_post_link($form_id) : apply_filters('elzo_forms_custom_form_edit_url', $form_id)) : '';

    // Loop through the fields and apply filters
    foreach ($fields as $field_index => $field) {
        // Apply filter to each field
        $fields[$field_index] = apply_filters('elzo_forms_admin_submission_field', $fields[$field_index]);
    }

    $field_presenter = new \ElzoForms\Submission\Submission_Field_Presenter([
        'submission_id' => $submission_id,
        'form_id' => $form_id,
    ]);

    // Include the meta box content template
    include plugin_dir_path(__DIR__) . 'admin/templates/admin-submission-data-meta-box-content.php';
}

// Render the Submitter Info meta box
function elzo_submitter_info_meta_box_callback($post) {
    // Get submission object
    $submission = new \ElzoForms\Submission\Submission($post);
    $user = $submission->get_user_data();
    $user['user_object'] = !empty($user['user_id']) ? get_user_by('id', intval($user['user_id'])) : null;

    // Include the meta box content template
    include plugin_dir_path(__DIR__) . 'admin/templates/admin-submitter-info-meta-box-content.php';
}
// Save submission meta box data
function elzo_forms_save_submission_meta_box_data($post_id) {
    // Check if our nonce is set.
    if (!isset($_POST['elzo_forms_submission_meta_box_nonce']) || !is_scalar($_POST['elzo_forms_submission_meta_box_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash((string) $_POST['elzo_forms_submission_meta_box_nonce']));

    // Verify the nonce.
    if (!wp_verify_nonce($nonce, 'elzo_forms_submission_meta_box')) {
        return;
    }

    // Check if the user has permission to save data.
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Check if the post type is correct
    if (get_post_type($post_id) !== 'elzo_submission') {
        return;
    }

    // Prevent infinite loop - check if this is an autosave or revision
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    // Static variable to prevent infinite recursion
    static $is_saving = false;
    if ($is_saving) {
        return;
    }
    $is_saving = true;

    // Get the current submission object
    $submission = new \ElzoForms\Submission\Submission($post_id);

    if (!$submission->get_id()) {
        $is_saving = false;
        return;
    }

    // Get current fields
    $fields = $submission->get_fields();

    $posted_fields = [];
    if (isset($_POST['fields']) && is_array($_POST['fields'])) {
        $posted_fields = wp_unslash($_POST['fields']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are sanitized field-by-field in the loop below; nonce verified at the top of this function.
    }

    // Process updated field values
    if (!empty($posted_fields)) {
        foreach ($posted_fields as $field_index => $field_value) {
            if(is_array($field_value)) {
                // If the field value is an array, sanitize each value
                $sanitized_value = array_filter(array_map('sanitize_textarea_field', $field_value));
            } else {
                // Sanitize the field value
                $sanitized_value = sanitize_textarea_field($field_value);
            }

            // Update the field value in the submission data
            if (isset($fields[$field_index])) {
                $fields[$field_index]['value'] = $sanitized_value;
            }
        }

        // Update submission with new fields
        $submission->set_fields($fields);

        // Remove the hook temporarily to prevent infinite loop
        remove_action('save_post', 'elzo_forms_save_submission_meta_box_data');

        // Save submission
        $submission->save();

        // Re-add the hook
        add_action('save_post', 'elzo_forms_save_submission_meta_box_data');
    }

    // Reset the static variable
    $is_saving = false;
}
add_action('save_post', 'elzo_forms_save_submission_meta_box_data');

/**
 * Resolve a style value before replacing its CSS custom property.
 *
 * Compact UI elements use values derived from the primary border settings.
 * Resolve them on the server so stale hidden admin fields cannot make the
 * compiled stylesheet disagree with the visible setting.
 *
 * @param string $key                    Style key being compiled.
 * @param mixed  $default_value          Default value for the style key.
 * @param array  $style_settings         Saved style settings.
 * @param array  $default_style_settings Default style settings.
 * @return mixed
 */
function elzo_forms_resolve_compiled_style_value($key, $default_value, array $style_settings, array $default_style_settings) {
    if ($key === 'input_border_width_05') {
        $base_width = isset($style_settings['input_border_width']) && $style_settings['input_border_width'] !== ''
            ? absint($style_settings['input_border_width'])
            : absint($default_style_settings['input_border_width']);

        return max(1, min(3, (int) round($base_width / 2)));
    }

    if ($key === 'input_border_radius_05') {
        $base_radius = isset($style_settings['input_border_radius']) && $style_settings['input_border_radius'] !== ''
            ? (float) $style_settings['input_border_radius']
            : (float) $default_style_settings['input_border_radius'];

        return max(0, $base_radius / 2);
    }

    if (isset($style_settings[$key]) && $style_settings[$key] !== null && $style_settings[$key] !== '') {
        return $style_settings[$key];
    }

    return $default_value;
}

// Update and compile the plugin styles
function elzo_forms_update_and_compile_styles() {
    // Get default settings
    $default_style_settings = \ElzoForms\Services\Settings::get_default_styles();

    // Get current settings
    $style_settings = get_option('elzo_forms_style_settings', []);

    // Path to the source CSS file
    $source_css_file_path = plugin_dir_path(__DIR__) . 'assets/css/elzo-forms.css';

    if (!class_exists('\WP_Filesystem_Base', false)) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
    }

    if (!class_exists('\WP_Filesystem_Direct', false)) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
    }

    $filesystem = new \WP_Filesystem_Direct(false);

    // Read the CSS file content
    $css_content = $filesystem->get_contents($source_css_file_path);

    if ($css_content === false) {
        return;
    }

    // Initialize minified CSS content
    $minified_css_content = $css_content;

    // Initialize CSS variables array
    $css_variables = [];

    // Iterate through the style settings
    foreach ($default_style_settings as $key => $value) {
        $value = elzo_forms_resolve_compiled_style_value($key, $value, $style_settings, $default_style_settings);

        // Prepare the CSS variable key and value
        $css_variable_key = '--elzo-forms-' . str_replace('_', '-', $key);
        $css_variable_value = is_numeric($value) ? $value . 'px' : $value;

        // Add the CSS variable to the array
        $css_variables[] = "\t" . $css_variable_key . ": " . $css_variable_value . ";";

        // Replace the usage of the CSS variable in the minimized CSS content
        $minified_css_content = preg_replace('/var\(' . preg_quote($css_variable_key, '/') . '\)/', $css_variable_value, $minified_css_content);
    }

    // Remove everything between '/* Elzo Forms CSS variables start */' and '/* Elzo Forms CSS variables end */' in the minified content
    $minified_css_content = preg_replace('/\/\* Elzo Forms CSS variables start \*\/.*?\/\* Elzo Forms CSS variables end \*\//s', '', $minified_css_content);

    // Remove theme styles if needed (everything between '/* Elzo Forms theme start */' and '/* Elzo Forms theme end */')
    if(!empty($style_settings['disable_theme_styles'])){
        $minified_css_content = preg_replace('/\/\* Elzo Forms theme start \*\/.*?\/\* Elzo Forms theme end \*\//s', '', $minified_css_content);
    }

    // Minify the CSS content
    $minified_css_content = preg_replace(
        [
            '/\/\*[^*]*\*+([^\/][^*]*\*+)*\//', // Remove comments
            '/\s*([{}|:;,])\s*/',               // Remove whitespace around separators
            '/\s\s+(?![^{}]*\})/',              // Remove extra whitespace
            '/;}/'                              // Remove semicolon before closing brace
        ],
        [
            '',
            '$1',
            ' ',
            '}'
        ],
        $minified_css_content
    );

    // Get the uploads directory
    $upload_dir = wp_upload_dir();
    $elzo_uploads_path = $upload_dir['basedir'] . '/elzo-forms/assets';

    // Create the directory if it doesn't exist
    if (!is_dir($elzo_uploads_path)) {
        wp_mkdir_p($elzo_uploads_path);
    }

    // Generate a timestamp for the file
    $version_timestamp = time();

    // Prepare the path to the new timestamped minified CSS file
    $timestamped_css_file_path = $elzo_uploads_path . '/elzo-forms-' . $version_timestamp . '.min.css';

    // Save the minified CSS content to the timestamped file
    $file_mode = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : 0644;

    if (!$filesystem->put_contents($timestamped_css_file_path, trim($minified_css_content), $file_mode)) {
        return;
    }

    // Update the option with the new version timestamp
    update_option('elzo_forms_style_version', $version_timestamp);
}

/**
 * Build preferred source string for field key generation.
 *
 * @see \ElzoForms\Form\Form_Data_Normalizer::get_field_key_source()
 */
function elzo_forms_get_field_key_source(array $field): string {
    return \ElzoForms\Form\Form_Data_Normalizer::get_field_key_source($field);
}

/**
 * Normalize field_key values across all fields in the form and ensure uniqueness.
 *
 * @see \ElzoForms\Form\Form_Data_Normalizer::normalize_field_keys()
 */
function elzo_forms_normalize_field_keys(array $steps): array {
    return \ElzoForms\Form\Form_Data_Normalizer::normalize_field_keys($steps);
}

/**
 * Drop conditional logic rules that point at a field the form no longer holds.
 *
 * @see \ElzoForms\Form\Form_Data_Normalizer::drop_dangling_logic_rules()
 */
function elzo_forms_drop_dangling_logic_rules(array $steps): array {
    return \ElzoForms\Form\Form_Data_Normalizer::drop_dangling_logic_rules($steps);
}

// Save the custom meta box data
function elzo_forms_save_meta_box_data($post_id) {
    // Check if our nonce is set and verify that the nonce is valid.
    if (!isset($_POST['elzo_forms_meta_box_nonce']) || !is_scalar($_POST['elzo_forms_meta_box_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash((string) $_POST['elzo_forms_meta_box_nonce']));
    if (!wp_verify_nonce($nonce, 'elzo_forms_save_meta_box')) {
        return;
    }

    if (get_post_type($post_id) !== 'elzo_form') {
        return;
    }

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (\ElzoForms\Form\Form_JSON_Storage::is_form_edit_locked((int) $post_id)) {
        return;
    }

    // Check if the function is already running to avoid infinite loop
    static $is_saving = false;

    if ($is_saving) {
        return;
    }

    // Set the static variable
    $is_saving = true;

    $posted_form_fields = [];
    if (!empty($_POST['elzo_form_fields']) && is_array($_POST['elzo_form_fields'])) {
        $posted_form_fields = wp_unslash($_POST['elzo_form_fields']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are sanitized field-by-field in the loop below; nonce verified at the top of this function.
    }

    // Initialize an empty array to store the form fields
    $steps = array();

    // Check if form fields are set and is an array
    if(!empty($posted_form_fields) && is_array($posted_form_fields)){
        // Remove the template step (index 0) if it exists
        unset($posted_form_fields[0]);

        foreach($posted_form_fields as $step_index => $raw_step){
            if(!is_array($raw_step)){
                continue;
            }

            // Initialize an empty array to store the fields
            $fields = $raw_step['fields'] ?? null;

            // Build a sanitized step from explicit keys only.
            $sanitized_step = [
                'index'  => isset($raw_step['index']) ? absint($raw_step['index']) : absint($step_index),
                'label'  => isset($raw_step['label']) ? sanitize_text_field((string) $raw_step['label']) : '',
                'fields' => [],
            ];

            // Check if fields are set and is an array
            if($fields && is_array($fields)){
                // Remove template field (index 0) if it exists
                unset($fields[0]);

                // Loop through each field and sanitize the values
                foreach($fields as $field){
                    if (!is_array($field)) {
                        continue;
                    }

                    // Sanitize the field values
                    foreach($field as $key => $value){
                        /*
                         * Field definitions stay open to third-party field types
                         * (see Field::normalize_configuration()), so keys are validated
                         * rather than rewritten: anything that is not a plain machine
                         * name is dropped instead of being silently renamed into a
                         * second key alongside the original.
                         */
                        if(!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $key)){
                            unset($field[$key]);
                            continue;
                        }

                        /*
                         * Every branch below except 'rules' and 'width' expects a scalar
                         * string. Validate the shape before sanitizing so a crafted
                         * nested payload cannot reach explode()/wp_kses_post() and fatal.
                         */
                        if(!is_string($value) && !in_array($key, ['rules', 'width'], true)){
                            unset($field[$key]);
                            continue;
                        }

                        if($key === 'options'){
                            // Convert options to array
                            $field[$key] = array_map(function($option){
                                // Sanitize the option
                                $option = sanitize_text_field($option);

                                // Split the option by colon
                                $option = strpos($option, ':') !== false ? explode(':', $option) : [$option];

                                // Prepare the value and label
                                $value = trim($option[0]);
                                $label = !empty($option[1]) ? trim($option[1]) : null;

                                // Prepare the description
                                $description = null;

                                // Check if label is set and contains placeholders
                                if($label && strpos($label, '||') !== false){
                                    // Split the label by placeholder
                                    $label_arr = explode('||', $label);

                                    // Prepare the label and description
                                    $label = trim($label_arr[0]);
                                    $description = trim($label_arr[1]);
                                }

                                return [
                                    'value' => $value,
                                    'label' => $label,
                                    'description' => $description,
                                ];
                            }, array_filter(explode("\n", $value)));
                        } else if($key === 'rules'){
                            // Loop through each group and sanitize condition items
                            if(!empty($value) && is_array($value)){
                                foreach($value as $group_index => $group){
                                    if(!is_array($group)){
                                        unset($field[$key][$group_index]);
                                        continue;
                                    }
                                    foreach($group as $rule_index => $rule){
                                        if(!is_array($rule)){
                                            unset($field[$key][$group_index][$rule_index]);
                                            continue;
                                        }

                                        /*
                                         * A type that is not registered right now (PRO or an addon
                                         * inactive) is kept as long as it is a clean machine name,
                                         * so a plain Save does not delete the rule. Like field keys,
                                         * it is validated rather than rewritten into another type.
                                         */
                                        $rule_type = isset($rule['type']) && is_scalar($rule['type']) ? (string) $rule['type'] : '';
                                        if(!\ElzoForms\Utilities\Conditional_Logic::is_storable_condition_type($rule_type, 'field')){
                                            unset($field[$key][$group_index][$rule_index]);
                                            continue;
                                        }
                                        $rule_operator = isset($rule['operator']) ? sanitize_text_field((string) $rule['operator']) : '';
                                        $rule_settings_raw = isset($rule['settings']) && is_array($rule['settings']) ? $rule['settings'] : [];
                                        $rule_settings = [];
                                        foreach($rule_settings_raw as $s_key => $s_value){
                                            $rule_settings[sanitize_key($s_key)] = sanitize_text_field((string) $s_value);
                                        }
                                        // Require field_id for field type
                                        if($rule_type === 'field' && empty($rule_settings['field_id'])){
                                            unset($field[$key][$group_index][$rule_index]);
                                            continue;
                                        }
                                        // Require cookie name for cookie type
                                        if($rule_type === 'cookie' && empty($rule_settings['name'])){
                                            unset($field[$key][$group_index][$rule_index]);
                                            continue;
                                        }
                                        $field[$key][$group_index][$rule_index] = [
                                            'type'     => $rule_type,
                                            'operator' => $rule_operator,
                                            'settings' => $rule_settings,
                                        ];
                                    }
                                    // Re-index items
                                    $field[$key][$group_index] = array_values(array_filter($field[$key][$group_index]));
                                }
                            }

                            // Remove empty groups and re-index
                            $groups = $field[$key] ?? [];
                            $field[$key] = is_array($groups) ? array_values(array_filter($groups)) : [];
                        } else if($key === 'width') {
                            $field[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : [];
                        } else if($key === 'content') {
                            // Sanitize the HTML content into its stored representation
                            $field[$key] = \ElzoForms\Form\Form_Data_Normalizer::encode_content($value);
                        } else if($key === 'allowed_file_types'){
                            $field[$key] = \ElzoForms\Utilities\Admin::handle_mime_types($value);
                        } else {
                            $field[$key] = sanitize_text_field($value);
                        }
                    }

                    // Checkboxes
                    $field['required'] = !empty($field['required']);
                    $field['logic'] = !empty($field['logic']);
                    $field['primary_field'] = !empty($field['primary_field']);
                    $field['submission_table_field'] = !empty($field['submission_table_field']);

                    // Let field classes normalize their own configuration before save.
                    $field = \ElzoForms\Field\Field::normalize_definition($field);

                    // Apply filters to the field before saving
                    $field = apply_filters('elzo_forms_field_before_save', $field);

                    // Whatever a filter returns, stored fields keep a single
                    // composite "type" and never a separate "subtype".
                    $field = \ElzoForms\Field\Field_Type::normalize_field(is_array($field) ? $field : []);

                    // Populate the step fields array
                    $sanitized_step['fields'][] = $field;
                }

                // Apply filters to the step before saving
                $sanitized_step = apply_filters('elzo_forms_step_before_save', $sanitized_step);

                // Populate the steps array
                $steps[] = $sanitized_step;
            }
        }
    }

    // Keep stable machine-readable field keys and avoid collisions in one form.
    $steps = elzo_forms_normalize_field_keys($steps);

    // Conditional logic can only reference fields the saved form actually has.
    $steps = elzo_forms_drop_dangling_logic_rules($steps);

    $posted_form_settings = [];
    if (!empty($_POST['elzo_forms_form_settings']) && is_array($_POST['elzo_forms_form_settings'])) {
        $posted_form_settings = wp_unslash($_POST['elzo_forms_form_settings']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are sanitized key-by-key in the loop below; nonce verified at the top of this function.
    }

    // Initialize an empty array to store the form settings
    $settings = array();

    // Check if form settings are set and is an array
    if(!empty($posted_form_settings) && is_array($posted_form_settings)){
        // Sanitize the settings values
        foreach($posted_form_settings as $key => $value){
            // Sanitize key and value
            $key = sanitize_text_field($key);
            $value = is_array($value) ? array_map('sanitize_text_field', $value) : (in_array($key, ['email_notification_recipients', 'email_notification_reply_to'], true) ? elzo_forms_sanitize_email_recipients($value) : sanitize_text_field($value));

            // Apply value filters
            $value = apply_filters('elzo_forms_form_settings_value_before_save', $value, $key);
            $value = apply_filters('elzo_forms_form_settings_' . $key . '_before_save', $value);

            // Update the settings array
            $settings[$key] = $value;
        }
    }

    $posted_texts_settings = [];
    if (!empty($_POST['elzo_forms_texts_settings']) && is_array($_POST['elzo_forms_texts_settings'])) {
        $posted_texts_settings = wp_unslash($_POST['elzo_forms_texts_settings']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are sanitized key-by-key in the loop below; nonce verified at the top of this function.
    }

    // Initialize an empty array to store the texts settings
    $texts = array();

    // Check if texts settings are set and is an array
    if(!empty($posted_texts_settings) && is_array($posted_texts_settings)){
        // Sanitize the settings values
        foreach($posted_texts_settings as $key => $value){
            // Sanitize key and value
            $key = sanitize_text_field($key);
            $value = in_array($key, ['email_notification_subject', 'email_notification_message'], true) ? sanitize_textarea_field($value) : sanitize_text_field($value);

            // Apply value filters
            $value = apply_filters('elzo_forms_form_texts_value_before_save', $value, $key);
            $value = apply_filters('elzo_forms_form_texts_' . $key . '_before_save', $value);

            // Update the texts array
            $texts[$key] = $value;
        }
    }

    // Preserve existing modules from JSON unless new modules are posted
    $existing_content = get_post_field('post_content', $post_id);
    $existing_data = $existing_content ? \ElzoForms\Form\Form_Data_Normalizer::decode_form_json((string) $existing_content) : null;
    $existing_modules = (is_array($existing_data) && isset($existing_data['modules']) && is_array($existing_data['modules'])) ? $existing_data['modules'] : [];
    $posted_form_modules = [];
    if (!empty($_POST['elzo_forms_form_modules']) && is_array($_POST['elzo_forms_form_modules'])) {
        $posted_form_modules = wp_unslash($_POST['elzo_forms_form_modules']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are sanitized key-by-key in the loop below; nonce verified at the top of this function.
    }

    // Collect per-form modules from POST if provided
    $modules = $existing_modules;
    if (!empty($posted_form_modules) && is_array($posted_form_modules)) {
        $modules = [];
        $modules_manager = function_exists('elzo_forms_modules_manager') ? elzo_forms_modules_manager() : null;
        foreach ($posted_form_modules as $module_id => $module_payload) {
            $module_id = sanitize_key($module_id);
            $status = 'inherit';
            $settings_payload = [];

            if ($module_id === '') {
                continue;
            }

            $registered_module = $modules_manager ? $modules_manager->get($module_id) : null;
            if ($modules_manager && !$registered_module) {
                continue;
            }

            if (is_array($module_payload)) {
                if (isset($module_payload['status'])) {
                    $candidate = sanitize_text_field($module_payload['status']);
                    $status = in_array($candidate, ['inherit','enabled','disabled'], true) ? $candidate : 'inherit';
                }
                if (!empty($module_payload['settings']) && is_array($module_payload['settings'])) {
                    if ($modules_manager && $registered_module) {
                        $settings_payload = $modules_manager->sanitize_settings($module_payload['settings'], $registered_module->get_settings_schema());
                    } else {
                        foreach ($module_payload['settings'] as $k => $v) {
                            $k = sanitize_key($k);
                            if ($k === '') {
                                continue;
                            }

                            if (is_array($v)) {
                                $settings_payload[$k] = array_map('sanitize_text_field', $v);
                            } else {
                                $settings_payload[$k] = sanitize_text_field($v);
                            }
                        }
                    }
                }
            }
            $modules[$module_id] = [
                'status' => $status,
                'settings' => $settings_payload,
            ];
        }
    }

    // Prepare the data to be saved
    $data = [
        'steps' => $steps,
        'settings' => $settings,
        'texts' => $texts,
        'modules' => $modules,
    ];
    if (is_array($existing_data) && array_key_exists(\ElzoForms\Form\Compatibility\MinimumVersion::KEY, $existing_data)) {
        $data[\ElzoForms\Form\Compatibility\MinimumVersion::KEY] = $existing_data[\ElzoForms\Form\Compatibility\MinimumVersion::KEY];
    }

    // Filter callbacks receive only whitelisted, sanitized save context.
    $save_context = $data;
    $clear_automation_draft = false;

    // Let feature modules (e.g., PRO automations) extend saved form payload.
    $data = apply_filters('elzo_forms_form_data_before_save', $data, $post_id, $save_context);

    $notification_templates = [];
    foreach (['email_notification_recipients' => 'settings', 'email_notification_reply_to' => 'settings', 'email_notification_subject' => 'texts', 'email_notification_message' => 'texts'] as $key => $section) {
        $value = $data[$section][$key] ?? '';
        $global = get_option($section === 'settings' ? 'elzo_forms_form_settings' : 'elzo_forms_texts_settings', []);
        $notification_templates[$key] = $value !== '' ? $value : ($global[$key] ?? '');
    }
    $notification_errors = \ElzoForms\Variables\Notification_Validation::validate($notification_templates, (new \ElzoForms\Form\Form($data))->get_fields());
    if ($notification_errors) {
        set_transient(elzo_forms_notification_form_draft_key((int) $post_id), ['data' => $data, 'errors' => $notification_errors], DAY_IN_SECONDS);
        $is_saving = false;
        return;
    }

    // Encode the data to JSON and handle errors
    try {
        $json_data = wp_json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (JsonException $e) {
        // Allow custom handling of JSON encoding failures.
        do_action('elzo_forms_json_encoding_error', $e, $post_id);

        // Reset the static variable
        $is_saving = false;

        // Return
        return;
    }

    // Save fields data as JSON in post content
    $saved_post_id = wp_update_post([
        'ID' => $post_id,
        // wp_update_post() unslashes post fields before saving; keep JSON escape sequences intact.
        'post_content' => wp_slash($json_data),
    ]);
    if ($saved_post_id && !is_wp_error($saved_post_id)) {
        delete_transient(elzo_forms_notification_form_draft_key((int) $post_id));
    }

    // Reset the static variable
    $is_saving = false;
}
add_action('save_post', 'elzo_forms_save_meta_box_data');

// Form Post Admin Table Column

function elzo_forms_form_post_extra_columns($columns) {
    return array_slice($columns, 0, 2, true) + array('shortcode' => __('Shortcode', 'elzo-forms')) + array_slice($columns, 2, count($columns) - 2, true);
}
add_filter('manage_elzo_form_posts_columns' , 'elzo_forms_form_post_extra_columns', 100);

function elzo_forms_form_post_extra_columns_value($column, $post_id) {
    if($column === 'shortcode') {
        echo '<input type="text" readonly value="[elzo_form id=&quot;' . esc_attr($post_id) . '&quot;]" class="elzo-forms-shortcode-input" onclick="this.select();">';
    }
}
add_action('manage_elzo_form_posts_custom_column', 'elzo_forms_form_post_extra_columns_value', 10, 2);

// Submission Post Admin Table Column

/**
 * Record (or read back) the submission table columns that stand for form fields.
 *
 * Column keys are field IDs, and a field ID is a free-form string: a form
 * written outside the admin UI can carry readable IDs such as "contact_email".
 * The value callback therefore cannot recognise its own columns by shape, so
 * the column filter records them here and the callback reads them back.
 *
 * @param array|null $columns Field columns to record, or null to read them back
 * @return array Recorded field columns, keyed by column name
 */
function elzo_forms_submission_table_field_columns($columns = null): array {
    static $field_columns = [];

    if (is_array($columns)) {
        $field_columns = $columns;
    }

    return $field_columns;
}

/**
 * Collect the fields of a form that may become submission table columns.
 *
 * Fields are read from every step: a field flagged for the table on the second
 * step is as good a column as one on the first. Fields without an ID and
 * read-only fields (content blocks, buttons) are dropped - the first cannot be
 * matched against a stored submission, and the second never reaches submission
 * data, so either one could only ever render an empty column.
 *
 * @param array $steps Form steps as stored in the form JSON
 * @return array Fields eligible for the submission table, in form order
 */
function elzo_forms_submission_table_form_fields(array $steps): array {
    $fields = [];

    foreach ($steps as $step) {
        if (empty($step['fields']) || !is_array($step['fields'])) {
            continue;
        }

        foreach ($step['fields'] as $field) {
            if (!is_array($field)) {
                continue;
            }

            // Apply filter to each field
            $field = apply_filters('elzo_forms_admin_field', $field);
            if (!is_array($field)) {
                continue;
            }

            $field_id = isset($field['id']) && is_scalar($field['id']) ? (string) $field['id'] : '';
            if ($field_id === '') {
                continue;
            }

            if (\ElzoForms\Field\Field::from($field)->is_read_only()) {
                continue;
            }

            $fields[] = $field;
        }
    }

    return $fields;
}

function elzo_forms_submission_post_extra_columns($columns) {
    /* translators: %s: Field ID or position, shown when the field has no label. */
    $field_label_format = __('Field %s', 'elzo-forms');

    $field_labels = [
        sprintf($field_label_format, 1),
        sprintf($field_label_format, 2),
        sprintf($field_label_format, 3),
    ];

    $existing_forms = get_posts([
        'post_type' => 'elzo_form',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    // Check if there is only one existing form
    $has_single_form = apply_filters('elzo_forms_has_single_form', count($existing_forms) === 1);

    // If there is only one form, we can use its fields
    $single_form_id = $has_single_form && !empty($existing_forms) ? $existing_forms[0] : null;

    // Initialize an empty array for submission fields
    $submission_fields = [];
    $selected_form_id = elzo_forms_get_admin_query_absint('elzo_form_id');

    // Get field names when a form filter is active.
    if($single_form_id || $selected_form_id > 0) {
        $form_id = $single_form_id ? $single_form_id : $selected_form_id;
        $form_data = $form_id ? get_post_field('post_content', $form_id) : null;
        $form = $form_data ? \ElzoForms\Form\Form_Data_Normalizer::decode_form_json((string) $form_data) : null;
        $steps = !empty($form['steps']) && is_array($form['steps']) ? $form['steps'] : [];
        $fields = elzo_forms_submission_table_form_fields($steps);

        if($fields) {
            // Add fields as columns
            foreach ($fields as $field) {
                // Check if the field is a submission table field
                if(!empty($field['submission_table_field'])) {
                    $submission_fields[] = $field;
                }
            }

            // Check if there are less than 3 submission fields
            if(count($submission_fields) < 3) {
                // Loop through the remaining fields and add them to the submission fields
                foreach ($fields as $field) {
                    if(!empty($field['submission_table_field'])) continue; // Skip if already a submission table field
                    if(!empty($field['primary_field'])) continue; // Skip if primary field
                    if(count($submission_fields) >= 3) break; // Stop if we have 3 submission fields
                    $submission_fields[] = $field;
                }
            }

            // Column keys a field must not claim: the union below keeps the
            // first occurrence of a key, so a field ID shadowing "title" or
            // "date" would silently replace that core column.
            $reserved_columns = is_array($columns) ? array_keys($columns) : [];
            $reserved_columns[] = 'form';

            // Build $field_labels based on the submission fields
            $field_labels = [];

            // Loop through the submission fields and set the labels
            foreach ($submission_fields as $field) {
                $field_id = (string) $field['id'];

                // One column per field ID: form JSON written outside the admin
                // UI can repeat an ID, and a repeat would overwrite the column
                // that was already built for it.
                if(isset($field_labels[$field_id]) || in_array($field_id, $reserved_columns, true)) {
                    continue;
                }

                $admin_label = !empty($field['admin_label']) ? $field['admin_label'] : null;
                $label = !empty($field['label']) ? $field['label'] : null;
                $placeholder = !empty($field['placeholder']) ? $field['placeholder'] : null;

                // Set the field label based on admin_label, label, or placeholder
                $field_label = $admin_label ? $admin_label : ($label ? $label : $placeholder);

                // Normalize to string to avoid mb_* deprecation warnings on null/non-scalar values.
                $field_label = is_scalar($field_label) ? trim((string) $field_label) : '';

                // Hidden fields, and fields left unlabelled, still need a header.
                if($field_label === '') {
                    $field_label = sprintf($field_label_format, count($field_labels) + 1);
                }

                // Limit the label to 25 characters
                $field_label = mb_strlen($field_label) > 25 ? mb_substr($field_label, 0, 25) . '...' : $field_label;

                // Add the field label to the array
                $field_labels[$field_id] = $field_label;
            }

        }
    }

    // Apply filters to field labels
    $field_labels = apply_filters('elzo_forms_submission_table_field_labels', $field_labels, $submission_fields);
    $field_labels = is_array($field_labels) ? $field_labels : [];

    // Hand the value callback the exact set of columns it has to render.
    elzo_forms_submission_table_field_columns($field_labels);

    return array_slice($columns, 0, 1, true) + array_slice($columns, 1, 1, true) + $field_labels + array('form' => __('Form', 'elzo-forms')) + array_slice($columns, 1, count($columns) - 1, true);
}

add_filter('manage_elzo_submission_posts_columns' , 'elzo_forms_submission_post_extra_columns', 100);

function elzo_forms_submission_post_extra_columns_value($column, $post_id) {
    $column_key = is_scalar($column) ? (string) $column : '';
    $field_columns = elzo_forms_submission_table_field_columns();

    // A field column is recognised by the column list this screen was built
    // with, not by the shape of its key: field IDs are strings and a readable
    // one such as "contact_email" is not numeric. The numeric test remains as a
    // fallback for rows rendered without the column filter having run.
    $is_field_column = $field_columns
        ? array_key_exists($column_key, $field_columns)
        : is_numeric($column_key);

    if($is_field_column) {
        $submission_data = json_decode(get_post_field('post_content', $post_id), true);
        if(!$submission_data || empty($submission_data['fields']) || !is_array($submission_data['fields'])) {
            echo '-';
            return;
        }

        // Search for the field in the submission data, comparing IDs as the
        // strings they are so that "01" never matches "1".
        $field = null;
        foreach($submission_data['fields'] as $submission_field) {
            if(!is_array($submission_field) || !isset($submission_field['id']) || !is_scalar($submission_field['id'])) {
                continue;
            }

            if((string) $submission_field['id'] === $column_key) {
                $field = $submission_field;
                break;
            }
        }

        // The numbered placeholder columns shown when the table is not narrowed
        // down to a single form address fields by position instead.
        if($field === null && ctype_digit($column_key)) {
            $field = $submission_data['fields'][(int) $column_key] ?? null;
        }

        // Apply filters to the field
        $field = apply_filters('elzo_forms_submission_table_field', $field, $column, $submission_data);

        $column_value = is_array($field) && isset($field['value']) ? $field['value'] : null;

        // "0" is a value a number, range or select field can legitimately hold,
        // so emptiness is tested rather than left to empty().
        $has_value = is_array($column_value) ? $column_value !== [] : ($column_value !== null && $column_value !== '');

        if($has_value) {
            if(is_array($column_value)) {
                // If the value is an array, implode it
                $column_value = implode(', ', array_filter($column_value, 'is_scalar'));
            }

            // Normalize to string to avoid mb_* warnings on null/non-scalar values.
            $column_value = is_scalar($column_value) ? (string) $column_value : '';

            // Limit the output to 50 characters before escaping: escaping first
            // double-escapes the value and lets the cut land inside an entity.
            $column_value = mb_substr($column_value, 0, 50);

            echo esc_html($column_value);
        } else {
            echo '-';
        }
    } elseif ($column_key === 'form') {
        $form_id = get_post_meta($post_id, 'form_id', 1);

        // Sanitize form ID
        $form_id = !empty($form_id) ? is_numeric($form_id) ? intval($form_id) : sanitize_text_field($form_id) : null;

        // Get form title and edit URL
        $form_title = is_int($form_id) ? get_the_title($form_id) : '';
        $form_edit_url = is_int($form_id) ? get_edit_post_link($form_id) : '';

        // Apply filter to form title and edit URL
        $form_title = apply_filters('elzo_forms_submission_table_form_title', $form_title, $form_id);
        $form_edit_url = apply_filters('elzo_forms_submission_table_form_edit_url', $form_edit_url, $form_id);

        // Set $form_title to $form_id if title is empty
        if (empty($form_title) && $form_id) {
            $form_title = $form_id;
        }

        // Normalize to string to avoid mb_* deprecation warnings on null/non-scalar values.
        $form_title = is_scalar($form_title) ? (string) $form_title : '';

        // Limit the form title to 25 characters
        $form_title = mb_strlen($form_title) > 25 ? mb_substr($form_title, 0, 25) . '...' : $form_title;

        // Output the form title as a link if it exists, otherwise just the title or ID
        echo $form_edit_url ? '<a href="' . esc_url($form_edit_url) . '" target="_blank">' . esc_html($form_title) . '</a>' : esc_html($form_title);
    }
}

add_action('manage_elzo_submission_posts_custom_column' , 'elzo_forms_submission_post_extra_columns_value', 10, 2);

// Submission Post Form Dropdown Filter

function elzo_forms_submission_post_form_dropdown_filter() {
    global $typenow;
    if ($typenow === 'elzo_submission') {
        $args = array(
            'post_type' => 'elzo_form',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        );
        $forms = get_posts($args);

        // Convert the forms to an associative array: form_ID => form_title
        $forms = wp_list_pluck($forms, 'post_title', 'ID');

        // Apply 'elzo_forms_submission_form_dropdown_options' filter
        $forms = apply_filters('elzo_forms_submission_form_dropdown_options', $forms);

        $selected = elzo_forms_get_admin_query_text('elzo_form_id');
        echo '<select name="elzo_form_id">';
        echo '<option value="">'.esc_html__('All Forms','elzo-forms').'</option>';
        if ($forms) {
            foreach ($forms as $form_id => $form_title) {
                echo '<option value="' . esc_attr($form_id) . '"' . selected($selected, $form_id, false) . '>' . esc_html($form_title) . '</option>';
            }
        } else {
            echo '<option value="" disabled>' . esc_html__('No Forms found', 'elzo-forms') . '</option>';
        }
        echo '</select>';
    }
}

add_action('restrict_manage_posts', 'elzo_forms_submission_post_form_dropdown_filter');

function elzo_forms_submission_post_form_dropdown_filter_query($query) {
    global $pagenow;
    $type = 'elzo_submission';
    $post_type = elzo_forms_get_admin_query_slug('post_type');
    $selected_form_id = elzo_forms_get_admin_query_text('elzo_form_id');

    if ($pagenow === 'edit.php' && $query->is_main_query() && $post_type === $type && $selected_form_id !== '') {
        $query->query_vars['meta_key'] = 'form_id'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for admin list-table filtering by selected form.
        $query->query_vars['meta_value'] = $selected_form_id; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required for admin list-table filtering by selected form.
    }
}

add_filter('parse_query', 'elzo_forms_submission_post_form_dropdown_filter_query');

// Submission Date Range Filter
// Replaces the month dropdown, which cannot narrow submissions to days or
// span several months.

function elzo_forms_submission_disable_months_dropdown($disable, $post_type) {
    return $post_type === 'elzo_submission' ? true : $disable;
}

add_filter('disable_months_dropdown', 'elzo_forms_submission_disable_months_dropdown', 10, 2);

/**
 * Get the date range selected in the submissions list.
 *
 * @return array{from: string, to: string} Valid Y-m-d dates, or empty strings.
 */
function elzo_forms_get_submission_date_range() {
    $range = array();

    foreach (array('from' => 'elzo_date_from', 'to' => 'elzo_date_to') as $bound => $key) {
        $date = elzo_forms_get_admin_query_text($key);
        $range[$bound] = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            ? $date
            : '';
    }

    // A reversed range still means the days between the two dates.
    if ($range['from'] !== '' && $range['to'] !== '' && $range['from'] > $range['to']) {
        $range = array('from' => $range['to'], 'to' => $range['from']);
    }

    return $range;
}

function elzo_forms_submission_date_range_filter($post_type) {
    if ($post_type !== 'elzo_submission') {
        return;
    }

    $range = elzo_forms_get_submission_date_range();

    echo '<span class="elzo-forms-date-range">';
    echo '<label for="elzo-forms-date-from" class="screen-reader-text">' . esc_html__('Submitted from', 'elzo-forms') . '</label>';
    echo '<input type="date" id="elzo-forms-date-from" name="elzo_date_from" value="' . esc_attr($range['from']) . '" title="' . esc_attr__('Submitted from', 'elzo-forms') . '">';
    echo '<span class="elzo-forms-date-range-separator" aria-hidden="true">&ndash;</span>';
    echo '<label for="elzo-forms-date-to" class="screen-reader-text">' . esc_html__('Submitted until', 'elzo-forms') . '</label>';
    echo '<input type="date" id="elzo-forms-date-to" name="elzo_date_to" value="' . esc_attr($range['to']) . '" title="' . esc_attr__('Submitted until', 'elzo-forms') . '">';
    echo '</span>';
}

// Before the form filter, where WordPress shows the month dropdown.
add_action('restrict_manage_posts', 'elzo_forms_submission_date_range_filter', 5);

function elzo_forms_submission_date_range_filter_query($query) {
    global $pagenow;

    if ($pagenow !== 'edit.php' || !$query->is_main_query() || elzo_forms_get_admin_query_slug('post_type') !== 'elzo_submission') {
        return;
    }

    $range = elzo_forms_get_submission_date_range();
    if ($range['from'] === '' && $range['to'] === '') {
        return;
    }

    // Days are whole and in the site timezone, as the Date column shows them.
    $date_query = array(
        'column' => 'post_date',
        'inclusive' => true,
    );
    if ($range['from'] !== '') {
        $date_query['after'] = $range['from'] . ' 00:00:00';
    }
    if ($range['to'] !== '') {
        $date_query['before'] = $range['to'] . ' 23:59:59';
    }

    $query->query_vars['date_query'] = array($date_query);
}

add_filter('parse_query', 'elzo_forms_submission_date_range_filter_query');

// Add Spam filter to submission post type views
function elzo_forms_submission_views($views) {
    global $typenow;

    if ($typenow !== 'elzo_submission') {
        return $views;
    }

    // Count spam submissions
    $spam_count = wp_count_posts('elzo_submission')->spam ?? 0;

    // Get current URL parameters
    $current_url = admin_url('edit.php?post_type=elzo_submission');
    $spam_url = add_query_arg('post_status', 'spam', $current_url);

    // Check if currently viewing spam
    $current_status = elzo_forms_get_admin_query_slug('post_status');
    $current_class = ($current_status === 'spam') ? ' class="current"' : '';

    // Add spam view to the views array
    $views['spam'] = sprintf(
        '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
        esc_url($spam_url),
        $current_class,
        esc_html__('Spam', 'elzo-forms'),
        $spam_count
    );

    return $views;
}
add_filter('views_edit-elzo_submission', 'elzo_forms_submission_views');

// Add bulk actions for spam
function elzo_forms_submission_bulk_actions($actions) {
    global $typenow;

    if ($typenow === 'elzo_submission') {
        $actions['mark_spam'] = esc_html__('Mark as Spam', 'elzo-forms');
        $actions['unmark_spam'] = esc_html__('Not Spam', 'elzo-forms');
    }

    return $actions;
}
add_filter('bulk_actions-edit-elzo_submission', 'elzo_forms_submission_bulk_actions');

// Handle bulk actions
function elzo_forms_handle_submission_bulk_actions($redirect_to, $action, $post_ids) {
    $status = '';
    $redirect_arg = '';

    if ($action === 'mark_spam') {
        $status = 'spam';
        $redirect_arg = 'bulk_spam';
    } elseif ($action === 'unmark_spam') {
        $status = 'publish';
        $redirect_arg = 'bulk_not_spam';
    } else {
        return $redirect_to;
    }

    $updated_count = 0;
    foreach ((array) $post_ids as $post_id) {
        $post_id = absint($post_id);
        if (
            $post_id <= 0
            || get_post_type($post_id) !== 'elzo_submission'
            || !current_user_can('edit_post', $post_id)
        ) {
            continue;
        }

        $updated_post_id = wp_update_post(array(
            'ID' => $post_id,
            'post_status' => $status,
        ));

        if ($updated_post_id) {
            $updated_count++;
        }
    }

    $redirect_to = add_query_arg(array($redirect_arg => $updated_count), $redirect_to);

    return $redirect_to;
}
add_filter('handle_bulk_actions-edit-elzo_submission', 'elzo_forms_handle_submission_bulk_actions', 10, 3);

// Add spam row actions
function elzo_forms_submission_row_actions($actions, $post) {
    if ($post->post_type === 'elzo_submission' && current_user_can('edit_post', $post->ID)) {
        if ($post->post_status === 'spam') {
            $actions['not_spam'] = sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(
                    admin_url('admin-post.php?action=elzo_submission_not_spam&post=' . $post->ID),
                    'elzo_submission_not_spam_' . $post->ID
                ),
                esc_html__('Not Spam', 'elzo-forms')
            );
        } else {
            $actions['spam'] = sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(
                    admin_url('admin-post.php?action=elzo_submission_spam&post=' . $post->ID),
                    'elzo_submission_spam_' . $post->ID
                ),
                esc_html__('Spam', 'elzo-forms')
            );
        }
    }

    return $actions;
}
add_filter('post_row_actions', 'elzo_forms_submission_row_actions', 10, 2);

// Handle individual spam actions
function elzo_forms_handle_spam_action() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The ID is needed to build the per-submission nonce action verified immediately below.
    $post_id = isset($_GET['post']) && is_scalar($_GET['post'])
        ? absint(wp_unslash((string) $_GET['post']))
        : 0;

    if ($post_id <= 0) {
        wp_die(esc_html__('Security check failed', 'elzo-forms'));
    }

    check_admin_referer('elzo_submission_spam_' . $post_id);

    if (get_post_type($post_id) !== 'elzo_submission' || !current_user_can('edit_post', $post_id)) {
        wp_die(esc_html__('You are not allowed to update this submission.', 'elzo-forms'));
    }

    wp_update_post(array(
        'ID' => $post_id,
        'post_status' => 'spam'
    ));

    wp_safe_redirect(admin_url('edit.php?post_type=elzo_submission'));
    exit;
}
add_action('admin_post_elzo_submission_spam', 'elzo_forms_handle_spam_action');

function elzo_forms_handle_not_spam_action() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The ID is needed to build the per-submission nonce action verified immediately below.
    $post_id = isset($_GET['post']) && is_scalar($_GET['post'])
        ? absint(wp_unslash((string) $_GET['post']))
        : 0;

    if ($post_id <= 0) {
        wp_die(esc_html__('Security check failed', 'elzo-forms'));
    }

    check_admin_referer('elzo_submission_not_spam_' . $post_id);

    if (get_post_type($post_id) !== 'elzo_submission' || !current_user_can('edit_post', $post_id)) {
        wp_die(esc_html__('You are not allowed to update this submission.', 'elzo-forms'));
    }

    wp_update_post(array(
        'ID' => $post_id,
        'post_status' => 'publish'
    ));

    wp_safe_redirect(admin_url('edit.php?post_type=elzo_submission'));
    exit;
}
add_action('admin_post_elzo_submission_not_spam', 'elzo_forms_handle_not_spam_action');

// Display spam status label in the submission list table
function elzo_forms_display_spam_status_label($post_states, $post) {
    if ($post->post_type === 'elzo_submission' && $post->post_status === 'spam') {
        $post_states[] = esc_html__('Spam', 'elzo-forms');
    }
    return $post_states;
}
add_filter('display_post_states', 'elzo_forms_display_spam_status_label', 10, 2);

// Handle mime types
function elzo_forms_handle_mime_types($mime_types_string, $return_format = 'string'){
    $mime_types = sanitize_text_field($mime_types_string);
    $mime_types = strpos($mime_types, ',') !== false ? array_map('trim', explode(',', $mime_types)) : [trim($mime_types)];

    // Loop through mime types
    foreach($mime_types as $key => $mime_type){
        $mime_type = trim($mime_type);

        // Check if mime type is empty
        if(empty($mime_type)){
            unset($mime_types[$key]);
            continue;
        }

        // Check if mime type is a file extension
        if(strpos($mime_type, '/') === false){
            $mime_types[$key] = '.' . str_replace('.', '', $mime_type);
        // Check if mime type is a wildcard
        } else if($mime_type === '*'){
            $mime_types[$key] = '*';
        } else {
            // Check if mime type is a wildcard with a prefix
            if(strpos($mime_type, '/*') !== false){
                $mime_types[$key] = str_replace('/*', '', $mime_type);
            }
        }
    }

    // Return mime types as a string
    return $return_format === 'array' ? $mime_types : implode(', ', $mime_types);
}

// Convert mime types to file extensions
function elzo_forms_mime_type_to_extension($mime_type) {
    $mime_types = wp_get_mime_types();
    $extensions = array_flip($mime_types);

    return isset($extensions[$mime_type]) ? $extensions[$mime_type] : false;
}
