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
        ]);
    }

    /**
     * Get the Text variant this field implements ("text", "email", "tel", ...).
     *
     * Always a registered Text subtype, so it is safe to use as the input type.
     */
    private function get_input_subtype(): string {
        $subtype = $this->get_subtype();

        return $subtype !== '' ? $subtype : 'text';
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
     * Validate the format of the Text variant.
     *
     * A variant with a format of its own accepts only values of that format,
     * whoever sends the request. The form is rendered with "novalidate", so
     * browsers do not enforce these formats either: date, time and number
     * controls submit a well-formed value or an empty string, while email,
     * URL and phone values arrive as typed.
     *
     * An empty value is always valid here; "required" is checked separately.
     *
     * @param mixed $value Scalar field value.
     * @return true|\WP_Error
     */
    private function validate_format($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return true;
        }

        $subtype = $this->get_input_subtype();

        /**
         * Filters whether a submitted value matches the format of its Text variant.
         *
         * Runs for every non-empty value of every Text variant, first on the
         * submitted value and then on the sanitized one. Variants registered
         * through elzo_forms_field_text_subtypes have no built-in format and
         * are valid unless a callback says otherwise.
         *
         * @filter elzo_forms_is_text_field_value_valid
         * @param bool   $is_valid Whether the value matches the variant's format.
         * @param string $value    Value without surrounding whitespace.
         * @param string $subtype  Text variant: "text", "email", "date", ...
         * @param array  $field    Field data.
         */
        $is_valid = (bool) apply_filters(
            'elzo_forms_is_text_field_value_valid',
            self::matches_subtype_format($subtype, $value),
            $value,
            $subtype,
            $this->to_array()
        );

        return $is_valid ? true : $this->get_format_error($subtype);
    }

    /**
     * Check a value against the built-in format of a Text variant.
     *
     * @param string $subtype Text variant.
     * @param string $value Value without surrounding whitespace.
     */
    private static function matches_subtype_format(string $subtype, string $value): bool {
        switch ($subtype) {
            case 'email':
                return (bool) is_email($value);
            case 'url':
                return self::is_valid_url($value);
            case 'number':
                return self::is_valid_number($value);
            case 'date':
                return self::is_valid_date($value);
            case 'time':
                return self::is_valid_time($value);
            case 'tel':
                return self::is_valid_phone($value);
            case 'password':
                // Kept exactly as typed, so it must already be valid UTF-8.
                return wp_check_invalid_utf8($value) === $value;
            default:
                // Text accepts any text; sanitization cleans it.
                return true;
        }
    }

    /**
     * Check for a finite decimal number, as a number input submits it ("-1.5", "2e3").
     *
     * No step is enforced: the input has no step setting, and without browser
     * validation a decimal typed into it is submitted as is.
     *
     * @param string $value Value without surrounding whitespace.
     */
    private static function is_valid_number(string $value): bool {
        // is_numeric() alone would also accept "1e999", which is INF.
        return (bool) preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/', $value)
            && is_finite((float) $value);
    }

    /**
     * Check for an existing calendar date in the "YYYY-MM-DD" format of date inputs.
     *
     * @param string $value Value without surrounding whitespace.
     */
    private static function is_valid_date(string $value): bool {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return false;
        }

        $year = (int) $matches[1];

        return $year >= 1 && checkdate((int) $matches[2], (int) $matches[3], $year);
    }

    /**
     * Check for a time of day as a time input submits it: "HH:MM", or
     * "HH:MM:SS" and "HH:MM:SS.sss" when the input allows seconds.
     *
     * @param string $value Value without surrounding whitespace.
     */
    private static function is_valid_time(string $value): bool {
        return (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d{1,3})?)?$/', $value);
    }

    /**
     * Check for an absolute URL with a protocol WordPress allows.
     *
     * Web URLs must name a host. Internationalized domain names are accepted
     * as typed, the way URL inputs accept them. Other allowed protocols, such
     * as "mailto:", have no host to check.
     *
     * @param string $value Value without surrounding whitespace.
     */
    private static function is_valid_url(string $value): bool {
        // URL inputs strip line breaks, so control characters are never typed.
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            return false;
        }

        if (!preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):./s', $value, $matches)) {
            return false;
        }

        $scheme = strtolower($matches[1]);
        if (!in_array($scheme, wp_allowed_protocols(), true)) {
            return false;
        }

        if (!in_array($scheme, ['http', 'https', 'ftp', 'ftps'], true)) {
            return true;
        }

        // parse_url() mangles non-ASCII hosts, so the authority is read here:
        // an optional "user@", the host and an optional port.
        if (!preg_match('~^[^:]+://([^/?#]*)~', $value, $matches)) {
            return false;
        }

        $authority = $matches[1];
        $at = strrpos($authority, '@');
        $host_and_port = $at === false ? $authority : substr($authority, $at + 1);

        if (!preg_match('/^(\[[^\]]*\]|[^:\[\]]*)(?::\d{0,5})?$/', $host_and_port, $matches) || $matches[1] === '') {
            return false;
        }

        $host = $matches[1];

        if ($host[0] === '[') {
            return substr($host, -1) === ']'
                && filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }

        $label = '[\p{L}\p{N}\p{M}_](?:[\p{L}\p{N}\p{M}_-]*[\p{L}\p{N}\p{M}_])?';

        return (bool) preg_match('/^' . $label . '(?:\.' . $label . ')*\.?$/u', $host);
    }

    /**
     * Check that a phone value contains at least one digit.
     *
     * A phone input has no format of its own, and phone numbers are written
     * in many ways ("+1 (555) 123-4567", "050 123 45 67, Viber"), so this only
     * keeps out values without a number at all. The value is checked again
     * after elzo_forms_handle_phone_field has formatted it; stricter rules
     * belong in elzo_forms_is_text_field_value_valid.
     *
     * @param string $value Value without surrounding whitespace.
     */
    private static function is_valid_phone(string $value): bool {
        return (bool) preg_match('/\d/', $value);
    }

    /**
     * Build the error for a value that does not match its variant's format.
     *
     * @param string $subtype Text variant.
     */
    private function get_format_error(string $subtype): \WP_Error {
        $title = $this->get_title();

        switch ($subtype) {
            case 'email':
                /* translators: %s: Field title. */
                return new \WP_Error('invalid_email', sprintf(__('Please enter a valid email address for %s', 'elzo-forms'), $title));
            case 'url':
                /* translators: %s: Field title. */
                return new \WP_Error('invalid_url', sprintf(__('Please enter a valid URL for %s', 'elzo-forms'), $title));
            case 'number':
                /* translators: %s: Field title. */
                return new \WP_Error('invalid_number', sprintf(__('Please enter a valid number for %s', 'elzo-forms'), $title));
            case 'date':
                /* translators: %s: Field title. */
                return new \WP_Error('invalid_date', sprintf(__('Please enter a valid date for %s', 'elzo-forms'), $title));
            case 'time':
                /* translators: %s: Field title. */
                return new \WP_Error('invalid_time', sprintf(__('Please enter a valid time for %s', 'elzo-forms'), $title));
            case 'tel':
                /* translators: %s: Field title. */
                return new \WP_Error('invalid_phone', sprintf(__('Please enter a valid phone number for %s', 'elzo-forms'), $title));
            default:
                /* translators: %s: Field title. */
                return new \WP_Error('invalid_field_format', sprintf(__('Please enter a valid value for %s', 'elzo-forms'), $title));
        }
    }

    /**
     * Sanitize field value.
     *
     * @param mixed $value Field value
     * @return string
     */
    public function sanitize($value) {
        $subtype = $this->get_input_subtype();

        // Email sanitization
        if ($subtype === 'email') {
            return sanitize_email(trim((string) $value));
        }

        // A valid URL is kept whole, including internationalized domain names
        // and percent-encoded data that text sanitization would strip.
        if ($subtype === 'url' && is_scalar($value) && self::is_valid_url(trim((string) $value))) {
            return esc_url_raw(trim((string) $value));
        }

        // Phone number - allow custom filter
        if ($subtype === 'tel') {
            return apply_filters('elzo_forms_handle_phone_field', sanitize_text_field($value), $this->to_array());
        }

        // A password is kept exactly as typed: text sanitization would strip
        // markup-like parts, percent-encoded sequences and repeated or
        // surrounding spaces, and so change the password. Only control
        // characters, which a password input never submits, are removed.
        // Every output of submission values escapes them.
        if ($subtype === 'password') {
            $value = is_scalar($value) ? (string) $value : '';

            return (string) preg_replace('/[\x00-\x08\x0A-\x1F\x7F]/', '', wp_check_invalid_utf8($value));
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
        $data['field_subtype'] = $this->get_input_subtype();
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
            $placeholder = $this->get('placeholder', '');
            $default_value = $this->get('default_value', '');
            ?>
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
