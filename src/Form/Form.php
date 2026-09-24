<?php
/**
 * Form model class.
 *
 * Represents a form with its data, settings, and steps. Provides methods for
 * accessing form properties, rendering, and managing form lifecycle.
 *
 * @package ElzoForms\Form
 * @since 1.0.0
 */

namespace ElzoForms\Form;

use ElzoForms\Services\Settings;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Form implements \ArrayAccess {

    /** @var int Form ID */
    protected $id = 0;

    /** @var \WP_Post Form post object */
    protected $post = null;

    /** @var array Form data */
    protected $data = [];

    /** @var Step[] Array of Step objects */
    protected $steps_objects = [];

    /** @var array|null Cached merged settings */
    private $cached_settings = null;

    /** @var array|null Cached merged texts */
    private $cached_texts = null;

    /** @var array|null Cached merged styles */
    private $cached_styles = null;

    /**
     * Constructor.
     *
     * Initialize form from various sources:
     * - int: Load form by post ID
     * - WP_Post: Load form from post object
     * - array: Create form from data array (useful for testing/programmatic forms)
     *
     * @param int|\WP_Post|array $form Form ID, post object, or data array
     */
    public function __construct($form = 0) {
        if (is_numeric($form)) {
            $this->id = intval($form);
            $this->post = get_post($this->id);
        } elseif ($form instanceof \WP_Post) {
            $this->post = $form;
            $this->id = $form->ID;
        } elseif (is_array($form)) {
            $this->data = wp_parse_args($form, $this->get_defaults());
            return;
        }

        // Load form data from post
        if ($this->post && $this->post->post_type === 'elzo_form') {
            $this->load_data();
        } else {
            $this->data = $this->get_defaults();
        }
    }

    /**
     * Get form defaults.
     *
     * Returns the default structure for form data. This ensures
     * all forms have a consistent data structure.
     *
     * @return array Default form data structure
     */
    protected function get_defaults(): array {
        return [
            'steps' => [],
            'settings' => [],
            'texts' => [],
        ];
    }

    /**
     * Load form data from post content.
     *
     * Loads form data from post_content (JSON) with JSON file support.
     * If a JSON file exists, it takes precedence over the database.
     *
     * @return void
     */
    protected function load_data(): void {
        if (!$this->post || !$this->post->post_content) {
            $this->data = $this->get_defaults();
            return;
        }

        $decoded = Form_Data_Normalizer::decode_form_json((string) $this->post->post_content);
        $data = $decoded ? wp_parse_args($decoded, $this->get_defaults()) : $this->get_defaults();

        // Allow loading from JSON files
        // If JSON file exists, it takes precedence over database
        $data = apply_filters('elzo_forms/load_form_data', $data, $this->post);

        $this->data = $data;
    }

    /**
     * Get form ID.
     *
     * @return int Form post ID
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Get form key (post name/slug).
     *
     * Returns the form slug which is used as a unique identifier.
     * This is useful for module integration and form identification.
     *
     * @return string Form slug/key
     */
    public function get_key(): string {
        if (!$this->post) {
            return '';
        }

        $post_name = $this->post->post_name;
        if (!is_scalar($post_name)) {
            return '';
        }

        return (string) $post_name;
    }

    /**
     * Get form slug (alias for get_key).
     *
     * @return string Form slug
     */
    public function get_slug(): string {
        return $this->get_key();
    }

    /**
     * Get form post object.
     *
     * @return \WP_Post|null WordPress post object or null if not loaded from database
     */
    public function get_post(): ?\WP_Post {
        return $this->post;
    }

    /**
     * Get form title.
     *
     * @return string Form title or empty string if not available
     */
    public function get_title(): string {
        return $this->post ? get_the_title($this->post) : '';
    }

    /**
     * Get form status.
     *
     * @return string Form post status (publish, draft, etc.)
     */
    public function get_status(): string {
        return $this->post ? $this->post->post_status : '';
    }

    /**
     * Check if form is published.
     *
     * @return bool True if form status is 'publish'
     */
    public function is_published(): bool {
        return $this->get_status() === 'publish';
    }

    /**
     * Get a specific property value.
     *
     * Generic getter that allows accessing form properties and data.
     * Useful for modules and templates.
     *
     * Examples:
     * - $form->get('key') - Form slug
     * - $form->get('title') - Form title
     * - $form->get('steps') - Form steps array
     * - $form->get('settings') - Form-specific settings
     *
     * @param string $key Property name to get
     * @return mixed Property value or null if not found
     */
    public function get(string $key) {
        // Map common property names to getter methods
        switch ($key) {
            case 'id':
            case 'ID':
                return $this->get_id();
            case 'key':
            case 'slug':
            case 'post_name':
                return $this->get_key();
            case 'title':
            case 'post_title':
                return $this->get_title();
            case 'status':
            case 'post_status':
                return $this->get_status();
            case 'post':
                return $this->get_post();
            default:
                // Return from data array
                return $this->data[$key] ?? null;
        }
    }

    /**
     * Get all form data as array.
     *
     * Returns the complete form data structure including steps, settings, and texts.
     *
     * @return array Complete form data array
     */
    public function to_array(): array {
        return $this->data;
    }

    /**
     * Get form steps data as raw arrays.
     *
     * Returns steps as arrays. Use steps() method to get Step objects instead.
     *
     * @return array Array of step data arrays
     * @see steps() For getting Step objects
     */
    public function get_steps(): array {
        return $this->data['steps'] ?? [];
    }

    /**
     * Get form steps as Step objects.
     *
     * @return Step[] Array of Step objects
     */
    public function steps(): array {
        // Return cached Step objects if already created
        if (!empty($this->steps_objects)) {
            return $this->steps_objects;
        }

        $steps_data = $this->get_steps();

        foreach ($steps_data as $step_index => $step_data) {
            $this->steps_objects[] = Step::from($step_data, $step_index, $this->get_id());
        }

        return $this->steps_objects;
    }

    /**
     * Get step by index.
     *
     * Accepts integer or numeric-string indexes and normalizes them.
     *
     * @param int|string $index Step index
     * @return Step|null
     */
    public function step($index): ?Step {
        if (is_string($index) && ctype_digit($index)) {
            $index = (int) $index;
        } elseif (!is_int($index)) {
            return null;
        }

        if ($index < 0) {
            return null;
        }

        $steps = $this->steps();
        return $steps[$index] ?? null;
    }

    /**
     * Get all form fields as raw arrays (flattened from all steps).
     *
     * Returns all fields from all steps as a flat array of field data.
     * This is useful for processing all fields at once.
     *
     * @return array Flattened array of field data
     */
    public function get_fields(): array {
        $fields = [];
        foreach ($this->get_steps() as $step) {
            if (!empty($step['fields']) && is_array($step['fields'])) {
                $fields = array_merge($fields, $step['fields']);
            }
        }
        return $fields;
    }

    /**
     * Get form-specific settings only (NOT merged with global).
     *
     * Returns only the settings defined in this form's data.
     * For merged settings, use get_settings() instead.
     *
     * @return array Form-specific settings array
     * @see get_settings() For merged global + form settings
     */
    public function get_form_settings(): array {
        return $this->data['settings'] ?? [];
    }

    /**
     * Get form-specific texts only (NOT merged with global).
     *
     * Returns only the text overrides defined in this form's data.
     * For merged texts, use get_texts() instead.
     *
     * @return array Form-specific text overrides
     * @see get_texts() For merged global + form texts
     */
    public function get_form_texts(): array {
        return $this->data['texts'] ?? [];
    }

    /**
     * Get merged settings (global + form-specific).
     *
     * Returns settings with proper inheritance: global settings are merged
     * with form-specific overrides. Results are cached per instance.
     *
     * @return array Merged settings array
     */
    public function get_settings(): array {
        if ($this->cached_settings !== null) {
            return $this->cached_settings;
        }

        $this->cached_settings = Settings::get_form_settings([
            'form_id' => $this->id,
            'form_post' => $this->post,
        ]);

        return $this->cached_settings;
    }

    /**
     * Get merged text settings (global + form-specific).
     *
     * Returns text strings with proper inheritance. Results are cached per instance.
     *
     * @return array Merged text settings array
     */
    public function get_texts(): array {
        if ($this->cached_texts !== null) {
            return $this->cached_texts;
        }

        $this->cached_texts = Settings::get_texts_settings([
            'form_id' => $this->id,
            'form_post' => $this->post,
        ]);

        return $this->cached_texts;
    }

    /**
     * Get merged style settings (global + form-specific).
     *
     * Returns style configuration with proper inheritance. Results are cached per instance.
     *
     * @return array Merged style settings array
     */
    public function get_styles(): array {
        if ($this->cached_styles !== null) {
            return $this->cached_styles;
        }

        $this->cached_styles = Settings::get_style_settings([
            'form_id' => $this->id,
            'form_post' => $this->post,
        ]);

        return $this->cached_styles;
    }

    /**
     * Get a specific setting value with automatic global fallback.
     *
     * Returns a single setting with proper inheritance. If not set at form level,
     * falls back to global settings.
     *
     * @param string $key Setting key to retrieve
     * @param mixed $default Default value if setting is not found
     * @return mixed Setting value or default
     */
    public function get_setting(string $key, $default = null) {
        $settings = $this->get_settings();

        // Check if value exists and is not an empty string (but allow '0')
        if (isset($settings[$key]) && $settings[$key] !== '') {
            return $settings[$key];
        }

        return $default;
    }

    /**
     * Get a specific text value with automatic global fallback.
     *
     * Returns a single text string with proper inheritance.
     *
     * @param string $key Text key to retrieve
     * @param string $default Default value if text is not found
     * @return string Text value or default
     */
    public function get_text(string $key, string $default = ''): string {
        $texts = $this->get_texts();
        return $texts[$key] ?? $default;
    }

    /**
     * Get a specific style value with automatic global fallback.
     *
     * Returns a single style value with proper inheritance.
     *
     * @param string $key Style key to retrieve
     * @param mixed $default Default value if style is not found
     * @return mixed Style value or default
     */
    public function get_style(string $key, $default = null) {
        $styles = $this->get_styles();
        return $styles[$key] ?? $default;
    }

    /**
     * Check if the current user can interact with this form.
     *
     * Published forms are public. Non-published forms require permission to
     * edit the specific form, which also supports author-owned drafts.
     *
     * @return bool True if the current user can interact with the form
     */
    public function can_interact(): bool {
        if (!$this->post || $this->post->post_type !== 'elzo_form') {
            return false;
        }

        if ($this->is_published()) {
            return true;
        }

        return current_user_can('edit_post', $this->id);
    }

    /**
     * Check if the current user can view this form.
     *
     * Kept as a compatibility alias for integrations using the original API.
     *
     * @return bool True if the current user can view the form
     */
    public function can_view(): bool {
        return $this->can_interact();
    }

    /**
     * Reduce a max_width rendering attribute to one CSS length.
     *
     * The value is written into an inline style and comes from shortcode and
     * block authors, so only a positive number with an optional length or
     * percentage unit is kept. A number without a unit is read as pixels.
     *
     * @param mixed $value Requested maximum width.
     * @return string CSS length, or an empty string when the value is not one.
     */
    private static function sanitize_max_width($value): string {
        if (!is_scalar($value)) {
            return '';
        }

        $value = strtolower(trim((string) $value));
        if (!preg_match('/^(\d+(?:\.\d+)?|\.\d+)(px|%|rem|em|vw|vh|vmin|vmax|ch|ex)?$/', $value, $matches) || (float) $matches[1] <= 0) {
            return '';
        }

        return $matches[1] . (isset($matches[2]) && $matches[2] !== '' ? $matches[2] : 'px');
    }

    /**
     * Reduce a text_align rendering attribute to a CSS text-align keyword.
     *
     * @param mixed $value Requested text alignment.
     * @return string Keyword, or an empty string when the value is not one.
     */
    private static function sanitize_text_align($value): string {
        $value = is_scalar($value) ? strtolower(trim((string) $value)) : '';

        return in_array($value, ['left', 'center', 'right', 'justify', 'start', 'end'], true) ? $value : '';
    }

    /**
     * Render form HTML.
     *
     * Generates complete form HTML with all steps, fields, and styling.
     * Boots modules for this form and applies all necessary hooks.
     *
     * @param array $atts Rendering attributes:
     *                    - echo (bool): Echo output directly instead of returning
     *                    - max_width (string|int): Maximum form width: a number of pixels,
     *                      or a CSS length such as '600px', '40rem' or '80%'
     *                    - form_align (string): Form alignment ('left', 'right'; any other
     *                      non-empty value centers the form)
     *                    - text_align (string): Text alignment ('left', 'center', 'right',
     *                      'justify', 'start', 'end')
     * @return string Form HTML output (empty string if echo is true)
     */
    public function render(array $atts = []): string {
        $atts = wp_parse_args($atts, [
            'echo' => false,
            'max_width' => '',
            'form_align' => '',
            'text_align' => '',
        ]);

        // Validate form exists and is correct post type
        if (!$this->post || $this->post->post_type !== 'elzo_form') {
            return '<p>' . esc_html__('Form not found', 'elzo-forms') . '</p>';
        }

        // Check interaction permissions
        if (!$this->can_interact()) {
            return '';
        }

        // Build wrapper styles. A layout value that is not a plain length or
        // keyword is dropped, so an attribute cannot add declarations of its own.
        $form_wrapper_styles = '';

        $form_wrapper_max_width = self::sanitize_max_width($atts['max_width']);
        if ($form_wrapper_max_width !== '') {
            $form_wrapper_styles .= 'max-width: ' . $form_wrapper_max_width . ';';
        }

        if (!empty($atts['form_align'])) {
            $form_wrapper_form_align = is_scalar($atts['form_align']) ? (string) $atts['form_align'] : '';
            if ($form_wrapper_form_align === 'left') {
                $form_wrapper_styles .= 'margin-right: auto;';
            } elseif ($form_wrapper_form_align === 'right') {
                $form_wrapper_styles .= 'margin-left: auto;';
            } else {
                $form_wrapper_styles .= 'margin-left: auto; margin-right: auto;';
            }
        }

        $form_wrapper_text_align = self::sanitize_text_align($atts['text_align']);
        if ($form_wrapper_text_align !== '') {
            $form_wrapper_styles .= 'text-align: ' . $form_wrapper_text_align . ';';
        }

        // Get settings/styles/texts
        $form_id = $this->id;
        $form_post = $this->post;
        $form_object = $this; // Pass form object to template
        $form_settings = $this->get_settings();
        $texts_settings = $this->get_texts();
        $style_settings = $this->get_styles();
        $steps = $this->steps(); // Array of Step objects
        $steps_total = count($steps);

        // Compute form HTML attributes
        $form_custom_id = !empty($form_settings['form_custom_id']) && is_scalar($form_settings['form_custom_id'])
            ? trim((string) $form_settings['form_custom_id'])
            : '';
        $form_custom_class = !empty($form_settings['form_custom_class']) && is_scalar($form_settings['form_custom_class'])
            ? trim((string) $form_settings['form_custom_class'])
            : '';
        // Each render is its own instance; its ID namespaces every HTML ID the
        // templates print, so forms sharing a page never share one.
        $form_instance = Form_Instance::create($form_id);
        $form_instance_id = $form_instance->get_id();
        $form_attr_id = $form_instance->form_element_id($form_custom_id);
        $form_nonce_id = $form_instance->element_id('nonce');
        // Suffix read by template overrides written for Elzo Forms 1.1.
        $form_instance_suffix = $form_instance->get_legacy_suffix();
        foreach ($steps as $step) {
            foreach ($step->fields() as $field) {
                $field->set_render_instance($form_instance);
            }
        }
        $form_attr_class = 'elzo-forms-form elzo-forms-input-style-' . ($style_settings['input_style'] ?? 'default') . ($form_custom_class ? ' ' . $form_custom_class : '');

        // Boot modules for this form
        $manager = elzo_forms_modules_manager();
        if ($manager) {
            $enabled_modules = $manager->enabled_for_form($form_id);
            foreach ($enabled_modules as $module_id) {
                $module = $manager->get($module_id);
                if ($module) {
                    $settings = $manager->settings_for_form($module_id, $form_id);
                    $module->on_form_boot($this, $settings);

                    // Enqueue assets only for form-specific modules (not globally enabled)
                    // Global modules are enqueued during wp_enqueue_scripts hook
                    if (!$manager->is_globally_enabled($module_id)) {
                        $module->enqueue_front_assets();
                    }
                }
            }
        }

        // Allow modules to add data attributes (must run after module boot)
        $form_data_attributes = apply_filters('elzo_forms_form_data_attributes', [], $form_id);
        $form_data_attrs_string = '';
        foreach ($form_data_attributes as $key => $value) {
            if ($key === null || $value === null) {
                continue;
            }
            $form_data_attrs_string .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
        }

        // Render template using template loader (supports theme overrides)
        $output = \ElzoForms\Utilities\Template_Loader::get_template('form.php', compact(
            'form_id', 'form_post', 'form_object', 'form_settings',
            'texts_settings', 'style_settings', 'steps', 'steps_total',
            'form_wrapper_styles', 'form_attr_id', 'form_attr_class',
            'form_data_attrs_string', 'form_instance', 'form_instance_id',
            'form_nonce_id', 'form_instance_suffix', 'atts'
        ));

        // The fields may be rendered again as another instance.
        foreach ($steps as $step) {
            foreach ($step->fields() as $field) {
                $field->set_render_instance(null);
            }
        }

        if ($atts['echo']) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form markup is generated by trusted plugin templates where output escaping is handled per value.
            echo $output;
            return '';
        }

        return $output;
    }

    /**
     * Static helper method to render form by ID.
     *
     * Convenience method for quickly rendering a form without creating an instance.
     * Used by shortcodes and helper functions.
     *
     * @param array $atts Rendering attributes (must include 'id')
     * @return string Form HTML output
     */
    public static function render_by_id(array $atts = []): string {
        $form_id = intval($atts['id'] ?? 0);
        if ($form_id <= 0) {
            return '<p>' . esc_html__('Invalid form ID', 'elzo-forms') . '</p>';
        }
        $form = new self($form_id);
        return $form->render($atts);
    }

    // ========================================
    // ArrayAccess Implementation
    // ========================================

    /**
     * Check if offset exists (ArrayAccess).
     *
     * Allows checking properties like: isset($form['title'])
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
     * Allows accessing properties like: $form['title']
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
     * Form objects are read-only via array access.
     *
     * @param mixed $offset Property name
     * @param mixed $value Property value
     * @return void
     * @throws \BadMethodCallException Always throws - forms are read-only
     */
    public function offsetSet($offset, $value): void {
        throw new \BadMethodCallException(
            'Form objects are read-only. Use form builder or post meta to modify forms.'
        );
    }

    /**
     * Unset offset (ArrayAccess) - NOT SUPPORTED.
     *
     * Form objects are read-only via array access.
     *
     * @param mixed $offset Property name
     * @return void
     * @throws \BadMethodCallException Always throws - forms are read-only
     */
    public function offsetUnset($offset): void {
        throw new \BadMethodCallException(
            'Form objects are read-only. Use form builder or post meta to modify forms.'
        );
    }
}
