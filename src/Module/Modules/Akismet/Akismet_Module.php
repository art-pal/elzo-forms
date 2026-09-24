<?php
/**
 * Akismet Module.
 *
 * Protects forms from spam using Akismet's comment-check API.
 *
 * @package ElzoForms\Module\Modules\Akismet
 */

namespace ElzoForms\Module\Modules\Akismet;

use ElzoForms\Module\Manager;
use ElzoForms\Module\Module;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Akismet_Module extends Module {
    /** @var string Akismet spam check endpoint */
    const COMMENT_CHECK_URL = 'https://rest.akismet.com/1.1/comment-check';

    /** @var array Last check result per form ID, used for optional submission meta logging */
    private array $last_results = [];

    public function get_id(): string {
        return 'akismet';
    }

    public function get_name(): string {
        return __('Akismet Protection', 'elzo-forms');
    }

    public function get_description(): string {
        return __('Protect forms from spam using Akismet content analysis for contact form submissions.', 'elzo-forms');
    }

    public function get_version(): string {
        return '1.0.0';
    }

    public function is_compatible(): bool {
        return function_exists('wp_remote_post');
    }

    public function defaults(): array {
        return [
            'api_key' => '',
            'treat_errors_as_spam' => false,
            'test_mode' => false,
            'enable_logging' => false,
        ];
    }

    public function get_configuration_warnings(array $settings, bool $is_enabled, bool $is_global = true): array {
        $warnings = [];

        if (!$is_enabled) {
            return $warnings;
        }

        if (empty($settings['api_key'])) {
            $warnings[] = __('Akismet API key is not configured. Configure it to enable Akismet protection.', 'elzo-forms');
        }

        return $warnings;
    }

    public function get_settings_schema(): array {
        return [
            'api_key' => [
                'type' => 'text',
                'label' => __('API Key', 'elzo-forms'),
                'description' => __('Your Akismet API key. Get it from <a href="https://akismet.com/account/" target="_blank">Akismet Account</a>.', 'elzo-forms'),
                'default' => '',
                'sensitive' => true,
            ],
            'treat_errors_as_spam' => [
                'type' => 'boolean',
                'label' => __('Treat API Errors as Spam', 'elzo-forms'),
                'description' => __('Mark submissions as spam when Akismet cannot be reached or returns an unexpected response. Leave disabled to avoid blocking valid submissions during service outages.', 'elzo-forms'),
                'default' => false,
            ],
            'test_mode' => [
                'type' => 'boolean',
                'label' => __('Test Mode', 'elzo-forms'),
                'description' => __('Send Akismet requests with the is_test flag. Use only while testing your integration.', 'elzo-forms'),
                'default' => false,
            ],
            'trust_proxy_headers' => [
                'type' => 'boolean',
                'label' => __('Trust Proxy Headers for Visitor IP', 'elzo-forms'),
                'description' => __('Read the visitor IP from the Cloudflare, X-Forwarded-For and X-Real-IP headers. Enable only when the site sits behind a trusted proxy or CDN: these headers are sent by the client and can be forged.', 'elzo-forms'),
                'default' => false,
            ],
            'enable_logging' => [
                'type' => 'boolean',
                'label' => __('Enable Logging', 'elzo-forms'),
                'description' => __('Log Akismet verification results to the PHP error log and store the result on the submission for debugging.', 'elzo-forms'),
                'default' => false,
            ],
        ];
    }

    public function on_register(Manager $manager): void {
        add_filter('elzo_forms_detect_spam', [$this, 'detect_spam_akismet'], 10, 3);
    }

    /**
     * Detect spam using Akismet.
     *
     * @param bool                     $is_spam Existing spam state.
     * @param \ElzoForms\Form\Form|null $form Form instance.
     * @param array                    $time_data Submission timing context.
     * @return bool
     */
    public function detect_spam_akismet($is_spam, $form, $time_data): bool {
        unset($time_data);

        if (!$form) {
            return $is_spam;
        }

        $form_id = $form->get_id();
        $manager = elzo_forms_modules_manager();
        if (!$manager) {
            return $is_spam;
        }

        $enabled = $manager->enabled_for_form($form_id);
        if (!in_array('akismet', $enabled, true)) {
            return $is_spam;
        }

        $settings = $manager->settings_for_form('akismet', $form_id);
        if (empty($settings['api_key'])) {
            return $is_spam;
        }

        $request_data = $this->build_request_data($form, $settings);
        if (empty($request_data['comment_content'])) {
            $this->last_results[$form_id] = [
                'result' => 'skipped',
                'reason' => 'No submitted text content',
                'is_spam' => $is_spam,
            ];

            return $is_spam;
        }

        $reason = '';
        $result = '';
        $spam_detected = $this->is_spam_by_akismet($request_data, $settings, $reason, $result);

        $this->last_results[$form_id] = [
            'result' => $result,
            'reason' => $reason,
            'is_spam' => $spam_detected,
        ];

        if (!empty($settings['enable_logging'])) {
            $log_message = sprintf(
                'Elzo Forms Akismet %s: %s | form_id=%d',
                $spam_detected ? 'detected spam' : 'passed verification',
                $reason,
                $form_id
            );
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional opt-in logging gated by user-controlled enable_logging setting.
            error_log($log_message);
        }

        if ($spam_detected) {
            return true;
        }

        return $is_spam;
    }

    /**
     * Build Akismet request data from the current posted form submission.
     */
    private function build_request_data(\ElzoForms\Form\Form $form, array $settings): array {
        $posted_steps = [];
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream in form-handling.php before this filter runs.
        if (isset($_POST['elzo_form_fields']) && is_array($_POST['elzo_form_fields'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream in form-handling.php before this filter runs.
            $posted_steps = map_deep(wp_unslash($_POST['elzo_form_fields']), 'sanitize_textarea_field');
        }

        if (!is_array($posted_steps)) {
            $posted_steps = [];
        }

        $submitted_fields = $this->collect_submitted_fields($form, $posted_steps);
        $referrer = $this->get_referrer();
        $home_url = function_exists('home_url') ? home_url('/') : '';

        $data = [
            'api_key' => $settings['api_key'],
            'blog' => $home_url,
            'user_ip' => $this->get_user_ip($settings),
            'user_agent' => $this->get_user_agent(),
            'referrer' => $referrer,
            'permalink' => $referrer ?: $home_url,
            'comment_type' => 'contact-form',
            'comment_author' => $this->find_author_value($submitted_fields),
            'comment_author_email' => $this->find_email_value($submitted_fields),
            'comment_author_url' => $this->find_url_value($submitted_fields),
            'comment_content' => $this->build_comment_content($submitted_fields),
            'comment_date_gmt' => gmdate('c'),
            'blog_lang' => $this->get_blog_language(),
            'blog_charset' => $this->get_blog_charset(),
        ];

        if (!empty($settings['test_mode'])) {
            $data['is_test'] = 'true';
        }

        $data = array_filter($data, static function ($value): bool {
            return $value !== '' && $value !== null && $value !== [];
        });

        return apply_filters('elzo_forms/akismet/request_data', $data, $form, $settings, $submitted_fields);
    }

    /**
     * Collect text-like submitted fields that are useful for Akismet.
     */
    private function collect_submitted_fields(\ElzoForms\Form\Form $form, array $posted_steps): array {
        $submitted_fields = [];
        $values_by_field_id = \ElzoForms\Utilities\Conditional_Logic::flatten_submission_values_by_field_id($posted_steps);
        $logic_context = array_merge(
            ['fields' => $values_by_field_id],
            \ElzoForms\Utilities\Conditional_Logic::get_request_page_context()
        );

        foreach ($form->steps() as $step_index => $step) {
            $step_fields = (!empty($posted_steps[$step_index]) && is_array($posted_steps[$step_index]))
                ? $posted_steps[$step_index]
                : [];

            foreach ($step->fields() as $field) {
                if ($field->is_read_only() || $this->should_skip_field($field)) {
                    continue;
                }

                $field_id = (string) $field->get_id();
                if ($field_id === '') {
                    continue;
                }

                if (!\ElzoForms\Utilities\Conditional_Logic::is_visible($field->get_logic_rules(), $logic_context)) {
                    continue;
                }

                if (array_key_exists($field_id, $step_fields)) {
                    $value = $step_fields[$field_id];
                } elseif ($field->allows_empty_submission()) {
                    $value = '';
                } else {
                    continue;
                }

                $value = $this->normalize_submission_value($value);
                if ($value === '') {
                    continue;
                }

                $submitted_fields[] = [
                    'id' => $field_id,
                    'key' => (string) $field->get_field_key(),
                    'label' => $this->get_field_label($field),
                    'type' => $field->get_base_type(),
                    'subtype' => $field->get_subtype(),
                    'value' => $value,
                ];
            }
        }

        return apply_filters('elzo_forms/akismet/submitted_fields', $submitted_fields, $form, $posted_steps);
    }

    /**
     * Check whether an input field should be excluded from Akismet payloads.
     */
    private function should_skip_field(\ElzoForms\Field\Field $field): bool {
        return $field->get_base_type() === 'file' || $field->get_subtype() === 'password';
    }

    /**
     * Normalize submitted values into readable text.
     *
     * @param mixed $value Submitted value.
     */
    private function normalize_submission_value($value): string {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                $normalized = $this->normalize_submission_value($item);
                if ($normalized !== '') {
                    $parts[] = $normalized;
                }
            }

            return implode(', ', $parts);
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (!is_scalar($value)) {
            return '';
        }

        return sanitize_textarea_field((string) $value);
    }

    /**
     * Get a human-readable field label.
     */
    private function get_field_label(\ElzoForms\Field\Field $field): string {
        $label = $field->get_admin_label() ?: $field->get_label();
        $label = $label ?: $field->get_placeholder();
        $label = $label ?: $field->get_field_key();

        return $label ?: (string) $field->get_id();
    }

    /**
     * Find the likely submitter name.
     */
    private function find_author_value(array $submitted_fields): string {
        foreach ($submitted_fields as $field) {
            if ($this->field_identity_matches($field, ['name', 'full name', 'first name', 'last name', 'your name', 'contact name'])) {
                return sanitize_text_field($field['value']);
            }
        }

        return '';
    }

    /**
     * Find the likely submitter email.
     */
    private function find_email_value(array $submitted_fields): string {
        foreach ($submitted_fields as $field) {
            if ($field['subtype'] === 'email' || $this->field_identity_matches($field, ['email', 'e mail'])) {
                $email = sanitize_email($field['value']);
                if ($email !== '') {
                    return $email;
                }
            }
        }

        return '';
    }

    /**
     * Find the likely submitter URL.
     */
    private function find_url_value(array $submitted_fields): string {
        foreach ($submitted_fields as $field) {
            if ($field['subtype'] !== 'url' && !$this->field_identity_matches($field, ['url', 'website', 'web site', 'homepage'])) {
                continue;
            }

            $url = esc_url_raw($field['value']);
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    /**
     * Build the submitted content body sent to Akismet.
     */
    private function build_comment_content(array $submitted_fields): string {
        $lines = [];

        foreach ($submitted_fields as $field) {
            if ($this->is_contact_identity_field($field)) {
                continue;
            }

            $lines[] = sprintf('%s: %s', $field['label'], $field['value']);
        }

        if (empty($lines)) {
            foreach ($submitted_fields as $field) {
                $lines[] = sprintf('%s: %s', $field['label'], $field['value']);
            }
        }

        return implode("\n", array_unique($lines));
    }

    /**
     * Check if a field is already represented by author/email/url Akismet fields.
     */
    private function is_contact_identity_field(array $field): bool {
        return $field['subtype'] === 'email'
            || $field['subtype'] === 'url'
            || $this->field_identity_matches($field, ['name', 'full name', 'first name', 'last name', 'your name', 'contact name', 'email', 'e mail', 'url', 'website', 'web site', 'homepage']);
    }

    /**
     * Match field identity against normalized words from key/label/type/subtype.
     */
    private function field_identity_matches(array $field, array $terms): bool {
        $identity = strtolower(implode(' ', [
            $field['key'] ?? '',
            $field['label'] ?? '',
            $field['type'] ?? '',
            $field['subtype'] ?? '',
        ]));
        $identity = preg_replace('/[^a-z0-9]+/', ' ', $identity) ?? '';
        $identity = ' ' . trim($identity) . ' ';

        foreach ($terms as $term) {
            $term = ' ' . strtolower(preg_replace('/[^a-z0-9]+/', ' ', $term) ?? '') . ' ';
            if ($term !== '  ' && strpos($identity, $term) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Call Akismet and return true when it reports spam.
     */
    private function is_spam_by_akismet(array $request_data, array $settings, string &$reason = '', string &$result = ''): bool {
        $response = wp_remote_post(self::COMMENT_CHECK_URL, [
            'body' => $request_data,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
                'User-Agent' => $this->get_akismet_user_agent(),
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            $result = 'error';
            $reason = 'HTTP error: ' . $response->get_error_message();
            return !empty($settings['treat_errors_as_spam']);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code && $response_code >= 400) {
            $result = 'error';
            $reason = 'HTTP status ' . $response_code;
            return !empty($settings['treat_errors_as_spam']);
        }

        $body = trim(wp_remote_retrieve_body($response));
        if ($body === 'true') {
            $result = 'spam';
            $reason = 'Akismet returned spam';
            return true;
        }

        if ($body === 'false') {
            $result = 'ham';
            $reason = 'Akismet returned ham';
            return false;
        }

        $result = 'error';
        $reason = 'Unexpected Akismet response: ' . ($body !== '' ? $body : 'empty');
        return !empty($settings['treat_errors_as_spam']);
    }

    /**
     * Get visitor IP address.
     *
     * Proxy headers are sent by the client and can be forged, so they are only
     * consulted when the site owner has confirmed the site sits behind a trusted
     * proxy. Otherwise only REMOTE_ADDR, which the web server sets, is used.
     *
     * @param array $settings Module settings for the current form.
     */
    private function get_user_ip(array $settings = []): string {
        $ip_keys = empty($settings['trust_proxy_headers'])
            ? ['REMOTE_ADDR']
            : [
                'HTTP_CF_CONNECTING_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_REAL_IP',
                'REMOTE_ADDR',
            ];

        foreach ($ip_keys as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }

            if (!is_scalar($_SERVER[$key])) {
                continue;
            }

            $ip = sanitize_text_field(wp_unslash((string) $_SERVER[$key]));
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '';
    }

    /**
     * Get visitor user agent.
     */
    private function get_user_agent(): string {
        return isset($_SERVER['HTTP_USER_AGENT']) && is_scalar($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT']))
            : '';
    }

    /**
     * Get HTTP referrer.
     */
    private function get_referrer(): string {
        return isset($_SERVER['HTTP_REFERER']) && is_scalar($_SERVER['HTTP_REFERER'])
            ? esc_url_raw(wp_unslash((string) $_SERVER['HTTP_REFERER']))
            : '';
    }

    /**
     * Get site language for Akismet.
     */
    private function get_blog_language(): string {
        if (function_exists('get_locale')) {
            return strtolower(str_replace('_', '-', (string) get_locale()));
        }

        if (function_exists('get_bloginfo')) {
            return strtolower(str_replace('_', '-', (string) get_bloginfo('language')));
        }

        return '';
    }

    /**
     * Get site charset for Akismet.
     */
    private function get_blog_charset(): string {
        if (function_exists('get_bloginfo')) {
            $charset = (string) get_bloginfo('charset');
            return $charset !== '' ? $charset : 'UTF-8';
        }

        return 'UTF-8';
    }

    /**
     * Get integration user agent.
     */
    private function get_akismet_user_agent(): string {
        $version = defined('ELZO_FORMS_VERSION') ? ELZO_FORMS_VERSION : $this->get_version();

        return 'WordPress | Elzo Forms/' . $version . ' | Akismet Module/' . $this->get_version();
    }

    public function handle_submission(\ElzoForms\Submission\Submission $submission, array $settings): void {
        if (empty($settings['enable_logging'])) {
            return;
        }

        $form_id = $submission->get_form_id();
        if (empty($this->last_results[$form_id])) {
            return;
        }

        $result = $this->last_results[$form_id];
        update_post_meta($submission->get_id(), '_akismet_result', $result['result'] ?? '');
        update_post_meta($submission->get_id(), '_akismet_reason', $result['reason'] ?? '');
    }
}
