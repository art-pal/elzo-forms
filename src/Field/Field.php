<?php
/**
 * Base Field class with factory pattern.
 *
 * EXTENDING THIS CLASS:
 *
 * Child classes MUST implement:
 * - validate($value): true|WP_Error    - Validate user input
 * - sanitize($value): mixed             - Clean/sanitize user input
 *
 * Child classes SHOULD override:
 * - sanitize_input($value): mixed|WP_Error - Pure early submission sanitization
 * - finalize_submission_value($value): mixed|WP_Error - Deferred side effects
 * - get_data($context): array           - Add field-specific data for rendering
 * - render_html($field_data): string    - Render field inline (if not using template)
 * - get_defaults(): array               - Define field-specific default values
 * - normalize_configuration($data): array - Normalize field config from admin/JSON
 *
 * Field rendering priority:
 * 1. Template file (templates/field-types/field-{type}.php)
 * 2. render_html() method
 *
 * @package ElzoForms\Field
 */

namespace ElzoForms\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

abstract class Field implements \ArrayAccess {

    /** @var int Maximum nesting depth accepted in a URL-encoded JSON field payload */
    protected const MAX_JSON_PAYLOAD_DEPTH = 32;

    /** @var array Field data */
    protected $data = [];

    /** @var array Registered field type classes */
    protected static $field_types = [];

    /** @var array Cache for rendered data */
    private $render_data_cache = [];

    /**
     * @var int Form this field belongs to.
     *
     * Kept outside $data so it never reaches to_array() and cannot be written
     * back into the stored form JSON: it describes where the object came from,
     * not how the field is configured.
     */
    protected int $form_id = 0;


    /**
     * Factory method to create field instance from data.
     *
     * Always builds a new instance from the passed data. Instances are
     * deliberately not cached by field ID: form JSON can be written outside the
     * admin UI (imports, fixtures, code) and duplicate IDs would otherwise make
     * every field after the first one render as a copy of that first field.
     *
     * @param array|int $field Field data array, or a field ID for an empty field
     * @return Field Field instance of appropriate type
     */
    public static function from($field = []): Field {
        // Handle numeric ID
        if (is_numeric($field)) {
            $field = ['id' => $field];
        }

        if (!is_array($field)) {
            $field = [];
        }

        // Get field type
        $type = $field['type'] ?? 'text';

        // Get registered field types
        $field_types = self::get_registered_field_types();

        // Get class name for field type
        $class_name = $field_types[$type] ?? $field_types['text'] ?? Text_Field::class;

        // Create instance
        if (class_exists($class_name)) {
            return new $class_name($field);
        }

        // Fallback to Text_Field
        return new Text_Field($field);
    }

    /**
     * Get registered field type classes.
     *
     * @return array Field type => Class name mapping
     */
    protected static function get_registered_field_types(): array {
        if (empty(self::$field_types)) {
            // Get from Admin utility which is the single source of truth
            self::$field_types = \ElzoForms\Utilities\Admin::get_field_types(null, 'class');
        }

        return self::$field_types;
    }

    /**
     * Register a custom field type.
     *
     * @param string $type Field type slug
     * @param string $class_name Fully qualified class name
     */
    public static function register_field_type(string $type, string $class_name): void {
        self::$field_types[$type] = $class_name;
    }

    /**
     * Constructor.
     *
     * @param array $field Field data array
     */
    public function __construct($field = []) {
        $this->data = wp_parse_args($field, $this->get_defaults());

        // Generate ID if not set
        if (empty($this->data['id'])) {
            $this->data['id'] = $this->generate_id();
        }

        $this->data = $this->normalize_configuration($this->data);

        // Read-only fields never contribute a submitted value, so settings
        // that describe submission data must not survive imports or crafted
        // admin requests. The admin UI mirrors this rule by disabling them.
        if ($this->is_read_only()) {
            $this->data['required'] = false;
            $this->data['primary_field'] = false;
            $this->data['submission_table_field'] = false;
        }
    }

    /**
     * Normalize field configuration data.
     *
     * Runs for every field object created from admin payload or form JSON.
     * Child classes can override to keep field-specific settings consistent.
      *
      * Implementation contract:
      * - Keep this method idempotent (same input => same normalized output).
      * - Avoid side effects (no DB writes, no remote calls, no output).
      * - Normalize configuration only (not submitted frontend values).
      * - Preserve unknown keys unless the field explicitly forbids them.
      * - Prefer safe fallbacks over throwing for malformed config values.
     *
     * @param array $data Field configuration data
     * @return array
     */
    protected function normalize_configuration(array $data): array {
        return $data;
    }

    /**
     * Normalize a raw field definition through its field class.
     *
     * Useful during admin save flows before writing JSON to storage.
     *
     * @param array $field Raw field data
     * @return array Normalized field data
     */
    public static function normalize_definition(array $field): array {
        $type = $field['type'] ?? 'text';
        $field_types = self::get_registered_field_types();
        $class_name = $field_types[$type] ?? $field_types['text'] ?? Text_Field::class;

        if (class_exists($class_name)) {
            $instance = new $class_name($field);
        } else {
            $instance = new Text_Field($field);
        }

        return $instance->to_array();
    }

    /**
     * Get field defaults.
     */
    protected function get_defaults(): array {
        return [
            'step_index' => 0,
            'index' => 0,
            'id' => null,
            'type' => 'text',
            'field_key' => '',
            'label' => '',
            'placeholder' => '',
            'admin_label' => '',
            'under_label' => '',
            'under_field' => '',
            'options' => [],
            'required' => false,
        ];
    }

    /**
     * Generate unique field ID.
     *
     * Several fields can be generated within the same second, so the timestamp
     * alone is not enough: duplicate IDs collide in the admin markup (shared DOM
     * IDs) and in the stored form JSON. Keep IDs increasing per request.
     */
    public static function generate_id(): int {
        static $last_generated_id = 0;

        $id = time();

        if ($id <= $last_generated_id) {
            $id = $last_generated_id + 1;
        }

        $last_generated_id = $id;

        return $id;
    }

    /**
     * Get field property.
     *
     * @param string $key Property key
     * @param mixed $default Default value if not set
     * @return mixed
     */
    public function get(string $key, $default = null) {
        if (!array_key_exists($key, $this->data)) {
            return $default;
        }

        $value = $this->data[$key];

        // Treat explicit null in stored field config the same as an absent key.
        // This avoids passing null into esc_attr()/htmlspecialchars in templates.
        return $value === null ? $default : $value;
    }

    /**
     * Set field property.
     *
     * @param string $key Property key
     * @param mixed $value Property value
     * @return self
     */
    public function set(string $key, $value): self {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Get all field data as array.
     *
     * @return array
     */
    public function to_array(): array {
        return $this->data;
    }

    /**
     * Bind the field to the form it was built from.
     *
     * Set by Step when a Form builds its fields. A field created on its own
     * keeps 0, which stands for "no form context", not "any form".
     *
     * @param int $form_id Form post ID.
     * @return self For method chaining.
     */
    public function set_form_id(int $form_id): self {
        $this->form_id = max(0, $form_id);

        return $this;
    }

    /**
     * Get the form this field was built from, or 0 when it was built alone.
     */
    public function get_form_id(): int {
        return $this->form_id;
    }

    /**
     * Get field ID.
     *
     * @return mixed
     */
    public function get_id() {
        return $this->data['id'];
    }

    /**
     * Get field type.
     *
     * @return string
     */
    public function get_type(): string {
        return $this->data['type'] ?? 'text';
    }

    /**
     * Get field label.
     *
     * @return string
     */
    public function get_label(): string {
        return $this->data['label'] ?? '';
    }

    /**
     * Get field placeholder.
     *
     * @return string
     */
    public function get_placeholder(): string {
        return $this->data['placeholder'] ?? '';
    }

    /**
     * Get field admin label.
     *
     * @return string
     */
    public function get_admin_label(): string {
        return $this->data['admin_label'] ?? '';
    }

    /**
     * Get field key used by integrations and internal references.
     *
     * @return string
     */
    public function get_field_key(): string {
        return $this->data['field_key'] ?? '';
    }

    /**
     * Get field display title.
     *
     * Returns label/placeholder, otherwise "Field #ID".
     *
     * @return string
     */
    public function get_title(): string {
        /* translators: %s: Field ID. */
        return ($this->get_label() ?: $this->get_placeholder()) ?: sprintf(__('Field #%s', 'elzo-forms'), $this->get_id());
    }

    /**
     * Get field admin display title.
     *
     * Returns admin_label if set, otherwise label/placeholder, otherwise "Field #ID".
     *
     * @return string
     */
    public function get_admin_title(): string {
        /* translators: %s: Field ID. */
        return $this->get_admin_label() ?: ($this->get_label() ?: $this->get_placeholder()) ?: sprintf(__('Field #%s', 'elzo-forms'), $this->get_id());
    }

    /**
     * Check if field is required.
     *
     * @return bool
     */
    public function is_required(): bool {
        return !empty($this->data['required']);
    }

    /**
     * Check if field is read-only (should not accept user input).
     * Content fields and similar non-input fields should override this.
     *
     * @return bool
     */
    public function is_read_only(): bool {
        return false;
    }

    /**
     * Check if field allows empty submission (may be missing from POST data).
     *
     * Fields like checkboxes are not included in POST when unchecked.
     * Override this in field types that need default empty values when not submitted.
     *
     * @return bool
     */
    public function allows_empty_submission(): bool {
        return false;
    }

    /**
     * Check if field type supports the text under label setting.
     *
     * Drives both the admin control and the frontend output, so a type never
     * renders a value that cannot be set in the form builder.
     *
     * @return bool
     */
    public function supports_under_label(): bool {
        $excluded_types = ['hidden', 'content'];

        return !in_array($this->get_type(), $excluded_types, true);
    }

    /**
     * Check if field type supports the text under field setting.
     *
     * @return bool
     */
    public function supports_under_field(): bool {
        $excluded_types = ['hidden'];

        return !in_array($this->get_type(), $excluded_types, true);
    }

    /**
     * Check if field should show label wrapper.
     *
     * @return bool
     */
    public function shows_label_wrapper(): bool {
        $has_label_content = !empty($this->get_label()) || !empty($this->get('under_label'));

        return $has_label_content && $this->supports_under_label();
    }

    /**
     * Check if field should show under field text.
     *
     * @return bool
     */
    public function shows_under_field(): bool {
        return !empty($this->get('under_field')) && $this->supports_under_field();
    }

    /**
     * Get field logic rules.
     */
    public function get_logic_rules(): array {
        if (empty($this->data['logic'])) {
            return [];
        }

        $rule_groups = !empty($this->data['rules']) ? array_values($this->data['rules']) : null;
        $logic_rules = $rule_groups ? array_map(function($group) {
            return array_values($group);
        }, $rule_groups) : null;

        return $logic_rules ?? [];
    }

    /**
     * Get field default value.
     *
     * @return mixed
     */
    public function get_default_value() {
        $default_value = $this->data['default_value'] ?? null;

        if($this->is_multiple()) {
            // Convert default value to array if comma-separated
            if (is_string($default_value) && strpos($default_value, ',') !== false) {
                $default_value = array_map('trim', explode(',', $default_value));
            } else if (!empty($default_value) && !is_array($default_value)) {
                $default_value = [$default_value];
            } else if (empty($default_value)) {
                $default_value = [];
            }
        }

        // Scalar controls render their value through string-oriented WordPress
        // escaping helpers. Keep an unset value empty instead of passing null,
        // which emits PHP 8.1+ deprecation output into the front-end markup.
        if (null === $default_value) {
            $default_value = '';
        }

        return $default_value;
    }

    /**
     * Get field value (defaults to default_value if not set).
     *
     * @return mixed
     */
    public function get_value() {
        return $this->data['value'] ?? $this->get_default_value();
    }

    /**
     * Check if this field type supports multiple values.
     * Override in child classes to return true for fields that accept arrays.
     *
     * @return bool
     */
    protected function is_multiple(): bool {
        return false;
    }

    /**
     * Validate the top-level shape of a submitted field value.
     *
     * This deliberately accepts only scalar leaves. Field types that support
     * structured values can override sanitize_input() and apply a more specific
     * schema before any submitted value reaches validation or business logic.
     *
     * @param mixed $value Raw submitted field value.
     * @return true|\WP_Error
     */
    protected function validate_input_shape($value) {
        if ($this->is_multiple()) {
            if (!is_array($value)) {
                return $this->get_invalid_input_error();
            }

            foreach ($value as $item) {
                if (!is_scalar($item)) {
                    return $this->get_invalid_input_error();
                }
            }

            return true;
        }

        return is_scalar($value) ? true : $this->get_invalid_input_error();
    }

    /**
     * Build the generic error used for malformed submission structures.
     *
     * @return \WP_Error
     */
    protected function get_invalid_input_error(): \WP_Error {
        return new \WP_Error(
            'invalid_field_value',
            /* translators: %s: Field title. */
            sprintf(esc_html__('Field %s contains invalid data', 'elzo-forms'), esc_html($this->get_title()))
        );
    }

    /**
     * Get field name attribute.
     *
     * @return string
     */
    public function get_field_name(): string {
        $step_index = $this->get('step_index', 0);
        $field_id = $this->get_id();
        $name = "elzo_form_fields[$step_index][$field_id]";

        // Add [] for multi-value fields
        if ($this->is_multiple()) {
            $name .= '[]';
        }

        return $name;
    }

    /**
     * Get field ID attribute.
     *
     * @return string
     */
    public function get_field_id(): string {
        $custom_id = $this->get('custom_id');
        $field_id = $this->get_id();

        return $custom_id ?: "elzo-forms-field-$field_id";
    }

    /**
     * Get field CSS class attribute.
     *
     * @param array $form_settings Optional form settings
     * @return string
     */
    public function get_field_class(array $form_settings = []): string {
        $type = $this->get_type();
        $required = $this->is_required();
        $custom_class = $this->get('custom_class');

        $classes = ['elzo-forms-field'];

        // Add field-control class for most field types
        if (!in_array($type, ['radio', 'checkbox'], true)) {
            $classes[] = 'elzo-forms-field-control';
        }

        $classes[] = 'elzo-forms-field-' . $type;

        if ($required) {
            $classes[] = 'elzo-forms-field-required';
        }

        if (!empty($form_settings['form_field_class'])) {
            $classes[] = $form_settings['form_field_class'];
        }

        if ($custom_class) {
            $classes[] = $custom_class;
        }

        return implode(' ', $classes);
    }

    /**
     * Get field wrapper ID attribute.
     *
     * @return string
     */
    public function get_wrapper_id(): string {
        $custom_id = $this->get('wrapper_custom_id');
        $field_id = $this->get_id();

        return $custom_id ?: "elzo-forms-field-wrapper-$field_id";
    }

    /**
     * Get field wrapper CSS class attribute.
     *
     * @return string
     */
    public function get_wrapper_class(): string {
        $custom_class = $this->get('wrapper_custom_class');

        $classes = ['elzo-forms-field-wrapper'];

        if ($custom_class) {
            $classes[] = $custom_class;
        }

        return implode(' ', $classes);
    }

    /**
     * Get field wrapper attributes as HTML string.
     *
     * @return string
     */
    public function get_wrapper_attributes(): string {
        $logic_rules = $this->get_logic_rules();
        $attributes = [];

        $attributes[] = 'class="' . esc_attr($this->get_wrapper_class()) . '"';
        $attributes[] = 'id="' . esc_attr($this->get_wrapper_id()) . '"';
        $attributes[] = 'data-ef-field-id="' . esc_attr((string) $this->get_id()) . '"';

        if ($logic_rules) {
            $json_logic_rules = wp_json_encode($logic_rules, JSON_UNESCAPED_UNICODE);
            if (false !== $json_logic_rules) {
                $attributes[] = "data-ef-logic='" . esc_attr($json_logic_rules) . "'";
            }

            // Hide by default if has logic rules and no width class
            $width = $this->get('width');
            if (empty($width)) {
                $attributes[] = 'style="display:none"';
            }
        }

        return implode(' ', $attributes);
    }

    /**
     * Get field attributes as HTML string.
     *
     * @param array $form_settings Optional form settings
     * @return string
     */
    public function get_field_attributes(array $form_settings = []): string {
        $logic_rules = $this->get_logic_rules();
        $attributes = [];

        $attributes[] = 'name="' . esc_attr($this->get_field_name()) . '"';
        $attributes[] = 'class="' . esc_attr($this->get_field_class($form_settings)) . '"';
        $attributes[] = 'id="' . esc_attr($this->get_field_id()) . '"';

        $placeholder = $this->get_placeholder();
        if ($placeholder) {
            $attributes[] = 'placeholder="' . esc_attr($placeholder) . '"';
        }

        if ($this->is_required() && !$logic_rules) {
            $attributes[] = 'required';
        }

        return implode(' ', $attributes);
    }

    /**
     * Get all field data for rendering.
     *
     * Caches result based on context to avoid recalculation.
     * Override in child classes to add field-specific data.
     *
     * @param array $context Additional context (form_settings, texts_settings, form_id, etc.)
     * @return array Complete field data for rendering
     */
    public function get_data(array $context = []): array {
        // Return cached data if context matches
        $context_key = md5(serialize($context)); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Used only to hash trusted render context; data is never unserialized.
        if (isset($this->render_data_cache[$context_key])) {
            return $this->render_data_cache[$context_key];
        }

        $form_settings = $context['form_settings'] ?? [];
        $step_index = $this->get('step_index', 0);

        $data = [
            // Field object
            'field' => $this,

            // Basic properties
            'field_id' => $this->get_id(),
            'field_index' => $this->get('index', 0),
            'field_type' => $this->get_type(),
            'required' => $this->is_required(),
            'step_index' => $step_index,

            // Labels and text
            'label' => $this->get_label(),
            'placeholder' => $this->get_placeholder(),
            'under_label' => (string) $this->get('under_label', ''),
            'under_field' => (string) $this->get('under_field', ''),

            // Display flags
            'shows_label_wrapper' => $this->shows_label_wrapper(),
            'shows_under_field' => $this->shows_under_field(),

            // Objects and logic
            'logic_rules' => $this->get_logic_rules(),

            // Values
            'default_value' => $this->get_default_value(),
            'value' => $this->get_value(),

            // Attributes
            'name' => $this->get_field_name(),
            'id' => $this->get_field_id(),
            'class' => $this->get_field_class($form_settings),
            'wrapper_id' => $this->get_wrapper_id(),
            'wrapper_class' => $this->get_wrapper_class(),

            // Context
            'form_id' => (int) ($context['form_id'] ?? 0),
            'form_settings' => $form_settings,
            'texts_settings' => $context['texts_settings'] ?? [],

            // Column width
            'width_class' => $this->get_column_width_class(),
        ];

        // Cache the result
        $this->render_data_cache[$context_key] = $data;

        return $data;
    }

    /**
     * Get allowed operators for conditional logic.
     *
     * Return null to use the full operator list, or an empty array to disable
     * conditional logic comparisons for this field type.
     *
     * Child classes can override this to define their own logic capabilities.
     *
     * @return array|null
     */
    public function get_logic_operators(): ?array {
        return null;
    }

    /**
     * Get default string comparison operators for conditional logic.
     *
     * Useful for field types that compare string values or option values.
     *
     * @return array
     */
    protected function get_string_logic_operators(): array {
        return \ElzoForms\Utilities\Conditional_Logic::get_string_operators();
    }

    /**
     * Get selectable values available for conditional logic.
     *
     * Child classes can override this if their comparable values are not stored
     * in the standard options array.
     *
     * @return array
     */
    public function get_logic_value_options(): array {
        $options = $this->get('options', []);

        return is_array($options) ? $options : [];
    }

    /**
     * Get the preferred value source for conditional logic UI.
     *
     * Supported values:
     * - text: free text input
     * - options: dropdown built from comparable values
     * - disabled: no value input should be available
     *
     * Child classes can override this to customize admin logic behavior.
     *
     * @return string
     */
    public function get_logic_value_source(): string {
        $logic_operators = $this->get_logic_operators();

        if (is_array($logic_operators) && count($logic_operators) === 0) {
            return 'disabled';
        }

        if (!empty($this->get_logic_value_options())) {
            return 'options';
        }

        return 'text';
    }

    /**
     * Get field data prepared for the admin editor.
     *
     * Includes logic metadata so the JS admin UI can work without hardcoded
     * knowledge of built-in field types.
     *
     * @return array
     */
    public function get_admin_field_data(): array {
        $field_data = $this->to_array();

        $field_data['logic_operators'] = $this->get_logic_operators();
        $field_data['logic_value_source'] = $this->get_logic_value_source();
        $field_data['logic_value_options'] = $this->get_logic_value_options();
        $field_data['read_only'] = $this->is_read_only();

        return $field_data;
    }

    /**
     * Get column width class for this field.
     *
     * @return string
     */
    public function get_column_width_class(): string {
        return \ElzoForms\Utilities\Helpers::get_column_width_class($this->get('width'));
    }

    /**
     * Sanitize submitted input before validation.
     *
     * This method is the request-boundary sanitizer used by the submission
     * handler. It MUST remain pure: do not write files or database records,
     * make remote requests, or trigger other externally visible side effects.
     * Field types with structured values should override this method and
     * validate their exact input shape before returning a sanitized value.
     *
     * The default implementation checks scalar/flat-array structure and
     * delegates to sanitize(), which every field type must implement.
     *
     * @param mixed $value Raw submitted field value.
     * @return mixed|\WP_Error Sanitized value, or an error for malformed input.
     */
    public function sanitize_input($value) {
        $shape_result = $this->validate_input_shape($value);
        if (is_wp_error($shape_result)) {
            return $shape_result;
        }

        return $this->sanitize($value);
    }

    /**
     * Validate an already sanitized field value.
     *
     * MUST be implemented by child classes. The submission handler guarantees
     * that sanitize_input() has completed successfully before calling this
     * extensible method.
     *
     * @param mixed $value Sanitized field value.
     * @return true|\WP_Error True if valid, WP_Error with validation message if invalid.
     */
    abstract public function validate($value);

    /**
     * Sanitize field value.
     *
     * MUST be implemented by child classes.
     * Should normalize input and remove dangerous content. This method should
     * be pure whenever the default sanitize_input() implementation delegates
     * to it. A field type whose sanitize() has side effects must override
     * sanitize_input() with a pure one and defer the side effect to
     * finalize_submission_value().
     *
     * @param mixed $value Field value
     * @return mixed Sanitized value
     */
    abstract public function sanitize($value);

    /**
     * Finalize a validated submission value.
     *
     * Side-effectful work that must happen only after every form field has
     * passed validation belongs here. Most fields need no finalization.
     *
     * @param mixed $value Sanitized and validated field value.
     * @return mixed|\WP_Error Final submission value, or an error on failure.
     */
    public function finalize_submission_value($value) {
        return $value;
    }

    /**
     * Render field HTML.
     *
     * Attempts to load template file first, then falls back to render_html() method.
     * Uses cached data from get_data() to avoid recalculation.
     *
     * Rendering priority:
     * 1. Template file (field-types/field-{type}.php)
     * 2. render_html() method
     *
     * @param array $args Additional context (form_settings, form_id, texts_settings, etc.)
     * @return string Field HTML output
     */
    public function render(array $args = []): string {
        // Get complete field data with all calculated properties
        $field_data = $this->get_data($args);

        // Try to locate template (checks theme, then plugin, then custom paths)
        $type = $this->get_type();
        $template_name = 'field-types/field-' . $type . '.php';
        $located_template = \ElzoForms\Utilities\Template_Loader::locate_template($template_name, $field_data);

        // If template exists, use it
        if (file_exists($located_template)) {
            return \ElzoForms\Utilities\Template_Loader::get_template($template_name, $field_data);
        }

        // Otherwise, fallback to render_html() method if child class implements it
        if (method_exists($this, 'render_html')) {
            return $this->render_html($field_data);
        }

        // If neither template nor render_html() is available, return empty string
        return '';
    }

    /**
     * Render field HTML directly (without template).
     *
     * Override this method in child classes if you want to render HTML
     * directly instead of using a template file.
     *
     * Template files take precedence if both exist, so you can provide
     * both a template and this method for flexibility.
     *
     * @param array $field_data Field data with all context (from get_data())
     * @return string Field HTML
     */
    protected function render_html(array $field_data): string {
        // Default implementation - child classes should override
        return '';
    }

    /**
     * Render field-specific settings in admin for a specific tab.
     *
     * Override this method in child classes to add field-specific settings.
     * The variables $field, $field_id, $step_index, $field_index are available.
     *
     * @param string $tab The settings tab (general, view, logic, admin, etc.)
     */
    public function render_field_settings(string $tab): void {
        if ($tab === 'view') {
            $this->render_under_text_settings();
        }

        /**
         * Action hook for third-party field types to add settings.
         *
         * @param string $tab The settings tab
         * @param array $field Field data array
         * @param int $step_index Step index
         * @param int $field_index Field index
         */
        do_action('elzo_forms_field_settings_' . $tab, $this->to_array(), $this->get('step_index', 0), $this->get('index', 0));
    }

    /**
     * Render the shared "text under label" and "text under field" controls.
     *
     * Every field type that supports the setting gets the control from here, so
     * the markup is not repeated per type and a new type cannot silently miss it.
     */
    protected function render_under_text_settings(): void {
        $field_id = $this->get_id();
        $step_index = $this->get('step_index', 0);
        $field_index = $this->get('index', 0);

        if ($this->supports_under_label()) {
            $under_label = $this->get('under_label', '');
            ?>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-under_label-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Text under label', 'elzo-forms'); ?></label>
                <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][under_label]" id="elzo-forms-field-under_label-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" value="<?php echo esc_attr($under_label); ?>">
            </div>
            <?php
        }

        if ($this->supports_under_field()) {
            $under_field = $this->get('under_field', '');
            ?>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-under_field-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Text under field', 'elzo-forms'); ?></label>
                <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][under_field]" id="elzo-forms-field-under_field-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" value="<?php echo esc_attr($under_field); ?>">
            </div>
            <?php
        }
    }

    /**
     * Sanitize URL-encoded JSON payloads.
     *
     * sanitize_text_field() strips percent-encoded sequences, so it would destroy
     * a URL-encoded JSON payload. Instead of relaxing the sanitizer for the whole
     * string, the payload is decoded, confirmed to be real JSON, sanitized value
     * by value with the field's own text sanitizer, and re-encoded in the same
     * wire format. A string that only looks percent-encoded but does not decode
     * to JSON is rejected here and falls back to normal text sanitization.
     *
     * @param mixed    $value     Field value
     * @param callable $sanitizer Text sanitizer applied to every string inside the payload
     * @return string|null Sanitized URL-encoded JSON value, or null if not detected
     */
    protected function sanitize_urlencoded_json($value, $sanitizer = 'sanitize_text_field'): ?string {
        if (!is_string($value) || $value === '') {
            return null;
        }

        // Must look like a percent-encoded JSON object or array.
        if (!preg_match('/%[0-9A-Fa-f]{2}/', $value)
            || (stripos($value, '%7B') === false && stripos($value, '%5B') === false)) {
            return null;
        }

        $decoded = json_decode(rawurldecode($value), true, self::MAX_JSON_PAYLOAD_DEPTH);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        if (!is_callable($sanitizer)) {
            $sanitizer = 'sanitize_text_field';
        }

        $encoded = wp_json_encode(
            $this->sanitize_decoded_json_payload($decoded, $sanitizer),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if (!is_string($encoded)) {
            return null;
        }

        return rawurlencode($encoded);
    }

    /**
     * Recursively sanitize a decoded JSON payload.
     *
     * Keys and string values go through the field's text sanitizer; int, float,
     * bool and null are already safe scalars and keep their original type.
     *
     * @param mixed    $data      Decoded payload node
     * @param callable $sanitizer Text sanitizer
     * @return mixed
     */
    private function sanitize_decoded_json_payload($data, callable $sanitizer) {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $item) {
                $sanitized_key = is_string($key) ? $sanitizer($key) : $key;
                $sanitized[$sanitized_key] = $this->sanitize_decoded_json_payload($item, $sanitizer);
            }

            return $sanitized;
        }

        return is_string($data) ? $sanitizer($data) : $data;
    }

    /**
     * Magic getter for array access compatibility.
     */
    public function __get($key) {
        return $this->get($key);
    }

    /**
     * Magic setter for array access compatibility.
     */
    public function __set($key, $value) {
        $this->set($key, $value);
    }

    /**
     * Magic isset for array access compatibility.
     */
    public function __isset($key) {
        return isset($this->data[$key]);
    }

    /**
     * ArrayAccess: offsetExists.
     */
    public function offsetExists($offset): bool {
        return isset($this->data[$offset]);
    }

    /**
     * ArrayAccess: offsetGet.
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        return $this->data[$offset] ?? null;
    }

    /**
     * ArrayAccess: offsetSet.
     */
    public function offsetSet($offset, $value): void {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * ArrayAccess: offsetUnset.
     */
    public function offsetUnset($offset): void {
        unset($this->data[$offset]);
    }
}
