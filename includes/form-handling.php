<?php
/**
 * Form submission handling.
 *
 * Processes form submissions via AJAX and standard POST requests.
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file is always included inside a class method (ElzoForms::handle_form_submission()), so all variables here are in function scope, not global scope. The sniff is a false positive.

defined('ABSPATH') || exit;

// Verify nonce for CSRF protection
$nonce = isset($_POST['elzo_forms_nonce']) && is_scalar($_POST['elzo_forms_nonce'])
    ? sanitize_text_field(wp_unslash((string) $_POST['elzo_forms_nonce']))
    : '';

if (!$nonce || !wp_verify_nonce($nonce, 'elzo_forms_action')) {
    wp_send_json_error([
        'message' => __('Security check failed', 'elzo-forms'),
    ]);
}

// Get form ID
$form_id = isset($_POST['elzo_form_id']) && is_scalar($_POST['elzo_form_id'])
    ? absint(wp_unslash((string) $_POST['elzo_form_id']))
    : 0;

// Break if form ID is not set
if(!$form_id) wp_send_json_error([
    'message' => __('Form ID is required', 'elzo-forms'),
]);

// Get form object using Form class
$form = new \ElzoForms\Form\Form($form_id);
$form_post = $form->get_post();

// Break if form is not found or not valid
if (!$form_post || $form_post->post_type !== 'elzo_form') {
    wp_send_json_error([
        'message' => __('Form not found', 'elzo-forms'),
    ]);
}

// Apply the same interaction policy used by rendering, editor previews, and uploads.
if (!$form->can_interact()) {
    wp_send_json_error([
        'message' => __('Form not found', 'elzo-forms'),
    ], 403);
}

// Get form load time and current time
$form_time = isset($_POST['elzo_form_time']) && is_scalar($_POST['elzo_form_time'])
    ? absint(wp_unslash((string) $_POST['elzo_form_time']))
    : 0;
$time = time();

// Get minimum submission delay (seconds) from form settings
$min_submission_delay = intval($form->get_setting('min_submission_delay', 5));

// Break if form submitted too quickly (if minimum delay is enabled)
if($min_submission_delay > 0 && $form_time > 0 && $time - $form_time < $min_submission_delay) {
    wp_send_json_error([
        'message' => __('Form submitted too quickly', 'elzo-forms'),
    ]);
}

// Apply validation filters
// Modules can hook here to validate and reject invalid submissions (validation errors that block processing)
$validation_result = apply_filters('elzo_forms_validate_submission', true, $form, ['submission_time' => $time, 'form_load_time' => $form_time]);
if (is_wp_error($validation_result)) {
    wp_send_json_error([
        'message' => wp_kses_post($validation_result->get_error_message()),
    ]);
}

// Set spam flag
$spam = false;

// Get blocked action
$blocked_action = $form->get_setting('blocked_submission_action', 'spam');

// Get blocked IPs from form settings
$blocked_ips = $form->get_setting('blocked_ips', []);

// Check if IP is blocked
$user_ip = filter_var(
    isset($_SERVER['REMOTE_ADDR']) && is_scalar($_SERVER['REMOTE_ADDR'])
        ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']))
        : '',
    FILTER_VALIDATE_IP
);
if(!$user_ip) {
    wp_send_json_error([
        'message' => __('Invalid IP address', 'elzo-forms'),
    ]);
}

if(in_array($user_ip, $blocked_ips, true)){
    // Check blocked action
    if($blocked_action === 'spam'){
        // Set spam flag
        $spam = true;
    } else {
        // Send error response
        wp_send_json_error([
            'message' => __('Unfortunately, you are not allowed to submit this form', 'elzo-forms'),
        ]);
    }
}

// Get blocked words from form settings
$blocked_words = $form->get_setting('blocked_words', []);

// Get minimum submission interval (seconds) from form settings
$min_submission_interval = intval($form->get_setting('min_submission_interval', 20));

// Check submission throttle if minimum interval is enabled (greater than 0)
if ($min_submission_interval > 0) {
    // Get last submission post within the minimum submission interval
    $last_submission_post = get_posts([
        'post_type' => 'elzo_submission',
        'posts_per_page' => 1,
        'orderby' => 'ID',
        'order' => 'DESC',
        'fields' => 'ids',
        'no_found_rows' => true,
        'cache_results' => false,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to throttle submissions by IP and form.
        'meta_query' => [
            [
                'key' => 'user_ip',
                'value' => $user_ip,
            ],
            [
                'key' => 'form_id',
                'value' => $form_id,
            ]
        ],
        'date_query' => [
            [
                'column' => 'post_date',
                'after' => wp_date('Y-m-d H:i:s', $time - $min_submission_interval),
            ],
        ],
    ]);

    // Ensure last submission post is older than the minimum interval
    if($last_submission_post) wp_send_json_error([
        'message' => __('Submission posted too quickly after the last one', 'elzo-forms'),
    ]);
}

// Apply spam detection filters (e.g., reCAPTCHA, custom spam detection)
// Modules can hook here to set/modify the spam flag based on their own detection logic
// The spam flag is then used to determine blocked_action behavior
$spam = apply_filters('elzo_forms_detect_spam', $spam, $form, ['submission_time' => $time, 'form_load_time' => $form_time]);

// Get submission data. This raw container is short-lived: only values matching
// the stored form schema are read, and each one goes directly through the
// field-specific, side-effect-free sanitize_input() boundary below.
$raw_submission_steps = !empty($_POST['elzo_form_fields']) && is_array($_POST['elzo_form_fields'])
    ? wp_unslash($_POST['elzo_form_fields']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below by each known field's sanitize_input() method before validation, hooks, persistence, logic, or output.
    : null;

// Break if submission data is empty
if(!$raw_submission_steps || !is_array($raw_submission_steps)) wp_send_json_error([
    'message' => __('Submission data is missing or invalid', 'elzo-forms'),
]);

// Resolve the page the form was submitted on once. Field visibility is
// evaluated against it below, and the submission stores it so automations
// running after the request can still tell where the form was filled in.
$request_page_context = \ElzoForms\Utilities\Conditional_Logic::get_request_page_context();

// Initialize submission object
$submission = new \ElzoForms\Submission\Submission();
$submission->set_form_id($form_id)
    ->set_user_data([
        'user_id' => get_current_user_id(),
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) && is_scalar($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT']))
            : '',
        'ip' => $user_ip,
    ])
    ->set_submitted_in($form_time > 0 ? $time - $form_time : 0);

if($request_page_context) $submission->set_page_data([
    'id' => $request_page_context['current_page_id'] ?? 0,
    'url' => $request_page_context['current_url'] ?? '',
]);

// Get form object and data
$steps = $form->steps(); // Get Step objects

// Project the request onto the stored form schema and sanitize every submitted
// value before it can reach extensible validation, conditional logic, filters,
// persistence, notifications, or output. Unknown steps and field IDs are ignored.
$sanitized_submission_steps = [];
foreach($steps as $step_index => $step){
    $fields = $step->fields();
    if(!$fields) continue;

    $raw_submission_fields = (!empty($raw_submission_steps[$step_index]) && is_array($raw_submission_steps[$step_index]))
        ? $raw_submission_steps[$step_index]
        : [];

    foreach($fields as $field){
        if($field->is_read_only()) continue;

        $field_id = (string) $field->get_id();
        if($field_id === '' || !array_key_exists($field_id, $raw_submission_fields)) continue;

        $sanitized_value = $field->sanitize_input($raw_submission_fields[$field_id]);
        if(is_wp_error($sanitized_value)){
            wp_send_json_error([
                'message' => wp_kses_post($sanitized_value->get_error_message()),
            ]);
        }

        $sanitized_submission_steps[$step_index][$field_id] = $sanitized_value;
    }
}
unset($raw_submission_steps, $raw_submission_fields);

// Conditional logic receives only known, sanitized values. Strip any remaining
// allowed markup for plain-text comparisons while preserving percent-encoded
// values supported by text and hidden fields.
$submission_values_for_logic = map_deep($sanitized_submission_steps, 'wp_strip_all_tags');
$submission_values_by_field_id = \ElzoForms\Utilities\Conditional_Logic::flatten_submission_values_by_field_id($submission_values_for_logic);

// Visibility is evaluated against the same context the browser used, so page
// conditions resolve identically on both sides.
$field_logic_context = array_merge(
    ['fields' => $submission_values_by_field_id],
    $request_page_context
);

// Validate sanitized values and stage them without performing side effects.
$validated_submission_fields = [];
foreach($steps as $step_index => $step){
    $fields = $step->fields();
    if(!$fields) continue;

    $submission_fields = (!empty($sanitized_submission_steps[$step_index]) && is_array($sanitized_submission_steps[$step_index]))
        ? $sanitized_submission_steps[$step_index]
        : [];

    foreach($fields as $field){
        if($field->is_read_only()) continue;

        $field_id = (string) $field->get_id();
        if($field_id === '') continue;

        $field_rules = $field->get_logic_rules();
        $field_is_visible = \ElzoForms\Utilities\Conditional_Logic::is_visible($field_rules, $field_logic_context);

        /*
         * Hidden-by-logic fields are not expected in the payload, so they are
         * dropped rather than validated: the value never reaches the stored
         * submission, notifications or automations.
         *
         * Page, URL and cookie conditions rest on context the browser supplies,
         * so a visitor can decide which of those fields count. That is a
         * completeness limit, not an authorization one - see the rule in
         * Conditional_Logic. Nothing here may become the only check protecting
         * a capability, a limit or a side effect.
         */
        if(!$field_is_visible) continue;

        if(array_key_exists($field_id, $submission_fields)){
            $submission_field_value = $submission_fields[$field_id];
        } elseif($field->allows_empty_submission()) {
            $submission_field_value = '';
        } else {
            wp_send_json_error([
                /* translators: 1: Missing field ID, 2: Expected field ID. */
                'message' => sprintf(__('Field %1$s missing or invalid. This one passed: %2$s','elzo-forms'), $field_id, $field_id),
            ]);
        }

        // Extensible validators receive only values that have crossed the
        // field-specific request-boundary sanitizer.
        $validation_result = $field->validate($submission_field_value);
        if(is_wp_error($validation_result)){
            wp_send_json_error([
                'message' => wp_kses_post($validation_result->get_error_message()),
            ]);
        }

        // Check if submission field value contains blocked words
        if(is_string($submission_field_value) && \ElzoForms\Utilities\Blocked_Words::contains_blocked_word($submission_field_value, $blocked_words)){
            // Check blocked action
            if($blocked_action === 'spam'){
                // Set spam flag
                $spam = true;
            } else {
                // Send error response
                wp_send_json_error([
                    'message' => __('Unfortunately, you are not allowed to submit this form', 'elzo-forms'),
                ]);
            }
        }

        // Check if field value is empty (but allow "0" as valid)
        $is_empty = is_array($submission_field_value) ? empty($submission_field_value) : ($submission_field_value === '' || $submission_field_value === null);

        // Apply filter to modify empty check behavior
        $is_empty = apply_filters('elzo_forms_is_field_value_empty', $is_empty, $submission_field_value, $field, $form);

        // Make sure visible required field is not empty
        if($field->is_required() && $is_empty) {
            wp_send_json_error([
                /* translators: %s: Field title in bold. */
                'message' => sprintf(__('Field %s is required','elzo-forms'), '<strong>"' . esc_html($field->get_title()) . '"</strong>'),
            ]);
        }

        $validated_submission_fields[] = [
            'field' => $field,
            'id' => $field_id,
            'value' => $submission_field_value,
        ];
    }
}

// All fields are valid. Only now may field types perform deferred side effects,
// such as moving a verified temporary upload into permanent storage.
$submission_object_fields = [];
foreach($validated_submission_fields as $validated_submission_field){
    $field = $validated_submission_field['field'];
    $submission_field_value = $field->finalize_submission_value($validated_submission_field['value']);
    if(is_wp_error($submission_field_value)){
        // Fields finalize one by one, so a failure here leaves the files of the
        // fields before it in permanent storage with no submission to own them.
        \ElzoForms\Upload\Submission_File_Cleanup::discard_files($submission_object_fields);

        wp_send_json_error([
            'message' => wp_kses_post($submission_field_value->get_error_message()),
        ]);
    }

    $submission_object_fields[] = [
        'id' => $validated_submission_field['id'],
        'field_key' => sanitize_text_field((string) $field->get('field_key', '')),
        'type' => $field->get_type(),
        'admin_label' => $field->get_admin_label(),
        'label' => $field->get_label() ?: $field->get_placeholder(),
        'value' => $submission_field_value,
        'primary_field' => !empty($field->get('primary_field')),
    ];
}
unset($validated_submission_fields, $sanitized_submission_steps);

// Set fields on submission object
$submission->set_fields($submission_object_fields);

// Break if submission data is empty
if(!$submission->get_fields()){
    \ElzoForms\Upload\Submission_File_Cleanup::discard_files($submission_object_fields);

    wp_send_json_error([
        'message' => __('Submission data is missing or invalid', 'elzo-forms'),
    ]);
}

// Mark as spam if needed (before saving)
if($spam){
    $submission->mark_as_spam();
}

// Save submission
$submission_post_id = $submission->save();

// Check for errors
if(is_wp_error($submission_post_id)){
    // The files moved into permanent storage above are owned by a submission
    // that was never stored, so nothing will ever reference them again.
    \ElzoForms\Upload\Submission_File_Cleanup::discard_files($submission_object_fields);

    wp_send_json_error([
        'message' => wp_kses_post($submission_post_id->get_error_message()),
    ]);
}

// Allow extensions (e.g., PRO automations) to run only after a valid submission is saved.
if (!$spam) {
    do_action('elzo_forms_after_submission_saved', $form, $submission, $submission_post_id);
}

// Call module submission handlers
$manager = elzo_forms_modules_manager();
if ($manager) {
    $enabled_modules = $manager->enabled_for_form($form_id);
    foreach ($enabled_modules as $module_id) {
        $module = $manager->get($module_id);
        if ($module) {
            $settings = $manager->settings_for_form($module_id, $form_id);
            $module->handle_submission($submission, $settings);
        }
    }
}

// Get primary field value for email
$primary_field_value = '';
foreach($submission_object_fields as $field){
    if(!empty($field['primary_field']) && !empty($field['value'])){
        $primary_field_value = $field['value'];
        break;
    }
}
if(empty($primary_field_value)){
    foreach($submission_object_fields as $field){
        if(!empty($field['value'])){
            $primary_field_value = $field['value'];
            break;
        }
    }
}
$primary_field_value = is_array($primary_field_value) ? implode(', ', $primary_field_value) : $primary_field_value;

// Exit earlier if spam
if($spam){
    wp_send_json_error([
        'message' => __('Unfortunately, you are not allowed to submit this form', 'elzo-forms'),
    ]);
}

// Send email notification
// Allow runtime overrides (for example, PRO automations) without mutating stored form settings.
$email_settings = [
    'email_notifications' => (string) $form->get_setting('email_notifications'),
    'email_notification_recipients' => $form->get_setting('email_notification_recipients', get_option('admin_email')),
    'email_notification_reply_to' => (string) $form->get_setting('email_notification_reply_to', ''),
];
$email_settings = apply_filters('elzo_forms_submission_email_settings', $email_settings, $form, $submission, (int) $submission_post_id);

$email_notifications = isset($email_settings['email_notifications'])
    ? sanitize_key((string) $email_settings['email_notifications'])
    : 'no';

$notification_templates = null;
if ($email_notifications === 'yes') {
    $email_recipients = $email_settings['email_notification_recipients'] ?? get_option('admin_email');
    if (is_array($email_recipients)) {
        $email_recipients = implode(',', array_map('strval', $email_recipients));
    }
    $subject_template = $form->get_text('email_notification_subject');
    $legacy_subject = !(new \ElzoForms\Variables\ValueResolver())->contains_reference($subject_template);
    $notification_templates = \ElzoForms\Variables\Notification_Templates::prepare(
        $form, $submission, (string) $email_recipients,
        $subject_template, $form->get_text('email_notification_message'),
        (string) ($email_settings['email_notification_reply_to'] ?? '')
    );
    if (!$notification_templates->is_valid()) {
        // A notification failure must not undo a successfully stored submission.
        do_action('elzo_forms_notification_template_failed', $notification_templates->get_errors(), $form, $submission);
    }
    if ($notification_templates->get_warnings()) {
        do_action('elzo_forms_notification_template_warning', $notification_templates->get_warnings(), $form, $submission);
    }
}

if ($notification_templates && $notification_templates->is_valid()) {
    $prepared_notification = $notification_templates->get_value();
    $email_to_list = $prepared_notification['recipients'];
    $email_to = count($email_to_list) === 1 ? $email_to_list[0] : $email_to_list;
    $email_subject = $prepared_notification['subject'];
    if ($legacy_subject) {
        $email_subject = sanitize_text_field($email_subject . (!empty($primary_field_value) ? ' (' . $primary_field_value . ')' : '') . ' | #' . $submission_post_id);
    }
    $email_message = $prepared_notification['message'];
    $email_headers = [
        'Content-Type: text/html; charset=UTF-8',
    ];

    // A Reply-To the submitter can be answered directly; it is left out when
    // the configured address or variable produced nothing usable.
    if (!empty($prepared_notification['reply_to'])) {
        $email_headers[] = 'Reply-To: ' . $prepared_notification['reply_to'];
    }

    // Email buttons
    $email_buttons = [
        [
            'url' => get_edit_post_link($submission_post_id),
            'title' => __('View submission', 'elzo-forms'),
        ],
    ];

    // Get submission data for email template
    $submission_data = $submission->to_array();

    $email_field_presenter = new \ElzoForms\Submission\Submission_Field_Presenter([
        'channel' => 'email',
        'form_id' => $form_id,
        'form' => $form,
        'submission_id' => (int) $submission_post_id,
    ]);

    // Render the email through the template loader so a theme can override
    // email/admin-email.php, and so the template only sees the arguments passed
    // here rather than every local of the enclosing submission handler.
    $email_template = \ElzoForms\Utilities\Template_Loader::get_template('email/admin-email.php', [
        'submission_data' => $submission_data,
        'field_presenter' => $email_field_presenter,
        'email_message' => $email_message,
        'email_fields_in_message' => $prepared_notification['fields_in_message'],
        'email_buttons' => $email_buttons,
        'form_id' => $form_id,
    ]);

    // Allow the rendered email body to be adjusted without copying the template.
    $email_template = apply_filters('elzo_forms_admin_email_html', $email_template, $submission_data, $form, (int) $submission_post_id);

    if (!is_string($email_template)) {
        $email_template = '';
    }

    // Send email
    wp_mail($email_to, $email_subject, $email_template, $email_headers);
}

// Get success message
$message = $form->get_text('success_submit_message');

// Prepare response data
$response = [
    'message' => $message,
    'redirect_url' => $form->get_setting('form_redirect_url', ''),
    'redirect_delay' => $form->get_setting('form_redirect_delay', 0),
    'submission_post_id' => $submission_post_id,
];

// Apply filter to modify response before sending
$response = apply_filters('elzo_forms_submission_response', $response, $submission, $form);

if (!is_array($response)) {
    $response = [];
}

// The front-end renders the message as limited HTML. Enforce that contract
// after extension filters have run, and normalize the remaining response
// values for their eventual URL, integer, and identifier contexts.
$response['message'] = isset($response['message']) && is_scalar($response['message'])
    ? wp_kses_post((string) $response['message'])
    : '';
$response['redirect_url'] = isset($response['redirect_url']) && is_scalar($response['redirect_url'])
    ? esc_url_raw((string) $response['redirect_url'])
    : '';
$response['redirect_delay'] = isset($response['redirect_delay']) && is_scalar($response['redirect_delay'])
    ? absint($response['redirect_delay'])
    : 0;
$response['submission_post_id'] = absint($submission_post_id);

if (wp_doing_ajax()) {
    // Return JSON for AJAX submissions.
    wp_send_json_success($response);
}

// Pass response back to the caller for standard submissions.
$elzo_forms_submission_response = [
    'success' => true,
    'data' => $response,
];
