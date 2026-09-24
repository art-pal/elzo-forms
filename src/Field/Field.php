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
 * - render_submission_value($value, $context): string - Safe admin submission HTML
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

    /** @var \ElzoForms\Form\Form_Instance|null Form instance being rendered. */
    private $render_instance = null;

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

        $class_name = self::get_class_for_type($field['type'] ?? 'text');

        return new $class_name($field);
    }

    /**
     * Get the class implementing a field type.
     *
     * Variants share the class of their base type: "text:email" is a
     * Field_Text. Unknown types fall back to the Text field class.
     *
     * @param mixed $type Field type identifier.
     * @return string Class name.
     */
    protected static function get_class_for_type($type): string {
        $base_type = is_scalar($type) ? Field_Type::get_base_type((string) $type) : 'text';
        $field_types = self::get_registered_field_types();
        $class_name = $field_types[$base_type] ?? $field_types['text'] ?? Field_Text::class;

        return class_exists($class_name) ? $class_name : Field_Text::class;
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
     * @deprecated 1.1.0 Use the elzo_forms_field_types filter, which also
     *             gives the type its label in the builder.
     *
     * The type is added to the same registry the filter feeds, so field
     * creation, the builder's type picker and type changes all accept it.
     *
     * @param string $type Field type key: letters, digits, "_" and "-" only.
     * @param string $class_name Fully qualified class name
     */
    public static function register_field_type(string $type, string $class_name): void {
        _deprecated_function(__METHOD__, '1.1.0', 'the elzo_forms_field_types filter');

        if (!Field_Type::is_valid_key($type)) {
            _doing_it_wrong(
                __METHOD__,
                esc_html__('Field type keys may only contain letters, digits, "_" and "-"; ":" separates a base type from its subtype.', 'elzo-forms'),
                '1.1.0'
            );
            return;
        }

        add_filter('elzo_forms_field_types', static function ($types) use ($type, $class_name) {
            $types = is_array($types) ? $types : [];
            $definition = isset($types[$type]) && is_array($types[$type]) ? $types[$type] : ['label' => $type];
            $definition['class'] = $class_name;
            $types[$type] = $definition;

            return $types;
        });

        // The class map is cached from the registry; the next lookup rebuilds it.
        self::$field_types = [];
    }

    /**
     * Constructor.
     *
     * @param array $field Field data array
     */
    public function __construct($field = []) {
        $defaults = $this->get_defaults();
        $this->data = wp_parse_args($field, $defaults);

        // "type" is the canonical composite type. Fields saved before composite
        // types keep their Text variant in "subtype"; it is folded into "type"
        // here, so the next save stores the composite format only.
        $this->data = Field_Type::normalize_field($this->data, (string) ($defaults['type'] ?? 'text'));

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
        $class_name = self::get_class_for_type($field['type'] ?? 'text');

        return (new $class_name($field))->to_array();
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
     * Bind the field to the form instance being rendered.
     *
     * Set by Form::render() for the duration of one render. While bound, every
     * HTML ID of the field is namespaced by the instance; without an instance
     * the field keeps the IDs of Elzo Forms 1.1.
     *
     * @param \ElzoForms\Form\Form_Instance|null $instance Instance, or null to unbind.
     * @return self For method chaining.
     */
    public function set_render_instance(?\ElzoForms\Form\Form_Instance $instance): self {
        $this->render_instance = $instance;

        return $this;
    }

    /**
     * Get the form instance being rendered.
     *
     * @return \ElzoForms\Form\Form_Instance|null
     */
    public function get_render_instance(): ?\ElzoForms\Form\Form_Instance {
        return $this->render_instance;
    }

    /**
     * Render a saved value in the submission admin or notification email.
     *
     * Contract: return safe HTML, escaping text, URLs and attributes here.
     * Custom fields may override this without changing the admin presenter.
     * Context contains channel (admin/email), submission_field, submission_id
     * and form_id when available. Email renderers must not depend on admin CSS.
     *
     * @param mixed $value Saved submission value, not frontend input.
     * @param array $context Presentation context.
     * @return string Safe HTML, ready to output without further escaping.
     */
    public function render_submission_value($value, array $context = []): string {
        $text = self::submission_value_to_text($value);

        return $text !== '' ? esc_html($text) : '-';
    }

    /**
     * Convert saved values to plain text for generic display and editors.
     *
     * Nested imported values are represented as JSON rather than triggering
     * array-to-string warnings. Escaping belongs to the output caller.
     *
     * @param mixed $value Saved value.
     * @return string Plain text.
     */
    public static function submission_value_to_text($value): string {
        if (is_array($value)) {
            return implode(', ', array_map(static function ($item): string {
                return is_array($item) ? (string) wp_json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (is_scalar($item) ? (string) $item : '');
            }, $value));
        }

        return is_scalar($value) ? (string) $value : '';
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
     * Get the field type identifier, a composite such as "text:email" for variants.
     *
     * @return string
     */
    public function get_type(): string {
        return $this->data['type'] ?? 'text';
    }

    /**
     * Get the base type, e.g. "text" for "text:email".
     *
     * Use it wherever behavior belongs to the field implementation rather
     * than to one variant: templates, CSS classes, type checks.
     *
     * @return string
     */
    public function get_base_type(): string {
        return Field_Type::get_base_type($this->get_type());
    }

    /**
     * Get the validated subtype of the field type, e.g. "email" for "text:email".
     *
     * @return string Empty for types without variants.
     */
    public function get_subtype(): string {
        return Field_Type::get_subtype($this->get_type());
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

        return !in_array($this->get_base_type(), $excluded_types, true);
    }

    /**
     * Check if field type supports the text under field setting.
     *
     * @return bool
     */
    public function supports_under_field(): bool {
        $excluded_types = ['hidden'];

        return !in_array($this->get_base_type(), $excluded_types, true);
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
     * @param string $form_instance_suffix Suffix of Elzo Forms 1.1, applied only
     *                                     while no form instance is bound.
     * @return string
     */
    public function get_field_id(string $form_instance_suffix = ''): string {
        $custom_id = $this->get_custom_id('custom_id');

        if ($this->render_instance) {
            return $this->render_instance->field_element_id($this->get_id(), '', $custom_id);
        }

        $base_id = $custom_id !== '' ? $custom_id : 'elzo-forms-field-' . $this->get_id();

        return $base_id . self::normalize_form_instance_suffix($form_instance_suffix);
    }

    /**
     * Get the HTML ID of an element that belongs to the field.
     *
     * Field templates use it for every ID besides the control and the wrapper,
     * so a template override can add elements that stay unique too.
     *
     * @param string $part Element name, such as "label", "help" or "option-2".
     * @return string
     */
    public function get_element_id(string $part): string {
        if ($this->render_instance) {
            return $this->render_instance->field_element_id($this->get_id(), $part);
        }

        return $this->get_field_id() . '-' . $part;
    }

    /**
     * Get the HTML ID of the field label.
     *
     * @return string
     */
    public function get_label_id(): string {
        return $this->get_element_id('label');
    }

    /**
     * Get the HTML ID of the text under the label.
     *
     * @return string
     */
    public function get_description_id(): string {
        return $this->get_element_id('description');
    }

    /**
     * Get the HTML ID of the text under the field.
     *
     * @return string
     */
    public function get_help_id(): string {
        return $this->get_element_id('help');
    }

    /**
     * Get the HTML ID of a choice option's input.
     *
     * @param int $position Option position, from 1.
     * @return string
     */
    public function get_option_id(int $position): string {
        if ($this->render_instance) {
            return $this->get_element_id('option-' . $position);
        }

        return $this->get_field_id() . '-' . $position;
    }

    /**
     * Get the IDs of the texts that describe the field.
     *
     * @return string Space-separated IDs for aria-describedby, or an empty string.
     */
    public function get_described_by(): string {
        $ids = [];

        if ($this->shows_label_wrapper() && (string) $this->get('under_label', '') !== '') {
            $ids[] = $this->get_description_id();
        }

        if ($this->shows_under_field()) {
            $ids[] = $this->get_help_id();
        }

        return implode(' ', $ids);
    }

    /**
     * Read an author-chosen HTML ID setting.
     *
     * @param string $key Setting key.
     * @return string
     */
    private function get_custom_id(string $key): string {
        $custom_id = $this->get($key);

        return !empty($custom_id) && is_scalar($custom_id) ? trim((string) $custom_id) : '';
    }

    /**
     * Get field CSS class attribute.
     *
     * @param array $form_settings Optional form settings
     * @return string
     */
    public function get_field_class(array $form_settings = []): string {
        $type = $this->get_base_type();
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
     * @param string $form_instance_suffix Suffix of Elzo Forms 1.1, applied only
     *                                     while no form instance is bound.
     * @return string
     */
    public function get_wrapper_id(string $form_instance_suffix = ''): string {
        $custom_id = $this->get_custom_id('wrapper_custom_id');

        if ($this->render_instance) {
            return $this->render_instance->field_element_id($this->get_id(), 'wrapper', $custom_id);
        }

        $base_id = $custom_id !== '' ? $custom_id : 'elzo-forms-field-wrapper-' . $this->get_id();

        return $base_id . self::normalize_form_instance_suffix($form_instance_suffix);
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
     * @param string $form_instance_suffix Suffix of Elzo Forms 1.1, applied only
     *                                     while no form instance is bound.
     * @return string
     */
    public function get_wrapper_attributes(string $form_instance_suffix = ''): string {
        $logic_rules = $this->get_logic_rules();
        $attributes = [];

        $attributes[] = 'class="' . esc_attr($this->get_wrapper_class()) . '"';
        $attributes[] = 'id="' . esc_attr($this->get_wrapper_id($form_instance_suffix)) . '"';
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
     * @param string $form_instance_suffix Suffix of Elzo Forms 1.1, applied only
     *                                     while no form instance is bound.
     * @return string
     */
    public function get_field_attributes(array $form_settings = [], string $form_instance_suffix = ''): string {
        $logic_rules = $this->get_logic_rules();
        $attributes = [];

        $attributes[] = 'name="' . esc_attr($this->get_field_name()) . '"';
        $attributes[] = 'class="' . esc_attr($this->get_field_class($form_settings)) . '"';
        $attributes[] = 'id="' . esc_attr($this->get_field_id($form_instance_suffix)) . '"';

        $described_by = $this->get_described_by();
        if ($described_by !== '') {
            $attributes[] = 'aria-describedby="' . esc_attr($described_by) . '"';
        }

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
        // Return cached data if context and form instance match
        $instance_id = $this->render_instance ? $this->render_instance->get_id() : '';
        $context_key = md5($instance_id . '|' . serialize($context)); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Used only to hash trusted render context; data is never unserialized.
        if (isset($this->render_data_cache[$context_key])) {
            return $this->render_data_cache[$context_key];
        }

        $form_settings = $context['form_settings'] ?? [];
        $step_index = $this->get('step_index', 0);
        $form_instance_suffix = self::normalize_form_instance_suffix($context['form_instance_suffix'] ?? '');

        $data = [
            // Field object
            'field' => $this,

            // Basic properties
            'field_id' => $this->get_id(),
            'field_index' => $this->get('index', 0),
            // Templates are selected and styled per base type.
            'field_type' => $this->get_base_type(),
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
            'id' => $this->get_field_id($form_instance_suffix),
            'class' => $this->get_field_class($form_settings),
            'wrapper_id' => $this->get_wrapper_id($form_instance_suffix),
            'wrapper_class' => $this->get_wrapper_class(),
            'label_id' => $this->get_label_id(),
            'description_id' => $this->get_description_id(),
            'help_id' => $this->get_help_id(),
            'described_by' => $this->get_described_by(),

            // Context
            'form_id' => (int) ($context['form_id'] ?? 0),
            'form_instance' => $this->render_instance,
            'form_instance_id' => $this->render_instance ? $this->render_instance->get_id() : '',
            'form_instance_suffix' => $form_instance_suffix,
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
     * Accept only the numeric suffixes Form::render() generated in Elzo Forms 1.1.
     *
     * The suffix affects DOM identifiers only. Stored field IDs, input names,
     * data-ef-field-id and the submission payload remain unchanged.
     *
     * @param mixed $suffix Render-instance suffix.
     */
    private static function normalize_form_instance_suffix($suffix): string {
        if (!is_string($suffix) || !preg_match('/^-(?:[2-9]|[1-9][0-9]+)$/', $suffix)) {
            return '';
        }

        return $suffix;
    }

    /**
     * Get allowed operators for conditional logic.
     *
     * Return null to offer every value operator, or an empty array to disable
     * conditional logic comparisons for this field type. The number-of-values
     * operators are not part of that default: a field that can submit several
     * values lists them itself, as Checkbox does.
     *
     * Child classes can override this to define their own logic capabilities.
     *
     * @return array|null
     */
    public function get_logic_operators(): ?array {
        return null;
    }

    /**
     * Get the operators the builder offers for this field, with the default resolved.
     *
     * Counting the values of a field that submits a single value would only
     * restate whether it is filled, so the default leaves those operators out.
     *
     * @return array
     */
    public function get_admin_logic_operators(): array {
        $operators = $this->get_logic_operators();
        if (is_array($operators)) {
            return array_values($operators);
        }

        return array_values(array_diff(
            \ElzoForms\Utilities\Conditional_Logic::get_supported_operators(),
            \ElzoForms\Utilities\Conditional_Logic::get_count_operators()
        ));
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
     * Get the hint shown in the conditional logic value input.
     *
     * Child classes override this when the value a condition compares against
     * is not obvious from the field itself.
     *
     * @return string
     */
    public function get_logic_value_placeholder(): string {
        return '';
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

        $field_data['logic_operators'] = $this->get_admin_logic_operators();
        $field_data['logic_value_source'] = $this->get_logic_value_source();
        $field_data['logic_value_options'] = $this->get_logic_value_options();
        $field_data['logic_value_placeholder'] = $this->get_logic_value_placeholder();
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
        $type = $this->get_base_type();
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
