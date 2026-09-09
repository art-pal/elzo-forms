<?php
/**
 * Step model class.
 *
 * Represents a single step in a multi-step form. Manages fields, validation,
 * and progress tracking within the step.
 *
 * **Features:**
 * - Field management and conversion to Field objects
 * - Validation for all fields in step
 * - Progress tracking based on required fields
 * - Layout information for responsive rendering
 * - WordPress filter application to fields
 *
 * @package ElzoForms\Form
 * @since 1.0.0
 */

namespace ElzoForms\Form;

use ElzoForms\Field\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Step implements \ArrayAccess {

    /** @var int Step index (0-based position in form) */
    protected int $index = 0;

    /** @var string Step label/title */
    protected string $label = '';

    /** @var array Complete step data array */
    protected array $data = [];

    /** @var Field[] Array of Field objects in this step */
    protected array $fields = [];

    /** @var int Form this step belongs to; 0 when the step was built alone */
    protected int $form_id = 0;

    /**
     * Create Step instance from data (static factory method).
     *
     * Convenience method for creating Step objects from arrays.
     * All field data is automatically converted to Field objects.
     *
     * @param array $data Step data array (should include 'fields' key)
     * @param int $index Step position (0-based) in the form
     * @param int $form_id Form the step belongs to
     * @return Step New Step instance
     */
    public static function from(array $data, int $index = 0, int $form_id = 0): Step {
        return new self($data, $index, $form_id);
    }

    /**
     * Constructor.
     *
     * Initializes step with data and converts field arrays to Field objects.
     * Sets default values for missing properties.
     *
     * @param array $data Step data array (label, fields, etc.)
     * @param int $index Step position (0-based) in the form
     * @param int $form_id Form the step belongs to; 0 when there is none
     */
    public function __construct(array $data = [], int $index = 0, int $form_id = 0) {
        $this->data = wp_parse_args($data, $this->get_defaults());
        $this->index = $index;
        $this->form_id = max(0, $form_id);
        $this->label = $this->data['label'] ?? '';

        // Convert field arrays to Field objects
        if (!empty($this->data['fields']) && is_array($this->data['fields'])) {
            $this->fields = $this->convert_fields_to_objects($this->data['fields']);
        }
    }

    /**
     * Get step defaults.
     *
     * Returns default structure for step data ensuring consistent shape.
     *
     * @return array Default step data structure
     */
    protected function get_defaults(): array {
        return [
            'label' => '',
            'fields' => [],
        ];
    }

    /**
     * Convert field arrays to Field objects.
     *
     * Transforms raw field data into Field instances with step/index metadata.
     * Already-instantiated Field objects are passed through unchanged.
     *
     * @param array $fields Field data arrays
     * @return Field[] Array of Field objects
     */
    protected function convert_fields_to_objects(array $fields): array {
        $field_objects = [];

        foreach ($fields as $field_index => $field) {
            // Add step_index and index to field data
            if (is_array($field)) {
                $field['step_index'] = $this->index;
                $field['index'] = $field_index;
            }

            // Convert to Field object (or keep if already an object)
            $field_object = $field instanceof Field ? $field : Field::from($field);
            $field_objects[] = $field_object->set_form_id($this->form_id);
        }

        return $field_objects;
    }

    /**
     * Get step index.
     *
     * @return int Step position (0-based) in the form
     */
    public function get_index(): int {
        return $this->index;
    }

    /**
     * Get the form this step belongs to, or 0 when it was built alone.
     */
    public function get_form_id(): int {
        return $this->form_id;
    }

    /**
     * Get step label.
     *
     * Returns the step's configured label, or a default "Step N" label.
     *
     * @return string Step label/title
     */
    public function get_label(): string {
        if (!empty($this->label)) {
            return $this->label;
        }

        /* translators: %d: Step number (1-based). */
        return sprintf(__('Step %d', 'elzo-forms'), $this->index + 1);
    }

    /**
     * Get a specific property value.
     *
     * Generic getter for accessing step properties.
     * Useful for flexible property access in templates and modules.
     *
     * Examples:
     * - $step->get('index') - Step position
     * - $step->get('label') - Step label
     * - $step->get('fields') - Field objects
     *
     * @param string $key Property name
     * @return mixed Property value or null
     */
    public function get(string $key) {
        switch ($key) {
            case 'index':
                return $this->get_index();
            case 'label':
                return $this->get_label();
            case 'fields':
                return $this->fields;
            case 'field_count':
                return $this->get_field_count();
            case 'has_visible_fields':
                return $this->has_visible_fields();
            case 'is_first':
                return $this->is_first();
            default:
                return $this->data[$key] ?? null;
        }
    }

    /**
     * Get all step data as array.
     *
     * Returns complete step data including fields and metadata.
     *
     * @return array Step data array
     */
    public function to_array(): array {
        return $this->data;
    }

    /**
     * Get step fields as Field objects.
     *
     * @return Field[] Array of Field objects in this step
     */
    public function fields(): array {
        return $this->fields;
    }

    /**
     * Get field by index.
     *
     * @param int $index Field position in step (0-based)
     * @return Field|null Field object or null if not found
     */
    public function field(int $index): ?Field {
        return $this->fields[$index] ?? null;
    }

    /**
     * Add field to step.
     *
     * Appends a field to the step's field list.
     * Returns self for method chaining.
     *
     * @param Field $field Field object to add
     * @return self For method chaining
     */
    public function add_field(Field $field): self {
        $this->fields[] = $field;
        return $this;
    }

    /**
     * Get total number of fields.
     *
     * @return int Field count
     */
    public function get_field_count(): int {
        return count($this->fields);
    }

    /**
     * Check if step has any visible fields.
     *
     * Counts all fields except hidden fields (type='hidden').
     * Used to determine if step should be displayed to user.
     *
     * @return bool True if step has visible fields
     */
    public function has_visible_fields(): bool {
        foreach ($this->fields as $field) {
            $type = $field->get_type();
            if (!in_array($type, ['hidden'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if step has a submit button.
     *
     * Looks for a button field with button_type='submit'.
     *
     * @return bool True if step contains a submit button
     */
    public function has_submit_button(): bool {
        foreach ($this->fields as $field) {
            if ($field->get_type() === 'button' && $field->get('button_type', 'button') === 'submit') {
                return true;
            }
        }
        return false;
    }

    /**
     * Get required fields in this step.
     *
     * Returns only fields where is_required() returns true.
     * Used for validation and progress tracking.
     *
     * @return Field[] Array of required Field objects
     */
    public function get_required_fields(): array {
        return array_filter($this->fields, function($field) {
            return $field->is_required();
        });
    }

    /**
     * Check if step is complete based on submission data.
     *
     * Step is complete when all required fields have non-empty values.
     * Used for multi-step validation and progress tracking.
     *
     * @param array $submission_data Submitted form data (field_id => value)
     * @return bool True if all required fields are filled
     */
    public function is_complete(array $submission_data): bool {
        $required_fields = $this->get_required_fields();

        foreach ($required_fields as $field) {
            $field_id = $field->get_id();
            $value = $submission_data[$field_id] ?? null;

            if (empty($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate all fields in this step.
     *
     * Sanitizes and validates each field in the step without finalizing any
     * side effects.
     * Collects all errors and returns them as a single WP_Error.
     *
     * @param array $data Field data to validate (field_id => value)
     * @return bool|\WP_Error True if valid, WP_Error with messages if invalid
     */
    public function validate(array $data) {
        $errors = [];

        foreach ($this->fields as $field) {
            $field_id = $field->get_id();
            $has_submitted_value = array_key_exists($field_id, $data);
            $value = $has_submitted_value ? $data[$field_id] : '';

            // Only request data needs to cross the input sanitizer. A missing
            // value is represented by a server-created empty string so this
            // validation-only method never triggers field finalization.
            $sanitized_value = $has_submitted_value ? $field->sanitize_input($value) : $value;
            if (is_wp_error($sanitized_value)) {
                $errors[] = $sanitized_value->get_error_message();
                continue;
            }

            // Validate the already sanitized value.
            $validation_result = $field->validate($sanitized_value);

            if (is_wp_error($validation_result)) {
                $errors[] = $validation_result->get_error_message();
            }
        }

        if (!empty($errors)) {
            return new \WP_Error('validation_failed', implode(' ', $errors));
        }

        return true;
    }

    /**
     * Get completion progress (0.0 to 1.0).
     *
     * Calculates progress as percentage of required fields that are filled.
     * Returns 1.0 if step has no required fields.
     *
     * @param array $submission_data Submitted form data (field_id => value)
     * @return float Progress from 0.0 (0%) to 1.0 (100%)
     */
    public function get_progress(array $submission_data): float {
        $required_count = count($this->get_required_fields());

        if ($required_count === 0) {
            return 1.0;
        }

        $completed_count = 0;
        foreach ($this->get_required_fields() as $field) {
            $field_id = $field->get_id();
            if (!empty($submission_data[$field_id])) {
                $completed_count++;
            }
        }

        return $completed_count / $required_count;
    }

    /**
     * Apply WordPress filter to all fields.
     *
     * Applies filter to each field and recreates field if modified.
     * Useful for dynamic field modifications.
     * Returns self for method chaining.
     *
     * @param string $filter_name WordPress filter hook name
     * @return self For method chaining
     */
    public function apply_field_filter(): self {
        foreach ($this->fields as $field_index => $field) {
            // Apply filter to field data
            $field_data = apply_filters('elzo_forms_field', $field->to_array(), $field);

            // Update field if filter modified it
            if ($field_data !== $field->to_array()) {
                $this->fields[$field_index] = Field::from($field_data);
            }
        }

        return $this;
    }

    /**
     * Check if this is the first step.
     *
     * @return bool True if step index is 0
     */
    public function is_first(): bool {
        return $this->index === 0;
    }

    /**
     * Get layout information for fields in rows and columns.
     *
     * Returns array with field layout metadata for rendering responsive layouts.
     * Each item contains:
     * - field: Field object
     * - field_index: Field position in step
     * - opens_row: Whether this field opens a new row
     * - closes_row: Whether this field closes a row
     * - width_class: CSS class for field width
     * - column_class: Full CSS class for wrapping
     *
     * @return array Layout information for each field
     */
    public function get_field_layout(): array {
        $layout = [];
        $field_count = count($this->fields);

        foreach ($this->fields as $field_index => $field) {
            $current_width = $field->get_column_width_class();

            // Check if previous and next fields have width classes
            $prev_has_width = false;
            $next_has_width = false;

            if ($field_index > 0 && isset($this->fields[$field_index - 1])) {
                $prev_has_width = !empty($this->fields[$field_index - 1]->get_column_width_class());
            }

            if ($field_index < $field_count - 1 && isset($this->fields[$field_index + 1])) {
                $next_has_width = !empty($this->fields[$field_index + 1]->get_column_width_class());
            }

            // Determine if we should open or close a row
            // Open row if: current field has width AND previous field has NO width
            $opens_row = $current_width && !$prev_has_width;

            // Close row if: current field has width AND next field has NO width
            $closes_row = $current_width && !$next_has_width;

            $layout[] = [
                'field' => $field,
                'field_index' => $field_index,
                'opens_row' => $opens_row,
                'closes_row' => $closes_row,
                'width_class' => $current_width,
                'column_class' => 'elzo-forms-column ' . $current_width,
            ];
        }

        return $layout;
    }

    /**
     * Get step data for rendering.
     *
     * Returns complete step information including fields, layout, and metadata.
     * Applies field filters before returning.
     *
     * @return array Rendering-ready step data
     */
    public function get_data(): array {
        // Apply filter to each field before rendering
        $this->apply_field_filter();

        return [
            'step_index' => $this->index,
            'label' => $this->get_label(),
            'fields' => $this->fields,
            'field_count' => $this->get_field_count(),
            'has_visible_fields' => $this->has_visible_fields(),
            'is_first' => $this->is_first(),
            'field_layout' => $this->get_field_layout(),
        ];
    }

    // ========================================
    // ArrayAccess Implementation
    // ========================================

    /**
     * Check if offset exists (ArrayAccess).
     *
     * Allows checking properties like: isset($step['label'])
     *
     * @param mixed $offset Property name
     * @return bool True if property exists
     */
    public function offsetExists($offset): bool {
        return $this->get($offset) !== null;
    }

    /**
     * Get offset value (ArrayAccess).
     *
     * Allows accessing properties like: $step['label']
     *
     * @param mixed $offset Property name
     * @return mixed Property value
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        return $this->get($offset);
    }

    /**
     * Set offset value (ArrayAccess) - NOT SUPPORTED.
     *
     * Step objects are read-only via array access.
     *
     * @param mixed $offset Property name
     * @param mixed $value Property value
     * @return void
     * @throws \BadMethodCallException Always throws - steps are read-only
     */
    public function offsetSet($offset, $value): void {
        throw new \BadMethodCallException(
            'Step objects are read-only. Modify form data and recreate steps.'
        );
    }

    /**
     * Unset offset (ArrayAccess) - NOT SUPPORTED.
     *
     * Step objects are read-only via array access.
     *
     * @param mixed $offset Property name
     * @return void
     * @throws \BadMethodCallException Always throws - steps are read-only
     */
    public function offsetUnset($offset): void {
        throw new \BadMethodCallException(
            'Step objects are read-only. Modify form data and recreate steps.'
        );
    }
}
