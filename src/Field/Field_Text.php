<?php
/**
 * Text Field class.
 *
 * @package ElzoForms\Field
 */

namespace ElzoForms\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Field_Text extends Field {

    /**
     * Get field defaults.
     */
    protected function get_defaults(): array {
        return array_merge(parent::get_defaults(), [
            'type' => 'text',
            'subtype' => 'text',
        ]);
    }

    /**
     * Sanitize submitted text before extensible validation runs.
     *
     * Strict subtypes are checked against the original scalar first so that
     * sanitization cannot turn malformed input into a value that happens to
     * pass validation (for example, markup wrapped around an email address).
     *
     * @param mixed $value Raw submitted field value.
     * @return mixed|\WP_Error
     */
    public function sanitize_input($value) {
        $shape_result = $this->validate_input_shape($value);
        if (is_wp_error($shape_result)) {
            return $shape_result;
        }

        $format_result = $this->validate_format($value);
        if (is_wp_error($format_result)) {
            return $format_result;
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

        return $this->validate_format($value);
    }

    /**
     * Validate subtype-specific scalar formats.
     *
     * @param mixed $value Scalar field value.
     * @return true|\WP_Error
     */
    private function validate_format($value) {
        $subtype = $this->get('subtype', 'text');

        // Email validation
        if ($subtype === 'email' && !empty($value)) {
            if (!is_email($value)) {
                return new \WP_Error(
                    'invalid_email',
                    /* translators: %s: Field title. */
                    sprintf(__('Please enter a valid email address for %s', 'elzo-forms'), $this->get_title())
                );
            }
        }

        // URL validation
        if ($subtype === 'url' && !empty($value)) {
            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                return new \WP_Error(
                    'invalid_url',
                    /* translators: %s: Field title. */
                    sprintf(__('Please enter a valid URL for %s', 'elzo-forms'), $this->get_title())
                );
            }
        }

        // Number validation
        if (in_array($subtype, ['number']) && !empty($value)) {
            if (!is_numeric($value)) {
                return new \WP_Error(
                    'invalid_number',
                    /* translators: %s: Field title. */
                    sprintf(__('Please enter a valid number for %s', 'elzo-forms'), $this->get_title())
                );
            }
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
        $subtype = $this->get('subtype', 'text');

        // Email sanitization
        if ($subtype === 'email') {
            return sanitize_email($value);
        }

        // Phone number - allow custom filter
        if ($subtype === 'tel') {
            return apply_filters('elzo_forms_handle_phone_field', sanitize_text_field($value), $this->to_array());
        }

        // Auto-detect URLs
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return esc_url_raw($value);
        }

        // Auto-detect URL-encoded JSON payloads
        $sanitized = $this->sanitize_urlencoded_json($value);
        if ($sanitized !== null) {
            return $sanitized;
        }

        // Default text sanitization
        return sanitize_text_field($value);
    }

    /**
     * Prepare all data for template rendering.
     *
     * @param array $context Additional context (form_settings, etc.)
     * @return array
     */
    public function get_data(array $context = []): array {
        $data = parent::get_data($context);

        // Add text field-specific data
        $data['field_subtype'] = $this->get('subtype', 'text');
        $data['autocomplete'] = $this->get('autocomplete', '');
        $data['input_prepend'] = $this->get('input_prepend');
        $data['input_append'] = $this->get('input_append');

        // Add has-prepend/has-append classes
        if($data['input_prepend'] || $data['input_append']) {
            $data['class'] .= ' elzo-forms-field-input-group-input';
        }
        if($data['input_prepend']) {
            $data['class'] .= ' has-prepend';
        }
        if($data['input_append']) {
            $data['class'] .= ' has-append';
        }

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
            $field_subtypes = \ElzoForms\Utilities\Admin::get_field_text_subtypes();
            $subtype = $this->get('subtype', array_key_first($field_subtypes));
            $placeholder = $this->get('placeholder', '');
            $default_value = $this->get('default_value', '');
            ?>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-subtype-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Subtype', 'elzo-forms'); ?></label>
                <select name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][subtype]" id="elzo-forms-field-subtype-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
                    <?php foreach($field_subtypes as $subtype_key => $subtype_label): ?>
                        <option value="<?php echo esc_attr($subtype_key); ?>" <?php selected($subtype, $subtype_key); ?>><?php echo esc_html($subtype_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-placeholder-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Placeholder', 'elzo-forms'); ?></label>
                <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][placeholder]" value="<?php echo esc_attr($placeholder); ?>" id="elzo-forms-field-placeholder-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control elzo-forms-field-header-part">
            </div>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-default-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Default Value', 'elzo-forms'); ?></label>
                <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][default_value]" value="<?php echo esc_attr($default_value); ?>" id="elzo-forms-field-default-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
            </div>
            <?php
        } elseif ($tab === 'view') {
            $autocomplete = $this->get('autocomplete', '');
            $input_prepend = $this->get('input_prepend', '');
            $input_append = $this->get('input_append', '');
            ?>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-autocomplete-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Autocomplete', 'elzo-forms'); ?></label>
                <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][autocomplete]" value="<?php echo esc_attr($autocomplete); ?>" id="elzo-forms-field-autocomplete-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" placeholder="off">
            </div>
            <div class="elzo-forms-row elzo-forms-row-2-cols elzo-forms-field-control-group">
                <div class="elzo-forms-column">
                    <div class="elzo-forms-field-control-group">
                        <label for="elzo-forms-field-input_prepend-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Input prepend', 'elzo-forms'); ?></label>
                        <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][input_prepend]" id="elzo-forms-field-input_prepend-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" value="<?php echo esc_attr($input_prepend); ?>">
                    </div>
                </div>
                <div class="elzo-forms-column">
                    <div class="elzo-forms-field-control-group">
                        <label for="elzo-forms-field-input_append-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Input append', 'elzo-forms'); ?></label>
                        <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][input_append]" id="elzo-forms-field-input_append-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" value="<?php echo esc_attr($input_append); ?>">
                    </div>
                </div>
            </div>
            <?php
        }

        // Call parent to trigger action hooks for third-party extensions
        parent::render_field_settings($tab);
    }
}
