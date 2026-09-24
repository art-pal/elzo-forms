<?php
/**
 * Checkbox Field class.
 *
 * @package ElzoForms\Field
 */

namespace ElzoForms\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Field_Checkbox extends Field {

    /**
     * Get field defaults.
     */
    protected function get_defaults(): array {
        return array_merge(parent::get_defaults(), [
            'type' => 'checkbox',
            'options' => [],
        ]);
    }

    /**
     * Checkbox fields always support multiple values.
     *
     * @return bool
     */
    protected function is_multiple(): bool {
        return true;
    }

    /**
     * Sanitize submitted input before extensible validation runs.
     *
     * The submitted values are matched against the configured options while they
     * are still untouched, so stripping markup cannot turn an unknown payload
     * into an empty value that passes validation.
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
     * @param mixed $value Field value (array or string)
     * @return true|\WP_Error
     */
    public function validate($value) {
        $values_to_check = is_array($value) ? $value : (!empty($value) ? [$value] : []);
        $selected_count = count($values_to_check);

        // Get min/max constraints. Settings are stored as strings, so cast them.
        $min_selections = absint($this->get('min_selections', 0));
        $max_selections = absint($this->get('max_selections', 0));

        // A maximum below the minimum would make the field impossible to submit.
        if ($max_selections > 0 && $min_selections > $max_selections) {
            $max_selections = $min_selections;
        }

        // Only "required" makes an empty selection invalid.
        if ($this->is_required() && $selected_count < 1) {
            return new \WP_Error(
                'required_field',
                /* translators: %s: Field title. */
                sprintf(__('%s is required', 'elzo-forms'), $this->get_title())
            );
        }

        /*
         * Min selections constrains a selection that has been started, it does not
         * force one: an optional field stays submittable while nothing is selected.
         * Marking the field required is what makes an empty selection invalid.
         */
        if ($min_selections > 0 && $selected_count > 0 && $selected_count < $min_selections) {
            return new \WP_Error(
                'min_selections',
                /* translators: 1: Field title, 2: Minimum required selections, 3: Current selected count. */
                sprintf(__('%1$s requires at least %2$d selections, but only %3$d selected', 'elzo-forms'), $this->get_title(), $min_selections, $selected_count)
            );
        }

        // Check maximum selections
        if ($max_selections > 0 && $selected_count > $max_selections) {
            return new \WP_Error(
                'max_selections',
                /* translators: 1: Field title, 2: Maximum allowed selections, 3: Current selected count. */
                sprintf(__('%1$s allows maximum %2$d selections, but %3$d were selected', 'elzo-forms'), $this->get_title(), $max_selections, $selected_count)
            );
        }

        return $this->validate_options($value);
    }

    /**
     * Validate values against the configured options.
     *
     * @param mixed $value Field value (array or string)
     * @return true|\WP_Error
     */
    private function validate_options($value) {
        $values_to_check = is_array($value) ? $value : (!empty($value) ? [$value] : []);

        $options = $this->get('options', []);
        $option_values = array_map('strval', array_column($options, 'value'));

        if (!empty($values_to_check)) {
            foreach ($values_to_check as $val) {
                if (!is_scalar($val) || !in_array((string) $val, $option_values, true)) {
                    return new \WP_Error(
                        'invalid_option',
                        /* translators: 1: Field title, 2: Invalid value, 3: Valid option list. */
                        sprintf(__('Invalid option selected for %1$s. Value "%2$s" is not a valid option. Valid options are: %3$s', 'elzo-forms'), $this->get_title(), $val, implode(', ', $option_values))
                    );
                }
            }
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
     * @return array|string
     */
    public function sanitize($value) {
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }

        return sanitize_text_field($value);
    }

    /**
     * Get checked values as array.
     *
     * @param mixed $value Field value
     * @return array
     */
    public function get_checked_values($value): array {
        if (is_array($value)) {
            return $value;
        }

        return !empty($value) ? [$value] : [];
    }

    /**
     * Get allowed operators for conditional logic.
     *
     * @return array|null
     */
    public function get_logic_operators(): ?array {
        // A checkbox group always submits a list, so how many boxes are
        // checked is a question worth asking of every one of them.
        return array_merge(
            $this->get_string_logic_operators(),
            \ElzoForms\Utilities\Conditional_Logic::get_count_operators()
        );
    }

    /**
     * Checkboxes compare against their configured options in the admin UI.
     *
     * @return string
     */
    public function get_logic_value_source(): string {
        return !empty($this->get_logic_value_options()) ? 'options' : 'text';
    }

    /**
     * Prepare all data for template rendering.
     *
     * @param array $context Additional context (form_settings, etc.)
     * @return array
     */
    public function get_data(array $context = []): array {
        $data = parent::get_data($context);

        $styles = \ElzoForms\Utilities\Admin::get_field_checkbox_styles();
        $style = !empty($this->get('style')) && in_array($this->get('style'), array_keys($styles), true)
            ? $this->get('style')
            : array_key_first($styles);

        // Add checkbox-specific data
        $data['options'] = $this->get('options', []);
        $data['layout'] = $this->get('layout', 'vertical');
        $data['style'] = $style;
        $data['min_selections'] = $this->get('min_selections', '');
        $data['max_selections'] = $this->get('max_selections', '');

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
            $field = $this->to_array();
            $field_id = $this->get_id();
            $step_index = $this->get('step_index', 0);
            $field_index = $this->get('index', 0);

            $options = \ElzoForms\Utilities\Admin::options_to_string($this->get('options', []));
            $default_value = $this->get('default_value', '');
            $min_selections = $this->get('min_selections', '');
            $max_selections = $this->get('max_selections', '');
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
        <div class="elzo-forms-row elzo-forms-row-2-cols elzo-forms-field-control-group">
            <div class="elzo-forms-column">
                <div class="elzo-forms-field-control-group">
                    <label for="elzo-forms-field-min-selections-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Min Selections', 'elzo-forms'); ?></label>
                    <input type="number" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][min_selections]" value="<?php echo esc_attr($min_selections); ?>" placeholder="-" id="elzo-forms-field-min-selections-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
                </div>
            </div>
            <div class="elzo-forms-column">
                <div class="elzo-forms-field-control-group">
                    <label for="elzo-forms-field-max-selections-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Max Selections', 'elzo-forms'); ?></label>
                    <input type="number" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][max_selections]" value="<?php echo esc_attr($max_selections); ?>" placeholder="-" id="elzo-forms-field-max-selections-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
                </div>
            </div>
        </div>
        <?php
        } elseif ($tab === 'view') {
            $field = $this->to_array();
            $field_id = $this->get_id();
            $step_index = $this->get('step_index', 0);
            $field_index = $this->get('index', 0);

            $layouts = \ElzoForms\Utilities\Admin::get_field_radio_checkbox_layouts();
            $layout = $this->get('layout', array_key_first($layouts));
            $styles = \ElzoForms\Utilities\Admin::get_field_checkbox_styles();
            $style = $this->get('style', array_key_first($styles));
        ?>
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-layout-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Layout', 'elzo-forms'); ?></label>
            <select name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][layout]" class="elzo-forms-field-control elzo-forms-field-layout-select" id="elzo-forms-field-layout-<?php echo esc_attr($field_id); ?>">
                <?php foreach($layouts as $option_value => $option_label): ?>
                    <option value="<?php echo esc_attr($option_value); ?>" <?php selected($layout, $option_value); ?>><?php echo esc_html($option_label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-style-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Style', 'elzo-forms'); ?></label>
            <select name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][style]" class="elzo-forms-field-control elzo-forms-field-style-select" id="elzo-forms-field-style-<?php echo esc_attr($field_id); ?>">
                <?php foreach($styles as $option_value => $option_label): ?>
                    <option value="<?php echo esc_attr($option_value); ?>" <?php selected($style, $option_value); ?>><?php echo esc_html($option_label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        }

        parent::render_field_settings($tab);
    }
}
