<?php
/**
 * Radio Field class.
 *
 * @package ElzoForms\Field
 */

namespace ElzoForms\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Field_Radio extends Field {

    /**
     * Get field defaults.
     */
    protected function get_defaults(): array {
        return array_merge(parent::get_defaults(), [
            'type' => 'radio',
            'options' => [],
        ]);
    }

    /**
     * Sanitize submitted input before extensible validation runs.
     *
     * The submitted value is matched against the configured options while it is
     * still untouched, so stripping markup cannot turn an unknown payload into
     * an empty value that passes validation.
     *
     * @param mixed $value Raw submitted field value.
     * @return mixed|\WP_Error
     */
    public function sanitize_input($value) {
        $shape_result = $this->validate_input_shape($value);
        if (is_wp_error($shape_result)) {
            return $shape_result;
        }

        $options_result = $this->validate_options($value);
        if (is_wp_error($options_result)) {
            return $options_result;
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
        return $this->validate_options($value);
    }

    /**
     * Validate a value against the configured options.
     *
     * @param mixed $value Field value
     * @return true|\WP_Error
     */
    private function validate_options($value) {
        // Validate against available options
        $options = $this->get('options', []);
        $option_values = array_column($options, 'value');

        if (!empty($value) && !in_array($value, $option_values, true)) {
            return new \WP_Error(
                'invalid_option',
                /* translators: 1: Field title, 2: Invalid value, 3: Valid option list. */
                sprintf(__('Invalid option selected for %1$s. Value "%2$s" is not a valid option. Valid options are: %3$s', 'elzo-forms'), $this->get_title(), $value, implode(', ', $option_values))
            );
        }

        return true;
    }

    /**
     * Check if field allows empty submission.
     * Checkboxes are not included in POST when unchecked.
     */
    public function allows_empty_submission(): bool {
        return true;
    }

    /**
     * Sanitize field value.
     *
     * @param mixed $value Field value
     * @return string
     */
    public function sanitize($value) {
        return sanitize_text_field($value);
    }

    /**
     * Get allowed operators for conditional logic.
     *
     * @return array|null
     */
    public function get_logic_operators(): ?array {
        return $this->get_string_logic_operators();
    }

    /**
     * Prepare data for template rendering.
     *
     * @param array $context Additional context data
     * @return array Data to be extracted in template
     */
    public function get_data(array $context = []): array {
        $data = parent::get_data($context);

        $data['options'] = $this->get('options', []);
        $data['layout'] = $this->get('layout', 'vertical');

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
            $options = \ElzoForms\Utilities\Admin::options_to_string($this->get('options', []));
            $default_value = $this->get('default_value', '');
            ?>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-options-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Options', 'elzo-forms'); ?></label>
                <?php /* translators: %s: Option markup example shown to admins. */ ?>
                <div class="elzo-forms-field-control-guideline"><?php echo sprintf(esc_html__('Enter each option on a new line. Markup: %s (label and description are optional)', 'elzo-forms'), '<code>'.esc_html__('value : label || description', 'elzo-forms').'</code>'); ?></div>
                <textarea name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][options]" id="elzo-forms-field-options-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" rows="3"><?php echo esc_textarea($options); ?></textarea>
            </div>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-default-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Default Value', 'elzo-forms'); ?></label>
                <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][default_value]" value="<?php echo esc_attr($default_value); ?>" id="elzo-forms-field-default-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
            </div>
            <?php
        } elseif ($tab === 'view') {
            $layouts = \ElzoForms\Utilities\Admin::get_field_radio_checkbox_layouts();
            $layout = $this->get('layout', array_key_first($layouts));
            ?>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-layout-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Layout', 'elzo-forms'); ?></label>
                <select name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][layout]" class="elzo-forms-field-control elzo-forms-field-layout-select" id="elzo-forms-field-layout-<?php echo esc_attr($field_id); ?>">
                    <?php foreach($layouts as $option_value => $option_label): ?>
                        <option value="<?php echo esc_attr($option_value); ?>" <?php selected($layout, $option_value); ?>><?php echo esc_html($option_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
        }

        parent::render_field_settings($tab);
    }
}
