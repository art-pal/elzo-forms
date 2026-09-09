<?php
/**
 * Select Field class.
 *
 * @package ElzoForms\Field
 */

namespace ElzoForms\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Field_Select extends Field {

    /**
     * Get field defaults.
     */
    protected function get_defaults(): array {
        return array_merge(parent::get_defaults(), [
            'type' => 'select',
            'options' => [],
            'multiple' => false,
        ]);
    }

    /**
     * Select fields support multiple values when 'multiple' is enabled.
     *
     * @return bool
     */
    protected function is_multiple(): bool {
        return !empty($this->data['multiple']);
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
     * Select fields compare against their configured options.
     *
     * @return string
     */
    public function get_logic_value_source(): string {
        return !empty($this->get_logic_value_options()) ? 'options' : 'text';
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

        if (!empty($value)) {
            $values_to_check = ($this->is_multiple() && is_array($value)) ? $value : [$value];

            foreach ($values_to_check as $val) {
                if (!in_array($val, $option_values, true)) {
                    $val_display = is_scalar($val) ? (string) $val : wp_json_encode($val);
                    return new \WP_Error(
                        'invalid_option',
                        /* translators: 1: Field title, 2: Invalid value, 3: Valid option list. */
                        sprintf(__('Invalid option selected for %1$s. Value is %2$s, but valid options are: %3$s', 'elzo-forms'), '<strong>"' . $this->get_title() . '"</strong>', '<strong>"' . $val_display . '"</strong>', implode(', ', $option_values))
                    );
                }
            }
        }

        return true;
    }

    /**
     * Sanitize field value.
     *
     * @param mixed $value Field value
     * @return array|string
     */
    public function sanitize($value) {
        if ($this->is_multiple() && is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }

        return sanitize_text_field($value);
    }

    /**
     * Get data for template rendering.
     *
     * @param array $context Additional context data
     * @return array Data to be extracted in template
     */
    public function get_data(array $context = []): array {
        $data = parent::get_data($context);

        $options = $this->get('options', []);
        $multiple = $this->is_multiple();
        $search = !empty($this->get('search'));
        $value = $data['value'];

        // Convert value into string if not multiple
        if (!$multiple && is_array($value)) {
            $value = $value[array_key_first($value)] ?? '';
        }

        // Calculate value label for single select
        $value_label_index = $value && !$multiple ? array_search($value, array_column($options, 'value')) : false;
        $value_label = $value_label_index !== false ? ($options[$value_label_index]['label'] ?: $options[$value_label_index]['value']) : '';

        // Determine if custom dropdown is needed
        $has_custom_dropdown = $multiple || $search;

        // Loop through options and add 'active' flag
        foreach($options as $option_index => $option){
            $options[$option_index]['active'] = is_array($value) ? in_array($option['value'], $value) : $value == $option['value'];
        }

        $data['options'] = $options;
        $data['multiple'] = $multiple;
        $data['search'] = $search;
        $data['value_label'] = $value_label;
        $data['has_custom_dropdown'] = $has_custom_dropdown;

        // Add 'select-w-custom-dropdown' class
        if($data['has_custom_dropdown']) $data['class'] .= ' select-w-custom-dropdown';

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
            $placeholder = $this->get('placeholder', '');
            $multiple = $this->is_multiple();
            $search = !empty($this->get('search'));
            $default_value = $this->get('default_value', '');
        ?>
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-options-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Options', 'elzo-forms'); ?></label>
            <?php /* translators: %s: Option markup example shown to admins. */ ?>
            <div class="elzo-forms-field-control-guideline"><?php echo sprintf(esc_html__('Enter each option on a new line. Markup: %s (label is optional)', 'elzo-forms'), '<code>'.esc_html__('value : label', 'elzo-forms').'</code>'); ?></div>
            <textarea name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][options]" id="elzo-forms-field-options-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" rows="3"><?php echo esc_textarea($options); ?></textarea>
        </div>
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-placeholder-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Placeholder', 'elzo-forms'); ?></label>
            <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][placeholder]" value="<?php echo esc_attr($placeholder); ?>" id="elzo-forms-field-placeholder-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control elzo-forms-field-header-part">
        </div>
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-default-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Default Value', 'elzo-forms'); ?></label>
            <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][default_value]" value="<?php echo esc_attr($default_value); ?>" id="elzo-forms-field-default-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
        </div>
        <div class="elzo-forms-row elzo-forms-row-2-cols elzo-forms-field-control-group">
            <div class="elzo-forms-column">
                <div class="elzo-forms-field-control-group">
                    <label for="elzo-forms-field-multiple-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label">
                        <input type="checkbox" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][multiple]" value="1" id="elzo-forms-field-multiple-<?php echo esc_attr($field_id); ?>" <?php checked($multiple); ?> class="elzo-forms-field-check">
                        <?php esc_html_e('Allow multiple selections', 'elzo-forms'); ?>
                    </label>
                </div>
            </div>
            <div class="elzo-forms-column">
                <div class="elzo-forms-field-control-group">
                    <label for="elzo-forms-field-search-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label">
                        <input type="checkbox" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][search]" value="1" id="elzo-forms-field-search-<?php echo esc_attr($field_id); ?>" <?php checked($search); ?> class="elzo-forms-field-check">
                        <?php esc_html_e('Enable search', 'elzo-forms'); ?>
                    </label>
                </div>
            </div>
        </div>
        <?php
        }

        parent::render_field_settings($tab);
    }
}