<?php
/**
 * Textarea Field class.
 *
 * @package ElzoForms\Field
 */

namespace ElzoForms\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Field_Textarea extends Field {

    /**
     * Get field defaults.
     */
    protected function get_defaults(): array {
        return array_merge(parent::get_defaults(), [
            'type' => 'textarea',
        ]);
    }

    /**
     * Sanitize submitted textarea content before extensible validation runs.
     *
     * The raw scalar length is checked first so removing markup cannot make an
     * over-limit value valid. Newlines and supported URL-encoded payloads are
     * preserved by the field-specific sanitizer.
     *
     * @param mixed $value Raw submitted field value.
     * @return mixed|\WP_Error
     */
    public function sanitize_input($value) {
        $shape_result = $this->validate_input_shape($value);
        if (is_wp_error($shape_result)) {
            return $shape_result;
        }

        $length_result = $this->validate_length($value);
        if (is_wp_error($length_result)) {
            return $length_result;
        }

        return $this->sanitize($value);
    }

    /**
     * Validate field value.
     *
     * @param mixed $value Field value
     * @return true|\WP_Error
     */
    public function validate($value) {
        $shape_result = $this->validate_input_shape($value);
        if (is_wp_error($shape_result)) {
            return $shape_result;
        }

        return $this->validate_length($value);
    }

    /**
     * Validate the configured maximum length.
     *
     * @param mixed $value Scalar textarea value.
     * @return true|\WP_Error
     */
    private function validate_length($value) {
        // Optional: Add max length validation
        $max_length = $this->get('max_length');

        if ($max_length && !empty($value) && mb_strlen((string) $value) > $max_length) {
            return new \WP_Error(
                'value_too_long',
                /* translators: 1: Field title, 2: Maximum allowed character count. */
                sprintf(__('%1$s exceeds maximum length of %2$d characters', 'elzo-forms'), $this->get_title(), $max_length)
            );
        }

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
        $sanitized = $this->sanitize_urlencoded_json($value, 'sanitize_textarea_field');
        if ($sanitized !== null) {
            return $sanitized;
        }

        return sanitize_textarea_field($value);
    }

    /**
     * Prepare data for template rendering.
     *
     * @param array $context Additional context data
     * @return array Data to be extracted in template
     */
    public function get_data(array $context = []): array {
        $data = parent::get_data($context);

        $data['rows_amount'] = $this->get('rows_amount', 3);
        $data['max_length'] = absint($this->get('max_length', 0));

        return $data;
    }

    /**
     * Render field-specific settings in admin for a specific tab.
     *
     * @param string $tab The settings tab (general, view, logic, admin, etc.)
     */
    public function render_field_settings(string $tab): void {
        $field = $this->to_array();
        $field_id = $this->get_id();
        $step_index = $this->get('step_index', 0);
        $field_index = $this->get('index', 0);

        if ($tab === 'general') {
            $placeholder = $this->get('placeholder', '');
            $default_value = $this->get('default_value', '');
            $rows_amount = $this->get('rows_amount', 3);
            $max_length = !empty($this->get('max_length')) ? absint($this->get('max_length')) : '';
            ?>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-placeholder-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Placeholder', 'elzo-forms'); ?></label>
                <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][placeholder]" value="<?php echo esc_attr($placeholder); ?>" id="elzo-forms-field-placeholder-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control elzo-forms-field-header-part">
            </div>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-default-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Default Value', 'elzo-forms'); ?></label>
                <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][default_value]" value="<?php echo esc_attr($default_value); ?>" id="elzo-forms-field-default-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
            </div>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-rows-amount-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Rows Amount', 'elzo-forms'); ?></label>
                <input type="number" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][rows_amount]" value="<?php echo esc_attr($rows_amount); ?>" id="elzo-forms-field-rows-amount-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
            </div>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-max-length-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Maximum length', 'elzo-forms'); ?></label>
                <input type="number" min="1" step="1" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][max_length]" value="<?php echo esc_attr($max_length); ?>" id="elzo-forms-field-max-length-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" placeholder="-">
            </div>
            <?php
        }

        parent::render_field_settings($tab);
    }
}
