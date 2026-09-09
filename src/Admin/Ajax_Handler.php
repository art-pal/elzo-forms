<?php
/**
 * Admin AJAX Handler class.
 *
 * Handles AJAX requests from the admin area.
 *
 * @package ElzoForms\Admin
 */

namespace ElzoForms\Admin;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Ajax_Handler {

    /**
     * Register AJAX handlers.
     */
    public static function init(): void {
        add_action('wp_ajax_elzo_forms', [__CLASS__, 'handle_field_settings_request']);
    }

    /**
     * Handle field settings AJAX request.
     *
     * Returns HTML for field-specific settings when field type changes in admin.
     */
    public static function handle_field_settings_request(): void {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'code' => 'unauthorized',
                'message' => __('Unauthorized', 'elzo-forms'),
            ], 403);
        }

        // Keep the existing JSON error contract while using WordPress's AJAX nonce verifier.
        if (!check_ajax_referer('elzo_forms_admin', 'nonce', false)) {
            wp_send_json_error([
                'code' => 'invalid_nonce',
                'message' => __('Invalid nonce', 'elzo-forms'),
            ], 403);
        }

        $field_type = isset($_POST['field_type']) && is_scalar($_POST['field_type'])
            ? sanitize_key(wp_unslash((string) $_POST['field_type']))
            : '';

        // Validate field type
        if ($field_type === '') {
            wp_send_json_error([
                'message' => __('Empty field type', 'elzo-forms'),
            ]);
        }

        $step_index = isset($_POST['step_index']) && is_scalar($_POST['step_index'])
            ? absint(wp_unslash((string) $_POST['step_index']))
            : 0;
        $field_index = isset($_POST['field_index']) && is_scalar($_POST['field_index'])
            ? absint(wp_unslash((string) $_POST['field_index']))
            : 0;
        $posted_field_id = isset($_POST['field_id']) && is_scalar($_POST['field_id'])
            ? sanitize_text_field(wp_unslash((string) $_POST['field_id']))
            : '';

        /*
         * Field IDs are only ever echoed into markup, and forms created outside
         * the admin UI may use readable string IDs, so keep the posted ID when it
         * is a plain machine name. The re-rendered settings markup has to keep
         * using the same ID as the field already in the DOM, otherwise its input
         * IDs, labels and conditional-logic references stop matching the field.
         */
        $field_id = preg_match('/^[A-Za-z0-9_-]{1,64}$/', $posted_field_id) === 1
            ? $posted_field_id
            : \ElzoForms\Field\Field::generate_id();

        // Create field object
        $field_data = [
            'type' => $field_type,
            'id' => $field_id,
            'step_index' => $step_index,
            'index' => $field_index,
        ];

        try {
            $field_object = \ElzoForms\Field\Field::from($field_data);

            // Generate HTML for each settings tab
            $response = [
                'message' => __('Field settings loaded', 'elzo-forms'),
                'field' => $field_object->get_admin_field_data(),
                'html' => [
                    'general' => self::get_settings_html($field_object, 'general'),
                    'view' => self::get_settings_html($field_object, 'view'),
                ],
            ];

        } catch (\Exception $e) {
            /* translators: %s: Error message. */
            $message = sprintf(__('Error loading field settings: %s', 'elzo-forms'), $e->getMessage());

            wp_send_json_error([
                'message' => $message,
            ]);
        }

        wp_send_json_success($response);
    }

    /**
     * Get field settings HTML for a specific tab.
     *
     * @param \ElzoForms\Field\Field $field_object Field instance
     * @param string $tab Settings tab name
     * @return string HTML output
     */
    protected static function get_settings_html($field_object, string $tab): string {
        ob_start();
        $field_object->render_field_settings($tab);
        return ob_get_clean();
    }

}
