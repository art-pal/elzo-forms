<?php
/**
 * Range Field class.
 *
 * @package ElzoForms\Field
 */

namespace ElzoForms\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Field_Range extends Field {

    /**
     * Get field defaults.
     */
    protected function get_defaults(): array {
        return array_merge(parent::get_defaults(), [
            'type' => 'range',
            'min' => 0,
            'max' => 100,
            'step' => 1,
        ]);
    }

    /**
     * Normalize range configuration from admin payload or form JSON.
     */
    protected function normalize_configuration(array $data): array {
        return self::normalize_range_field_configuration($data);
    }

    /**
     * Normalize range field bounds and clamp default value into that range.
     */
    private static function normalize_range_field_configuration(array $field): array {
        $min = self::parse_numeric_field_value($field['min_value'] ?? null);
        $max = self::parse_numeric_field_value($field['max_value'] ?? null);

        if ($min !== null && $max !== null && $min > $max) {
            $tmp = $min;
            $min = $max;
            $max = $tmp;
        }

        if ($min !== null) {
            $field['min_value'] = self::format_numeric_field_value($min);
        }

        if ($max !== null) {
            $field['max_value'] = self::format_numeric_field_value($max);
        }

        if (!array_key_exists('default_value', $field)) {
            return $field;
        }

        $range_type = isset($field['range_type']) ? (string) $field['range_type'] : 'range_2';
        $field['default_value'] = self::normalize_range_value($field['default_value'], $range_type, $min, $max);

        return $field;
    }

    /**
     * Normalize a range-compatible value to configured boundaries.
     *
     * For range_2, accepts both "X - Y" and single numeric values.
     * For range_1 and point, returns a single numeric value.
     */
    public static function normalize_range_value($value, string $range_type, ?float $min = null, ?float $max = null): string {
        $raw = is_scalar($value) ? trim((string) $value) : '';
        if ($raw === '') {
            return '';
        }

        if ($range_type === 'range_2') {
            $range_pair = self::parse_range_default_pair($raw);

            if ($range_pair !== null) {
                $first = self::clamp_numeric_field_value($range_pair[0], $min, $max);
                $second = self::clamp_numeric_field_value($range_pair[1], $min, $max);

                if ($first > $second) {
                    $tmp = $first;
                    $first = $second;
                    $second = $tmp;
                }

                // Keep two-handle values valid after clamping.
                if ($first >= $second && $min !== null && $max !== null && $min < $max) {
                    $first = $min;
                    $second = $max;
                }

                if ($first < $second) {
                    return self::format_numeric_field_value($first) . ' - ' . self::format_numeric_field_value($second);
                }

                // Fallback to single numeric value when a valid pair cannot be formed.
                return self::format_numeric_field_value($second);
            }

            $single_value = self::parse_numeric_field_value($raw);
            if ($single_value === null) {
                return '';
            }

            $single_value = self::clamp_numeric_field_value($single_value, $min, $max);

            return self::format_numeric_field_value($single_value);
        }

        $single_value = self::parse_numeric_field_value($raw);

        if ($single_value === null) {
            $range_pair = self::parse_range_default_pair($raw);
            if ($range_pair !== null) {
                $single_value = $range_pair[1];
            }
        }

        if ($single_value === null) {
            return '';
        }

        $single_value = self::clamp_numeric_field_value($single_value, $min, $max);

        return self::format_numeric_field_value($single_value);
    }

    /**
     * Parse scalar numeric value from field payload.
     */
    private static function parse_numeric_field_value($value): ?float {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Format numeric value for field storage.
     */
    private static function format_numeric_field_value(float $value): string {
        if (abs($value) < 0.0000000001) {
            $value = 0.0;
        }

        $formatted = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');

        return ($formatted === '' || $formatted === '-0') ? '0' : $formatted;
    }

    /**
     * Clamp value between optional minimum and maximum bounds.
     */
    private static function clamp_numeric_field_value(float $value, ?float $min, ?float $max): float {
        if ($min !== null && $value < $min) {
            $value = $min;
        }

        if ($max !== null && $value > $max) {
            $value = $max;
        }

        return $value;
    }

    /**
     * Parse range default in "X - Y" format.
     */
    private static function parse_range_default_pair(string $value): ?array {
        if (!preg_match('/^\s*([+-]?(?:\d+\.?\d*|\.\d+))\s*-\s*([+-]?(?:\d+\.?\d*|\.\d+))\s*$/', $value, $matches)) {
            return null;
        }

        return [(float) $matches[1], (float) $matches[2]];
    }

    /**
     * Sanitize submitted input before extensible validation runs.
     *
     * The submitted scalar is checked while it is still untouched, so that
     * sanitize() cannot cast a non-numeric payload into a number that happens
     * to sit inside the configured bounds.
     *
     * @param mixed $value Raw submitted field value.
     * @return mixed|\WP_Error
     */
    public function sanitize_input($value) {
        $shape_result = $this->validate_input_shape($value);
        if (is_wp_error($shape_result)) {
            return $shape_result;
        }

        $range_result = $this->validate_range(is_scalar($value) ? trim((string) $value) : $value);
        if (is_wp_error($range_result)) {
            return $range_result;
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
        return $this->validate_range($value);
    }

    /**
     * Validate a value against the configured range format and bounds.
     *
     * @param mixed $value Field value
     * @return true|\WP_Error
     */
    private function validate_range($value) {
        if (empty($value)) {
            return true;
        }

        // Parse value into array of numbers
        if (is_numeric($value)) {
            $values = [floatval($value)];
        } elseif (is_string($value) && strpos($value, ' - ') !== false) {
            $parts = explode(' - ', $value);
            if (count($parts) !== 2) {
                return new \WP_Error(
                    'invalid_range_format',
                    /* translators: %s: Field title. */
                    sprintf(__('Please enter a valid range for %s in format "X - Y"', 'elzo-forms'), $this->get_title())
                );
            }
            $values = array_map('trim', $parts);
        } else {
            return new \WP_Error(
                'invalid_range_format',
                /* translators: %s: Field title. */
                sprintf(__('Please enter a valid number or range (X - Y) for %s', 'elzo-forms'), $this->get_title())
            );
        }

        // Validate each value is numeric
        foreach ($values as $val) {
            if (!is_numeric($val)) {
                return new \WP_Error(
                    'invalid_range_numbers',
                    /* translators: %s: Field title. */
                    sprintf(__('All values in %s must be valid numbers', 'elzo-forms'), $this->get_title())
                );
            }
        }

        // Convert to floats
        $values = array_map('floatval', $values);

        // Validate range order if dual range
        if (count($values) === 2 && $values[0] >= $values[1]) {
            return new \WP_Error(
                'invalid_range_order',
                /* translators: %s: Field title. */
                sprintf(__('The first value must be less than the second value in %s', 'elzo-forms'), $this->get_title())
            );
        }

        // Validate each value is within bounds
        $min = $this->get('min_value');
        $max = $this->get('max_value');

        foreach ($values as $index => $val) {
            if (($min && $val < $min) || ($max && $val > $max)) {
                $label = count($values) === 2
                    ? ($index === 0 ? __('The first value', 'elzo-forms') : __('The second value', 'elzo-forms'))
                    : __('The value', 'elzo-forms');

                return new \WP_Error(
                    'out_of_range',
                    /* translators: 1: Value position label, 2: Field title, 3: Minimum value, 4: Maximum value. */
                    sprintf(__('%1$s in %2$s must be between %3$s and %4$s', 'elzo-forms'), $label, $this->get_title(), $min, $max)
                );
            }
        }

        return true;
    }

    /**
     * Sanitize field value.
     *
     * @param mixed $value Field value
     * @return string|int|float
     */
    public function sanitize($value) {
        $range_type = $this->get('range_type', 'range_2');

        // Handle range_2 type (dual range: "X - Y")
        if ($range_type === 'range_2') {
            if (!is_string($value) || strpos($value, ' - ') === false) {
                return '';
            }

            $parts = explode(' - ', $value);
            if (count($parts) !== 2) {
                return '';
            }

            $value_min = sanitize_text_field(trim($parts[0]));
            $value_max = sanitize_text_field(trim($parts[1]));

            // Return in "X - Y" format
            return $value_min . ' - ' . $value_max;
        }

        // Handle single value range
        $step = $this->get('step', 1);

        // Return float if step is decimal, int otherwise
        if (is_float($step) || strpos($step, '.') !== false) {
            return floatval($value);
        }

        return intval($value);
    }

    /**
     * Get allowed operators for conditional logic.
     *
     * @return array|null
     */
    public function get_logic_operators(): ?array {
        return ['==', '!=', '>', '<'];
    }

    /**
     * Prepare data for template rendering.
     *
     * @param array $context Additional context data
     * @return array Data to be extracted in template
     */
    public function get_data(array $context = []): array {
        $data = parent::get_data($context);

        $range_types = \ElzoForms\Utilities\Helpers::get_field_range_types();
        $range_type = !empty($this->get('range_type')) && in_array($this->get('range_type'), array_keys($range_types))
            ? $this->get('range_type')
            : 'range_2';

        $normalized_range = self::normalize_range_field_configuration([
            'type' => 'range',
            'range_type' => $range_type,
            'min_value' => $this->get('min_value', 0),
            'max_value' => $this->get('max_value', 100),
            'default_value' => $data['default_value'] ?? '',
        ]);

        $min_value = isset($normalized_range['min_value']) && is_numeric($normalized_range['min_value']) ? floatval($normalized_range['min_value']) : 0;
        $max_value = isset($normalized_range['max_value']) && is_numeric($normalized_range['max_value']) ? floatval($normalized_range['max_value']) : 100;

        if ($min_value > $max_value) {
            $tmp = $min_value;
            $min_value = $max_value;
            $max_value = $tmp;
        }

        $step = $this->get('step', 1);
        $step = (is_numeric($step) && floatval($step) > 0) ? floatval($step) : 1;

        $default_value = $normalized_range['default_value'] ?? '';

        // Normalize current value at render time to support externally provided JSON.
        $current_value = $data['value'];
        if ($current_value === null || $current_value === '') {
            $current_value = $default_value;
        }
        $current_value = self::normalize_range_value($current_value, $range_type, $min_value, $max_value);
        if ($current_value === '') {
            $current_value = $default_value;
        }

        $data['range_type'] = $range_type;
        $data['step'] = $step;
        $data['min_value'] = $min_value;
        $data['max_value'] = $max_value;
        $data['default_value'] = $default_value;
        $data['value'] = $current_value;
        $data['input_prepend'] = $this->get('input_prepend');
        $data['input_append'] = $this->get('input_append');
        $data['hide_text_inputs'] = !empty($this->get('hide_text_inputs'));

        // Process field value for range display
        $value_min = $min_value;
        $value_max = $max_value;

        if ($current_value !== '') {
            if (is_string($current_value) && strpos($current_value, ' - ') !== false) {
                $value_parts = explode(' - ', $current_value);
                if (count($value_parts) === 2) {
                    $value_min = floatval($value_parts[0]);
                    $value_max = floatval($value_parts[1]);
                }
            } elseif (is_numeric($current_value)) {
                if ($range_type === 'range_2') {
                    $value_min = $min_value;
                }

                $value_max = floatval($current_value);
            }
        }

        $value_min = max($min_value, min($max_value, $value_min));
        $value_max = max($min_value, min($max_value, $value_max));

        if ($range_type === 'range_2' && $value_min > $value_max) {
            $tmp = $value_min;
            $value_min = $value_max;
            $value_max = $tmp;
        }

        $data['value_min'] = $value_min;
        $data['value_max'] = $value_max;

        // Calculate intermediate points for slider labels
        $data['point_1'] = $min_value + ($max_value - $min_value) * 0.25;
        $data['point_2'] = $min_value + ($max_value - $min_value) * 0.5;
        $data['point_3'] = $min_value + ($max_value - $min_value) * 0.75;

        // Calculate range progress CSS percentages
        $range_span = $max_value - $min_value;
        if ($range_span > 0) {
            $data['range_progress_left'] = ($value_min - $min_value) / $range_span * 100;
            $data['range_progress_right'] = 100 - (($value_max - $min_value) / $range_span * 100);
        } else {
            $data['range_progress_left'] = 0;
            $data['range_progress_right'] = 0;
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
            $range_types = \ElzoForms\Utilities\Helpers::get_field_range_types();
        $range_type = !empty($this->get('range_type')) && in_array($this->get('range_type'), array_keys($range_types)) ? $this->get('range_type') : 'range_2';
        $step = $this->get('step', '');
        $default_value = $this->get('default_value', '');
        $min_value = $this->get('min_value', '');
        $max_value = $this->get('max_value', '');
        ?>
        <div class="elzo-forms-row elzo-forms-row-2-cols elzo-forms-field-control-group">
            <div class="elzo-forms-column">
                <div class="elzo-forms-field-control-group">
                    <label for="elzo-forms-field-range-type-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Range type', 'elzo-forms'); ?></label>
                    <select name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][range_type]" id="elzo-forms-field-range-type-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
                        <?php foreach($range_types as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($range_type, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="elzo-forms-column">
                <div class="elzo-forms-field-control-group">
                    <label for="elzo-forms-field-step-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Step', 'elzo-forms'); ?></label>
                    <input type="number" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][step]" value="<?php echo esc_attr($step); ?>" placeholder="1" id="elzo-forms-field-step-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
                </div>
            </div>
        </div>
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-default-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Default Value', 'elzo-forms'); ?></label>
            <?php /* translators: 1: Example range value, 2: Example single value. */ ?>
            <div class="elzo-forms-field-control-guideline"><?php echo sprintf(esc_html__('Example: %1$s or %2$s if a single value', 'elzo-forms'), '"2 - 4"', '"3"'); ?></div>
            <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][default_value]" value="<?php echo esc_attr($default_value); ?>" id="elzo-forms-field-default-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
        </div>
        <div class="elzo-forms-row elzo-forms-row-2-cols elzo-forms-field-control-group">
            <div class="elzo-forms-column">
                <div class="elzo-forms-field-control-group">
                    <label for="elzo-forms-field-min-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Min Value', 'elzo-forms'); ?></label>
                    <input type="number" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][min_value]" value="<?php echo esc_attr($min_value); ?>" placeholder="0" id="elzo-forms-field-min-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
                </div>
            </div>
            <div class="elzo-forms-column">
                <div class="elzo-forms-field-control-group">
                    <label for="elzo-forms-field-max-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Max Value', 'elzo-forms'); ?></label>
                    <input type="number" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][max_value]" value="<?php echo esc_attr($max_value); ?>" placeholder="100" id="elzo-forms-field-max-value-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
                </div>
            </div>
        </div>
        <?php
        } elseif ($tab === 'view') {
            $hide_text_inputs = !empty($this->get('hide_text_inputs'));
        $input_prepend = $this->get('input_prepend', '');
        $input_append = $this->get('input_append', '');
        ?>
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-hide-text-inputs-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><input type="checkbox" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][hide_text_inputs]" id="elzo-forms-field-hide-text-inputs-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-check" <?php checked($hide_text_inputs); ?> value="Checked" > <?php esc_html_e('Hide text inputs', 'elzo-forms'); ?></label>
        </div>
        <div class="elzo-forms-row elzo-forms-row-2-cols elzo-forms-field-control-group" data-ef-logic='[[{"type":"input","operator":"!=","settings":{"id":"elzo-forms-field-hide-text-inputs-<?php echo esc_attr($field_id); ?>","value":"Checked"}}]]'>
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

        parent::render_field_settings($tab);
    }
}