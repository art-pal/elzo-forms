<?php
/**
 * Form data normalization helpers.
 *
 * Shared by every path that writes a form definition, such as the editor save
 * handler and Import / Export, so each of them stores the same shape.
 *
 * @package ElzoForms\Form
 */

namespace ElzoForms\Form;

use ElzoForms\Utilities\Helpers;

// Exit if accessed directly
defined('ABSPATH') || exit;

final class Form_Data_Normalizer {

    private function __construct() {
    }

    /**
     * Build preferred source string for field key generation.
     *
     * @param array $field Field definition.
     * @return string
     */
    public static function get_field_key_source(array $field): string {
        foreach (['field_key', 'admin_label', 'label', 'placeholder'] as $candidate_key) {
            $candidate = isset($field[$candidate_key]) && is_scalar($field[$candidate_key]) ? trim((string) $field[$candidate_key]) : '';
            if ($candidate !== '') {
                return $candidate;
            }
        }

        $field_id = isset($field['id']) && is_scalar($field['id']) ? sanitize_text_field((string) $field['id']) : '';

        return $field_id !== '' ? 'field-' . $field_id : 'field';
    }

    /**
     * Normalize field_key values across all fields in the form and ensure uniqueness.
     *
     * @param array $steps Form steps.
     * @return array
     */
    public static function normalize_field_keys(array $steps): array {
        $used_keys = [];

        foreach ($steps as $step_index => $step) {
            if (empty($step['fields']) || !is_array($step['fields'])) {
                continue;
            }

            foreach ($step['fields'] as $field_index => $field) {
                if (!is_array($field)) {
                    continue;
                }

                $field_key_source = self::get_field_key_source($field);
                $steps[$step_index]['fields'][$field_index]['field_key'] = Helpers::unique_field_key($field_key_source, $used_keys);
            }
        }

        return $steps;
    }

    /**
     * Drop conditional logic rules that point at a field the form no longer holds.
     *
     * A dangling reference does not simply stop matching: Conditional_Logic compares
     * the missing field against an empty string, so "is not" style operators start
     * returning true and the field appears where it was meant to stay hidden. Only
     * whole rules go, the same way the save handler drops a rule that carries no
     * field ID at all, which keeps the surviving conditions no broader than before.
     *
     * This runs once the whole form is assembled because a rule may reference a
     * field in any step, including one sanitized after the rule itself.
     *
     * @param array $steps Form steps.
     * @return array
     */
    public static function drop_dangling_logic_rules(array $steps): array {
        $field_ids = [];

        foreach ($steps as $step) {
            if (empty($step['fields']) || !is_array($step['fields'])) {
                continue;
            }

            foreach ($step['fields'] as $field) {
                if (is_array($field) && isset($field['id']) && is_scalar($field['id'])) {
                    $field_ids[(string) $field['id']] = true;
                }
            }
        }

        foreach ($steps as $step_index => $step) {
            if (empty($step['fields']) || !is_array($step['fields'])) {
                continue;
            }

            foreach ($step['fields'] as $field_index => $field) {
                if (!is_array($field) || empty($field['rules']) || !is_array($field['rules'])) {
                    continue;
                }

                $groups = [];

                foreach ($field['rules'] as $group) {
                    if (!is_array($group)) {
                        continue;
                    }

                    $rules = array_filter($group, function ($rule) use ($field_ids) {
                        if (!is_array($rule) || ($rule['type'] ?? '') !== 'field') {
                            return true;
                        }

                        $settings = isset($rule['settings']) && is_array($rule['settings']) ? $rule['settings'] : [];
                        $field_id = isset($settings['field_id']) && is_scalar($settings['field_id']) ? (string) $settings['field_id'] : '';

                        return isset($field_ids[$field_id]);
                    });

                    if ($rules) {
                        $groups[] = array_values($rules);
                    }
                }

                $steps[$step_index]['fields'][$field_index]['rules'] = $groups;
            }
        }

        return $steps;
    }

    /**
     * Decode stored form JSON.
     *
     * Forms saved by earlier versions hold "\'" in their JSON, which is not a
     * valid JSON escape, wherever a Content field contained an apostrophe, and
     * such a form failed to decode as a whole. When decoding fails, only
     * those sequences are read as the escaped backslash they were meant to
     * be. Valid JSON is never altered and nothing is written back: the stored
     * form is corrected the next time it is saved.
     *
     * @param string $json Stored form JSON.
     * @return array|null Decoded form data, or null when it cannot be read.
     */
    public static function decode_form_json(string $json): ?array {
        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (strpos($json, "\\'") === false) {
            return null;
        }

        // Escape pairs are consumed whole, so "\\" followed by "'" is untouched.
        $repaired = preg_replace_callback('/\\\\(.)/s', static function (array $matches): string {
            return $matches[1] === "'" ? "\\\\'" : $matches[0];
        }, $json);

        $decoded = is_string($repaired) ? json_decode($repaired, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Sanitize the HTML of a content field into its stored representation.
     *
     * Stored content keeps line breaks as markers and escapes quotes; existing
     * forms are read in that representation, so every writer produces it and
     * every reader turns it back with decode_content(). The two are exact
     * inverses, so saving a form again never changes its content.
     *
     * @param string $html Content HTML.
     * @return string
     */
    public static function encode_content(string $html): string {
        $html = Helpers::encode_line_breaks(wp_kses_post($html));

        return str_replace(['"', "'"], ['\"', "\\'"], $html);
    }

    /**
     * Turn stored content back into plain HTML, without sanitizing it.
     *
     * @param string $content Content as stored by encode_content().
     * @return string
     */
    public static function decode_content(string $content): string {
        return Helpers::decode_line_breaks(str_replace(['\"', "\\'"], ['"', "'"], $content));
    }
}
