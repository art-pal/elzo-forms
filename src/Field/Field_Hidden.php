<?php
/**
 * Hidden Field class.
 *
 * @package ElzoForms\Field
 */

namespace ElzoForms\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Field_Hidden extends Field {

    /**
     * Get field defaults.
     */
    protected function get_defaults(): array {
        return array_merge(parent::get_defaults(), [
            'type' => 'hidden',
            'required' => false, // Hidden fields are never required
        ]);
    }

    /**
     * Validate field value.
     *
     * @param mixed $value Field value
     * @return true
     */
    public function validate($value) {
        // Hidden fields don't need validation
        return true;
    }

    /**
     * Sanitize field value.
     *
     * @param mixed $value Field value
     * @return string
     */
    public function sanitize($value) {
        // Auto-detect URLs
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return esc_url_raw($value);
        }

        // Auto-detect URL-encoded JSON payloads
        $sanitized = $this->sanitize_urlencoded_json($value);
        if ($sanitized !== null) {
            return $sanitized;
        }

        return sanitize_text_field($value);
    }

    /**
     * Render field-specific settings in admin for a specific tab.
     *
     * @param string $tab The settings tab (general, view, logic, admin, etc.)
     */
    public function render_field_settings(string $tab): void {
        if ($tab === 'general') {
            $field_id = $this->get_id();
            $step_index = $this->get('step_index', 0);
            $field_index = $this->get('index', 0);
            $default_value = $this->get('default_value', '');
            ?>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-default-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Default Value', 'elzo-forms'); ?></label>
                <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][default_value]" value="<?php echo esc_attr($default_value); ?>" id="elzo-forms-field-default-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
            </div>
            <?php
        }

        // Call parent to trigger action hooks for third-party extensions
        parent::render_field_settings($tab);
    }
}
