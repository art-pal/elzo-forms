<?php
/**
 * Admin utilities and helpers.
 *
 * @package ElzoForms\Utilities
 */

namespace ElzoForms\Utilities;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Admin {
    /**
     * Build a privacy-safe link to the PRO page for free-plugin admin surfaces.
     *
     * Campaign values are deliberately static. Do not add site URLs, user data,
     * form IDs, or other installation-specific values to these parameters.
     *
     * @param string $placement Known admin link placement.
     */
    public static function get_pro_url(string $placement): string {
        $content_by_placement = [
            'form_automations_title' => 'form_automations_title',
            'form_automations_button' => 'form_automations_button',
            'settings_automations_title' => 'settings_automations_title',
            'settings_automations_button' => 'settings_automations_button',
        ];

        $utm_content = $content_by_placement[$placement] ?? 'plugin_admin';

        return add_query_arg([
            'utm_source' => 'elzo_forms_free',
            'utm_medium' => 'plugin',
            'utm_campaign' => 'upgrade_to_pro',
            'utm_content' => $utm_content,
        ], ELZO_FORMS_PRO_URL);
    }

    /**
     * Get field types with class mappings and labels.
     *
     * @param string|null $key Optional field type key to retrieve specific type
     * @param string $return 'all' returns full data, 'label' returns just labels, 'class' returns just class names
     * @return mixed Array of types or specific type data
     */
    public static function get_field_types(?string $key = null, string $return = 'all') {
        $types = [
            'text' => [
                'label' => __('Text', 'elzo-forms'),
                'class' => \ElzoForms\Field\Field_Text::class,
            ],
            'textarea' => [
                'label' => __('Textarea', 'elzo-forms'),
                'class' => \ElzoForms\Field\Field_Textarea::class,
            ],
            'select' => [
                'label' => __('Select', 'elzo-forms'),
                'class' => \ElzoForms\Field\Field_Select::class,
            ],
            'checkbox' => [
                'label' => __('Checkbox', 'elzo-forms'),
                'class' => \ElzoForms\Field\Field_Checkbox::class,
            ],
            'radio' => [
                'label' => __('Radio', 'elzo-forms'),
                'class' => \ElzoForms\Field\Field_Radio::class,
            ],
            'range' => [
                'label' => __('Range', 'elzo-forms'),
                'class' => \ElzoForms\Field\Field_Range::class,
            ],
            'file' => [
                'label' => __('File', 'elzo-forms'),
                'class' => \ElzoForms\Field\Field_File::class,
            ],
            'hidden' => [
                'label' => __('Hidden', 'elzo-forms'),
                'class' => \ElzoForms\Field\Field_Hidden::class,
            ],
            'content' => [
                'label' => __('Content', 'elzo-forms'),
                'class' => \ElzoForms\Field\Field_Content::class,
            ],
            'button' => [
                'label' => __('Button', 'elzo-forms'),
                'class' => \ElzoForms\Field\Field_Button::class,
            ],
        ];

        // Apply filter - third parties can add: ['rating' => ['label' => 'Rating', 'class' => Rating_Field::class]]
        $types = apply_filters('elzo_forms_field_types', $types);

        // Return specific key
        if ($key) {
            if (!isset($types[$key])) {
                return null;
            }

            if ($return === 'label') {
                return $types[$key]['label'] ?? $key;
            } elseif ($return === 'class') {
                return $types[$key]['class'] ?? null;
            }

            return $types[$key];
        }

        // Return all types in requested format
        if ($return === 'label') {
            return array_map(function($type) {
                return $type['label'] ?? '';
            }, $types);
        } elseif ($return === 'class') {
            return array_map(function($type) {
                return $type['class'] ?? '';
            }, $types);
        }

        return $types;
    }

    /**
     * Get field tabs.
     */
    public static function get_field_tabs(?string $key = null) {
        $tabs = [
            'general' => __('General', 'elzo-forms'),
            'view' => __('View', 'elzo-forms'),
            'logic' => __('Logic', 'elzo-forms'),
            'admin' => __('Admin', 'elzo-forms'),
        ];

        // Apply filter
        $tabs = apply_filters('elzo_forms_admin_field_tabs', $tabs);

        return $key && isset($tabs[$key]) ? $tabs[$key] : $tabs;
    }

    /**
     * Convert options array to string format.
     */
    public static function options_to_string($options): string {
        return is_array($options) ? implode("\n", array_map(function($option){
            return $option['value'] . (!empty($option['label']) || !empty($option['description']) ? ' : ' . (!empty($option['label']) ? $option['label'] : '') . (!empty($option['description']) ? (!empty($option['label'])?' ':'').'|| '.$option['description'] : '') : '');
        }, $options)) : '';
    }

    /**
     * Get field text subtypes.
     */
    public static function get_field_text_subtypes(?string $key = null) {
        $subtypes = [
            'text' => __('Text','elzo-forms'),
            'date' => __('Date','elzo-forms'),
            'time' => __('Time','elzo-forms'),
            'number' => __('Number','elzo-forms'),
            'email' => __('Email','elzo-forms'),
            'url' => __('URL','elzo-forms'),
            'tel' => __('Telephone','elzo-forms'),
            'password' => __('Password','elzo-forms'),
        ];

        // Apply filter
        $subtypes = apply_filters('elzo_forms_field_text_subtypes', $subtypes);

        // Return subtype or all subtypes
        return $key && isset($subtypes[$key]) ? $subtypes[$key] : $subtypes;
    }

    /**
     * Get field widths.
     */
    public static function get_field_widths(?string $key = null) {
        $widths = [
            '1/1' => __('Full (1/1)','elzo-forms'),
            '1/2' => __('Half (1/2)','elzo-forms'),
            '1/3' => __('Third (1/3)','elzo-forms'),
            '1/4' => __('Quarter (1/4)','elzo-forms'),
        ];

        // Apply filter
        $widths = apply_filters('elzo_forms_field_widths', $widths);

        // Return width or all widths
        return $key && isset($widths[$key]) ? $widths[$key] : $widths;
    }

    /**
     * Get the field width to show in the field header.
     *
     * Full width is the default, so "1/1" tells nothing about the layout: the
     * header shows the first width that actually narrows the field instead.
     *
     * @param mixed $width Field width array, keyed by responsive keypoint.
     */
    public static function get_field_header_width($width): string {
        if (empty($width) || !is_array($width)) {
            return '';
        }

        foreach ($width as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '' && $value !== '1/1') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Get radio/checkbox layouts.
     */
    public static function get_field_radio_checkbox_layouts(?string $key = null) {
        $layouts = [
            'vertical' => __('Vertical','elzo-forms'),
            'inline' => __('Inline','elzo-forms'),
            'two-columns' => __('Two columns','elzo-forms'),
        ];

        return $key && isset($layouts[$key]) ? $layouts[$key] : $layouts;
    }

    /**
     * Get checkbox styles.
     */
    public static function get_field_checkbox_styles(?string $key = null) {
        $styles = [
            'checkmark' => __('Checkmark','elzo-forms'),
            'toggler' => __('Toggler','elzo-forms'),
        ];

        return $key && isset($styles[$key]) ? $styles[$key] : $styles;
    }

    /**
     * Get conditional logic operators.
     */
    public static function get_field_logic_operators(?string $key = null) {
        $operators = \ElzoForms\Utilities\Conditional_Logic::get_operator_labels();

        return $key && isset($operators[$key]) ? $operators[$key] : $operators;
    }

    /**
     * Get form alert types.
     *
     * @param string|null $key Optional alert type key to retrieve specific type
     * @return mixed Array of alert types or specific type label
     */
    public static function get_alert_types(?string $key = null) {
        $types = [
            'modal' => __('In a modal', 'elzo-forms'),
            'inside' => __('Inside the form', 'elzo-forms'),
        ];

        // Apply filter
        $types = apply_filters('elzo_forms_alert_types', $types);

        // Return specific key or all types
        return $key && isset($types[$key]) ? $types[$key] : $types;
    }

    /**
     * Handle mime types.
     */
    public static function handle_mime_types(string $mime_types_string, string $return_format = 'string') {
        $mime_types = sanitize_text_field($mime_types_string);
        $mime_types = strpos($mime_types, ',') !== false ? array_map('trim', explode(',', $mime_types)) : [trim($mime_types)];

        // Loop through mime types
        foreach($mime_types as $key => $mime_type){
            $mime_type = trim($mime_type);

            // Check if mime type is empty
            if(empty($mime_type)){
                unset($mime_types[$key]);
                continue;
            }

            // Check if mime type is a file extension
            if(strpos($mime_type, '/') === false){
                $mime_types[$key] = '.' . str_replace('.', '', $mime_type);
            // Check if mime type is a wildcard
            } else if($mime_type === '*'){
                $mime_types[$key] = '*';
            } else {
                // Check if mime type is a wildcard with a prefix
                if(strpos($mime_type, '/*') !== false){
                    $mime_types[$key] = str_replace('/*', '', $mime_type);
                }
            }
        }

        // Return mime types as a string
        return $return_format == 'array' ? $mime_types : implode(', ', $mime_types);
    }
}
