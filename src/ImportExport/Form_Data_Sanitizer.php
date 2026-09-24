<?php
/**
 * Sanitizer for imported form data.
 *
 * Imported form data is untrusted. It is rebuilt here from known keys in the
 * stored form shape, through the same field, step and settings filters and
 * normalizers as a save in the form editor, so an import stores exactly what
 * saving the same form in the editor would store.
 *
 * Only the core form schema is returned. Data beyond it is added by the
 * importer for the active edition, and by extensions in the
 * elzo_forms/import_export/import_form filter, from the raw item data.
 *
 * @package ElzoForms\ImportExport
 */

namespace ElzoForms\ImportExport;

use ElzoForms\Field\Field;
use ElzoForms\Field\Field_Type;
use ElzoForms\Form\Compatibility\MinimumVersion;
use ElzoForms\Form\Form_Data_Normalizer;
use ElzoForms\Utilities\Admin;
use ElzoForms\Utilities\Conditional_Logic;

// Exit if accessed directly
defined('ABSPATH') || exit;

final class Form_Data_Sanitizer {

    /** Top-level keys of the core form data schema. */
    public const CORE_KEYS = ['steps', 'settings', 'texts', 'modules', MinimumVersion::KEY];

    /** Field, setting and text keys are machine names; anything else is dropped. */
    private const KEY_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    /** Upper bound of fields accepted from one imported form. */
    private const MAX_FIELDS = 1000;

    /** @var array<string, bool> */
    private $unregistered_field_types = [];

    /** @var array<string, bool> */
    private $inactive_condition_types = [];

    /** @var array<string, bool> */
    private $unknown_modules = [];

    /** @var bool Whether a condition compares against IDs of the site the form was made on */
    private $references_site_ids = false;

    /** @var array<string, string>|null */
    private $condition_types = null;

    /** @var string[]|null */
    private $condition_operators = null;

    /** @var int */
    private $field_count = 0;

    /**
     * Sanitize imported form data.
     *
     * @param array $data Raw form data.
     * @return array Sanitized core form data.
     */
    public function sanitize(array $data): array {
        $this->unregistered_field_types = [];
        $this->inactive_condition_types = [];
        $this->unknown_modules = [];
        $this->references_site_ids = false;
        $this->condition_types = Conditional_Logic::get_condition_types('field');
        $this->condition_operators = null;
        $this->field_count = 0;

        $steps = $this->sanitize_steps($data['steps'] ?? []);
        $steps = Form_Data_Normalizer::normalize_field_keys($steps);
        $steps = Form_Data_Normalizer::drop_dangling_logic_rules($steps);

        $sanitized = [
            'steps' => $steps,
            'settings' => $this->sanitize_settings($data['settings'] ?? []),
            'texts' => $this->sanitize_texts($data['texts'] ?? []),
            'modules' => $this->sanitize_modules($data['modules'] ?? []),
        ];

        if (MinimumVersion::is_valid($data[MinimumVersion::KEY] ?? null)) {
            $sanitized[MinimumVersion::KEY] = MinimumVersion::normalize($data[MinimumVersion::KEY]);
        }

        return $sanitized;
    }

    /**
     * Describe what the last sanitize() call kept or dropped.
     *
     * @return string[]
     */
    public function get_notices(): array {
        $notices = [];

        if ($this->unregistered_field_types) {
            $notices[] = sprintf(
                /* translators: %s: Comma-separated list of field types. */
                __('Some fields use types that are not available on this site (%s). They are kept and render as text fields until their type is available.', 'elzo-forms'),
                implode(', ', array_keys($this->unregistered_field_types))
            );
        }

        if ($this->inactive_condition_types) {
            $notices[] = sprintf(
                /* translators: %s: Comma-separated list of condition types. */
                __('Some conditions use condition types that are not active on this site (%s). They are kept, but count as not met until their type is active again.', 'elzo-forms'),
                implode(', ', array_keys($this->inactive_condition_types))
            );
        }

        if ($this->references_site_ids) {
            $notices[] = __('This form contains conditions that refer to WordPress page or user IDs. The same IDs can mean different pages or users on another site, so review these conditions after importing the form to another site.', 'elzo-forms');
        }

        if ($this->unknown_modules) {
            $notices[] = sprintf(
                /* translators: %s: Comma-separated list of module IDs. */
                __('Settings of modules that are not available on this site are not imported: %s.', 'elzo-forms'),
                implode(', ', array_keys($this->unknown_modules))
            );
        }

        return $notices;
    }

    /**
     * Sanitize the form steps.
     *
     * @param mixed $steps Raw steps.
     * @return array
     */
    private function sanitize_steps($steps): array {
        if (!is_array($steps)) {
            return [];
        }

        $sanitized = [];

        foreach (array_values($steps) as $position => $step) {
            if (!is_array($step) || !isset($step['fields']) || !is_array($step['fields'])) {
                continue;
            }

            $sanitized_step = [
                'index' => isset($step['index']) && is_scalar($step['index']) ? absint($step['index']) : $position + 1,
                'label' => isset($step['label']) && is_scalar($step['label']) ? sanitize_text_field((string) $step['label']) : '',
                'fields' => [],
            ];

            foreach (array_values($step['fields']) as $field) {
                if (!is_array($field) || $this->field_count >= self::MAX_FIELDS) {
                    continue;
                }

                $this->field_count++;
                $sanitized_step['fields'][] = $this->sanitize_field($field);
            }

            // Same filter as a save in the form editor.
            $sanitized_step = apply_filters('elzo_forms_step_before_save', $sanitized_step);
            if (is_array($sanitized_step)) {
                $sanitized[] = $sanitized_step;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize one field definition.
     *
     * Field definitions stay open to third-party field types, so unknown keys
     * are kept when they are plain machine names holding scalar values, as in
     * the form editor. Structured values are accepted only for the keys the
     * core knows the shape of.
     *
     * @param array $field Raw field definition.
     * @return array
     */
    private function sanitize_field(array $field): array {
        $sanitized = [];

        foreach ($field as $key => $value) {
            if (!is_string($key) || !preg_match(self::KEY_PATTERN, $key)) {
                continue;
            }

            switch ($key) {
                case 'options':
                    $sanitized[$key] = $this->sanitize_options($value);
                    break;
                case 'rules':
                    $sanitized[$key] = $this->sanitize_rules($value);
                    break;
                case 'width':
                    $sanitized[$key] = $this->sanitize_width($value);
                    break;
                case 'content':
                    if (is_scalar($value)) {
                        $sanitized[$key] = Form_Data_Normalizer::encode_content(Form_Data_Normalizer::decode_content((string) $value));
                    }
                    break;
                case 'allowed_file_types':
                    if (is_scalar($value)) {
                        $sanitized[$key] = Admin::handle_mime_types((string) $value);
                    }
                    break;
                default:
                    if (is_bool($value) || is_int($value) || is_float($value)) {
                        $sanitized[$key] = $value;
                    } elseif (is_string($value)) {
                        $sanitized[$key] = sanitize_text_field($value);
                    }
            }
        }

        foreach (['required', 'logic', 'primary_field', 'submission_table_field'] as $flag) {
            $sanitized[$flag] = !empty($sanitized[$flag]);
        }

        // Same normalization and filter as a save in the form editor.
        $sanitized = Field::normalize_definition($sanitized);
        $sanitized = apply_filters('elzo_forms_field_before_save', $sanitized);
        $sanitized = Field_Type::normalize_field(is_array($sanitized) ? $sanitized : []);

        $type = (string) $sanitized['type'];
        if (!Field_Type::is_registered($type)) {
            $this->unregistered_field_types[$type] = true;
        }

        return $sanitized;
    }

    /**
     * Sanitize stored choice options.
     *
     * @param mixed $options Raw options.
     * @return array
     */
    private function sanitize_options($options): array {
        if (!is_array($options)) {
            return [];
        }

        $sanitized = [];

        foreach (array_values($options) as $option) {
            if (is_scalar($option)) {
                $option = ['value' => $option];
            }

            if (!is_array($option)) {
                continue;
            }

            $value = isset($option['value']) && is_scalar($option['value']) ? sanitize_text_field((string) $option['value']) : '';
            $label = isset($option['label']) && is_scalar($option['label']) ? sanitize_text_field((string) $option['label']) : '';
            $description = isset($option['description']) && is_scalar($option['description']) ? sanitize_text_field((string) $option['description']) : '';

            if ($value === '' && $label === '') {
                continue;
            }

            $sanitized[] = [
                'value' => $value,
                'label' => $label !== '' ? $label : null,
                'description' => $description !== '' ? $description : null,
            ];
        }

        return $sanitized;
    }

    /**
     * Sanitize conditional logic groups.
     *
     * A condition type that is not registered right now is kept when it is a
     * clean machine name, as in the form editor, so a form moved while PRO or
     * an addon is inactive does not lose its rules.
     *
     * @param mixed $groups Raw rule groups.
     * @return array
     */
    private function sanitize_rules($groups): array {
        if (!is_array($groups)) {
            return [];
        }

        $sanitized = [];

        foreach (array_values($groups) as $group) {
            if (!is_array($group)) {
                continue;
            }

            $rules = [];

            foreach (array_values($group) as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                $type = isset($rule['type']) && is_scalar($rule['type']) ? (string) $rule['type'] : '';
                if (!Conditional_Logic::is_storable_condition_type($type, 'field')) {
                    continue;
                }

                $settings = [];
                foreach ((isset($rule['settings']) && is_array($rule['settings']) ? $rule['settings'] : []) as $setting_key => $setting_value) {
                    $setting_key = sanitize_key((string) $setting_key);
                    if ($setting_key === '') {
                        continue;
                    }

                    if (is_scalar($setting_value)) {
                        $settings[$setting_key] = sanitize_text_field((string) $setting_value);
                    } elseif (is_array($setting_value)) {
                        $settings[$setting_key] = array_values(array_map(static function ($item): string {
                            return sanitize_text_field((string) $item);
                        }, array_filter($setting_value, 'is_scalar')));
                    }
                }

                if (($type === 'field' && empty($settings['field_id'])) || ($type === 'cookie' && empty($settings['name']))) {
                    continue;
                }

                if (!isset($this->condition_types[$type])) {
                    $this->inactive_condition_types[$type] = true;
                }

                $operator = $this->sanitize_operator($rule['operator'] ?? '');
                if ($this->compares_site_ids($type, $operator)) {
                    $this->references_site_ids = true;
                }

                $rules[] = [
                    'type' => $type,
                    'operator' => $operator,
                    'settings' => $settings,
                ];
            }

            if ($rules) {
                $sanitized[] = $rules;
            }
        }

        return $sanitized;
    }

    /**
     * Whether a condition compares against IDs of the site the form was made on.
     *
     * Such IDs are kept as they are: on another site the same numbers can
     * point at different pages or users, which only a person can resolve.
     *
     * @param string $type Condition type.
     * @param string $operator Sanitized operator.
     * @return bool
     */
    private function compares_site_ids(string $type, string $operator): bool {
        if ($type === 'page' && strpos($operator, 'page_id_') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Keep an operator that is registered, or a plain machine name.
     *
     * Registered operators include symbols such as "<" that text sanitization
     * would mangle, so operators are validated rather than sanitized.
     *
     * @param mixed $operator Raw operator.
     * @return string
     */
    private function sanitize_operator($operator): string {
        if (!is_scalar($operator)) {
            return '';
        }

        $operator = trim((string) $operator);

        if ($this->condition_operators === null) {
            $this->condition_operators = [];
            foreach (Conditional_Logic::get_condition_operators_map('field') as $operators) {
                if (is_array($operators)) {
                    $this->condition_operators = array_merge($this->condition_operators, array_map('strval', $operators));
                }
            }
        }

        if (in_array($operator, $this->condition_operators, true)) {
            return $operator;
        }

        return preg_match('/^[a-z0-9_]{1,64}$/', $operator) ? $operator : '';
    }

    /**
     * Sanitize responsive field widths.
     *
     * @param mixed $width Raw widths.
     * @return array
     */
    private function sanitize_width($width): array {
        if (!is_array($width)) {
            return [];
        }

        $sanitized = [];
        foreach ($width as $keypoint => $value) {
            $keypoint = sanitize_key((string) $keypoint);
            if ($keypoint !== '' && is_scalar($value)) {
                $sanitized[$keypoint] = sanitize_text_field((string) $value);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize per-form settings through the editor's settings filters.
     *
     * @param mixed $settings Raw settings.
     * @return array
     */
    private function sanitize_settings($settings): array {
        if (!is_array($settings)) {
            return [];
        }

        $sanitized = [];

        foreach ($settings as $key => $value) {
            if (!is_string($key) || !preg_match(self::KEY_PATTERN, $key)) {
                continue;
            }

            if (is_array($value)) {
                $value = array_map(static function ($item): string {
                    return sanitize_text_field((string) $item);
                }, array_filter($value, 'is_scalar'));
            } elseif (is_scalar($value)) {
                $value = sanitize_text_field((string) $value);
            } else {
                continue;
            }

            $value = apply_filters('elzo_forms_form_settings_value_before_save', $value, $key);
            $value = apply_filters('elzo_forms_form_settings_' . $key . '_before_save', $value);

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Sanitize per-form texts through the editor's text filters.
     *
     * @param mixed $texts Raw texts.
     * @return array
     */
    private function sanitize_texts($texts): array {
        if (!is_array($texts)) {
            return [];
        }

        $sanitized = [];

        foreach ($texts as $key => $value) {
            if (!is_string($key) || !preg_match(self::KEY_PATTERN, $key) || !is_scalar($value)) {
                continue;
            }

            $value = sanitize_text_field((string) $value);
            $value = apply_filters('elzo_forms_form_texts_value_before_save', $value, $key);
            $value = apply_filters('elzo_forms_form_texts_' . $key . '_before_save', $value);

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Sanitize per-form module overrides against the registered modules.
     *
     * @param mixed $modules Raw module overrides.
     * @return array
     */
    private function sanitize_modules($modules): array {
        if (!is_array($modules)) {
            return [];
        }

        $manager = function_exists('elzo_forms_modules_manager') ? elzo_forms_modules_manager() : null;
        $sanitized = [];

        foreach ($modules as $module_id => $payload) {
            $module_id = sanitize_key((string) $module_id);
            if ($module_id === '' || !is_array($payload)) {
                continue;
            }

            $module = $manager ? $manager->get($module_id) : null;
            if ($manager && !$module) {
                $this->unknown_modules[$module_id] = true;
                continue;
            }

            $status = isset($payload['status']) && is_scalar($payload['status']) ? sanitize_key((string) $payload['status']) : 'inherit';
            if (!in_array($status, ['inherit', 'enabled', 'disabled'], true)) {
                $status = 'inherit';
            }

            $settings = [];
            if (!empty($payload['settings']) && is_array($payload['settings'])) {
                if ($manager && $module) {
                    $settings = $manager->sanitize_settings($payload['settings'], $module->get_settings_schema());
                } else {
                    foreach ($payload['settings'] as $setting_key => $setting_value) {
                        $setting_key = sanitize_key((string) $setting_key);
                        if ($setting_key !== '' && is_scalar($setting_value)) {
                            $settings[$setting_key] = sanitize_text_field((string) $setting_value);
                        }
                    }
                }
            }

            $sanitized[$module_id] = [
                'status' => $status,
                'settings' => $settings,
            ];
        }

        return $sanitized;
    }
}
