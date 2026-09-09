<?php
/**
 * reCAPTCHA v3 Module
 *
 * Protects forms from spam using Google reCAPTCHA v3 (invisible/score-based).
 *
 * @package ElzoForms\Module\Modules\Recaptcha
 */

namespace ElzoForms\Module\Modules\Recaptcha;

use ElzoForms\Module\Module;
use ElzoForms\Module\Manager;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Recaptcha_Module extends Module {

    /** @var string reCAPTCHA verify endpoint */
    const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /** @var array Global settings for the module */
    private array $global_settings = [];

    public function get_id(): string {
        return 'recaptcha';
    }

    public function get_name(): string {
        return __('reCAPTCHA Protection', 'elzo-forms');
    }

    public function get_description(): string {
        return __('Protect forms from spam using Google reCAPTCHA v3 (invisible, score-based). No user interaction required.', 'elzo-forms');
    }

    public function get_version(): string {
        return '1.0.4';
    }

    public function is_compatible(): bool {
        // Check if wp_remote_post is available
        return function_exists('wp_remote_post');
    }

    public function defaults(): array {
        return [
            'site_key' => '',
            'secret_key' => '',
            'score_threshold' => '0.5',
            'action_name' => '',
            'badge_position' => 'bottomright',
            'hide_badge' => false,
            'enable_logging' => false,
        ];
    }

    public function get_configuration_warnings(array $settings, bool $is_enabled, bool $is_global = true): array {
        $warnings = [];

        if (!$is_enabled) {
            return $warnings;
        }

        // Check if API keys are configured
        if (empty($settings['site_key']) || empty($settings['secret_key'])) {
            $warnings[] = __('API keys are not configured. Configure them to enable reCAPTCHA protection.', 'elzo-forms');
        }

        return $warnings;
    }

    public function get_settings_schema(): array {
        return [
            'site_key' => [
                'type' => 'text',
                'label' => __('Site Key', 'elzo-forms'),
                'description' => __('Your reCAPTCHA v3 site key (public). Get it from <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin</a>', 'elzo-forms'),
                'default' => '',
            ],
            'secret_key' => [
                'type' => 'text',
                'label' => __('Secret Key', 'elzo-forms'),
                'description' => __('Your reCAPTCHA v3 secret key (private). Keep this secure!', 'elzo-forms'),
                'default' => '',
            ],
            'score_threshold' => [
                'type' => 'select',
                'label' => __('Score Threshold', 'elzo-forms'),
                'description' => __('Minimum score required (0.0 = bot, 1.0 = human). Recommended: 0.5', 'elzo-forms'),
                'options' => ['0.1', '0.2', '0.3', '0.4', '0.5', '0.6', '0.7', '0.8', '0.9', '1.0'],
                'default' => '0.5',
            ],
            'action_name' => [
                'type' => 'text',
                'label' => __('Action Name', 'elzo-forms'),
                'description' => __('Action identifier for analytics. Leave empty to use form-specific action (form_ID) for better security.', 'elzo-forms'),
                'default' => '',
            ],
            'badge_position' => [
                'type' => 'select',
                'label' => __('Badge Position', 'elzo-forms'),
                'description' => __('Position of the reCAPTCHA badge', 'elzo-forms'),
                'options' => ['bottomright', 'bottomleft', 'inline'],
                'default' => 'bottomright',
            ],
            'hide_badge' => [
                'type' => 'boolean',
                'label' => __('Hide Badge', 'elzo-forms'),
                'description' => __('Hide the reCAPTCHA badge. You must include "This site is protected by reCAPTCHA" in your privacy policy.', 'elzo-forms'),
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
                'description' => __('Log reCAPTCHA verification attempts for debugging', 'elzo-forms'),
                'default' => false,
            ],
        ];
    }

    public function on_register(Manager $manager): void {
        // Add spam detection hook
        add_filter('elzo_forms_detect_spam', [$this, 'detect_spam_recaptcha'], 10, 3);
    }

    public function on_boot(array $global_settings): void {
        // Store global settings for quick access
        $this->global_settings = $global_settings;
    }

    public function on_form_boot(\ElzoForms\Form\Form $form, array $settings): void {
        // Enqueue Google reCAPTCHA script immediately if keys are configured
        if (!empty($settings['site_key'])) {
            $this->enqueue_recaptcha_script($settings);

            // Add reCAPTCHA data to form
            add_filter('elzo_forms_form_data_attributes', function($attributes, $form_id) use ($form, $settings) {
                if ($form->get_id() === $form_id) {
                    $attributes['data-recaptcha-site-key'] = esc_attr($settings['site_key']);

                    // Only add action attribute if custom action is specified
                    if (!empty($settings['action_name'])) {
                        $attributes['data-recaptcha-action'] = esc_attr($settings['action_name']);
                    }
                }
                return $attributes;
            }, 10, 2);
        }
    }

    /**
     * Enqueue frontend assets (custom reCAPTCHA handler script).
     * Called during wp_enqueue_scripts hook by Module Manager.
     */
    public function enqueue_front_assets(): void {
        wp_enqueue_script(
            'elzo-forms-recaptcha',
            plugins_url('assets/recaptcha.js', __FILE__),
            ['elzo-forms-script'],
            $this->get_version(),
            true
        );
    }
    private function enqueue_recaptcha_script(array $settings): void {
        $site_key = $settings['site_key'];
        $badge_position = $settings['badge_position'];
        $hide_badge = $settings['hide_badge'];

        // Enqueue Google reCAPTCHA API
        wp_enqueue_script(
            'google-recaptcha-v3',
            "https://www.google.com/recaptcha/api.js?render={$site_key}",
            [],
            $this->get_version(),
            true
        );

        // Add inline styles for badge positioning
        if ($hide_badge) {
            wp_add_inline_style('elzo-forms-style', '.grecaptcha-badge { visibility: hidden; }');
        } elseif ($badge_position !== 'inline') {
            wp_add_inline_style('elzo-forms-style', ".grecaptcha-badge { {$this->get_badge_css($badge_position)} }");
        }
    }

    /**
     * Get CSS for badge position.
     */
    private function get_badge_css(string $position): string {
        switch ($position) {
            case 'bottomleft':
                return 'left: 4px !important; right: auto !important;';
            case 'bottomright':
            default:
                return 'right: 4px !important; left: auto !important;';
        }
    }

    /**
     * Detect spam using reCAPTCHA verification.
     *
     * Returns true (is spam) if score is below threshold, false (not spam) otherwise.
     */
    public function detect_spam_recaptcha($is_spam, $form, $time_data): bool {
        if (!$form) {
            return $is_spam; // No form, can't validate
        }

        $form_id = $form->get_id();

        // Get module settings for this form
        $manager = elzo_forms_modules_manager();
        if (!$manager) {
            return $is_spam;
        }

        // Check if module is enabled for this form
        $enabled = $manager->enabled_for_form($form_id);
        if (!in_array('recaptcha', $enabled, true)) {
            return $is_spam;
        }

        // Get settings
        $settings = $manager->settings_for_form('recaptcha', $form_id);
        $enable_logging = !empty($settings['enable_logging']);

        // Check if keys are configured
        if (empty($settings['site_key']) || empty($settings['secret_key'])) {
            // Skip verification if not configured
            return $is_spam; // Don't mark as spam if not configured
        }

        // Get reCAPTCHA token from POST
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream in form-handling.php before this filter runs.
        $token = isset($_POST['g-recaptcha-response']) && is_scalar($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash((string) $_POST['g-recaptcha-response'])) : '';

        $reason = '';
        $verification_error = '';
        $spam_detected = $is_spam;

        if (empty($token)) {
            // Missing token is treated as spam (likely a bot)
            $spam_detected = true;
            $reason = 'Missing token';
        } else {
            // Verify token with Google
            $spam_detected = $this->is_spam_by_recaptcha($token, $settings, $reason, $form_id, $verification_error);
        }

        if ($verification_error !== '') {
            if ($enable_logging) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional opt-in logging gated by user-controlled enable_logging setting.
                error_log('Elzo Forms reCAPTCHA verification error: ' . $verification_error . ' | form_id=' . $form_id);
            }

            wp_send_json_error([
                'message' => __('The reCAPTCHA verification service is temporarily unavailable. Please try again later.', 'elzo-forms'),
            ], 503);
        }

        if ($enable_logging) {
            if ($spam_detected) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional opt-in logging gated by user-controlled enable_logging setting.
                error_log('Elzo Forms reCAPTCHA detected spam submission: ' . $reason . ' | form_id=' . $form_id);
            } else {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional opt-in logging gated by user-controlled enable_logging setting.
                error_log('Elzo Forms reCAPTCHA passed verification: ' . $reason . ' | form_id=' . $form_id);
            }
        }

        // If reCAPTCHA detected spam, mark submission as spam
        if ($spam_detected) {
            return true;
        }

        return $is_spam; // Return existing spam status if reCAPTCHA didn't flag it
    }

    /**
     * Check if submission is spam based on reCAPTCHA score.
     *
     * Returns true if spam detected (score below threshold), false otherwise.
     */
    private function is_spam_by_recaptcha(string $token, array $settings, string &$reason = '', int $form_id = 0, string &$verification_error = ''): bool {
        $secret_key = $settings['secret_key'];
        $threshold = floatval($settings['score_threshold']);
        // Generate form-specific action if not set (fallback to 'submit' if form_id is missing)
        $expected_action = !empty($settings['action_name'])
            ? $settings['action_name']
            : ($form_id > 0 ? 'form_' . $form_id : 'submit');
        $enable_logging = !empty($settings['enable_logging']);

        // Prepare request
        $response = wp_remote_post(self::VERIFY_URL, [
            'body' => [
                'secret' => $secret_key,
                'response' => $token,
                'remoteip' => $this->get_user_ip($settings),
            ],
            'timeout' => 10,
        ]);

        // Check for HTTP errors
        if (is_wp_error($response)) {
            $reason = 'HTTP error: ' . $response->get_error_message();
            $verification_error = $reason;
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code < 200 || $response_code >= 300) {
            $reason = 'HTTP status ' . $response_code;
            $verification_error = $reason;
            return false;
        }

        // Parse response
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!is_array($result)) {
            $reason = 'Invalid JSON response';
            $verification_error = $reason;
            return false;
        }

        // Compose reason only; avoid verbose multi-line logs

        // Check if verification succeeded
        if (empty($result['success'])) {
            $error_codes = isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'unknown';
            $reason = 'Verification failed: ' . $error_codes;
            // Verification failed, assume spam
            return true;
        }

        // Verify action
        if (!empty($result['action']) && $result['action'] !== $expected_action) {
            $reason = 'Action mismatch: expected=' . $expected_action . ', got=' . $result['action'];
            // Action mismatch, assume spam
            return true;
        }

        // Check score
        $score = isset($result['score']) ? floatval($result['score']) : 0.0;

        if ($score < $threshold) {
            $reason = sprintf('Score below threshold: score=%.2f, threshold=%.2f', $score, $threshold);
            return true; // Spam detected
        }

        // Not spam: set success reason for single-line log
        $reason = sprintf('Score OK: score=%.2f%s', $score, !empty($result['action']) ? ', action=' . $result['action'] : '');
        return false; // Not spam
    }

    /**
     * Get user IP address.
     *
     * Proxy headers are sent by the client and can be forged, so they are only
     * consulted when the site owner has confirmed the site sits behind a trusted
     * proxy. Otherwise only REMOTE_ADDR, which the web server sets, is used.
     *
     * @param array $settings Module settings for the current form.
     */
    private function get_user_ip(array $settings = []): string {
        // Proxy headers are opt-in; REMOTE_ADDR is always the trusted fallback.
        $ip_keys = empty($settings['trust_proxy_headers'])
            ? ['REMOTE_ADDR']
            : [
                'HTTP_CF_CONNECTING_IP', // Cloudflare
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_REAL_IP',
                'REMOTE_ADDR',
            ];

        foreach ($ip_keys as $key) {
            if (empty($_SERVER[$key]) || !is_scalar($_SERVER[$key])) {
                continue;
            }

            $ip = sanitize_text_field(wp_unslash((string) $_SERVER[$key]));
            if ($ip !== '') {
                // Handle comma-separated IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '';
    }

    public function handle_submission(\ElzoForms\Submission\Submission $submission, array $settings): void {
        // reCAPTCHA detection happens in detect_spam_recaptcha filter
        // This method is called after successful submission processing

        // Optionally store reCAPTCHA score in submission meta
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream in form-handling.php before handle_submission() is called.
        $token = isset($_POST['g-recaptcha-response']) && is_scalar($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash((string) $_POST['g-recaptcha-response'])) : '';

        if (!empty($settings['enable_logging']) && $token !== '') {
            // Re-verify to get the score (already validated, just need score)
            $response = wp_remote_post(self::VERIFY_URL, [
                'body' => [
                    'secret' => $settings['secret_key'],
                    'response' => $token,
                ],
                'timeout' => 10,
                'blocking' => true,
            ]);

            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code < 200 || $response_code >= 300) {
                    return;
                }

                $body = wp_remote_retrieve_body($response);
                $result = json_decode($body, true);
                if (is_array($result) && !empty($result['success']) && isset($result['score'])) {
                    update_post_meta($submission->get_id(), '_recaptcha_score', $result['score']);
                }
            }
        }
    }
}
