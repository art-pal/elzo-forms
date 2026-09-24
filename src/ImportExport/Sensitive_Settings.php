<?php
/**
 * Sensitive form settings.
 *
 * Credentials a form carries in its per-form module settings are left out of
 * exports unless an administrator explicitly includes them, and are kept when
 * a Replace import brings a form without them.
 *
 * A module marks its own credential settings with 'sensitive' => true in
 * get_settings_schema(). The elzo_forms/import_export/sensitive_settings filter
 * covers modules that cannot do that. Extensions that store secrets elsewhere
 * in the form data remove them in the elzo_forms/import_export/export_form
 * filter, whose context tells whether sensitive settings were requested.
 *
 * @package ElzoForms\ImportExport
 */

namespace ElzoForms\ImportExport;

// Exit if accessed directly
defined('ABSPATH') || exit;

final class Sensitive_Settings {

    private function __construct() {
    }

    /**
     * Get the sensitive module setting keys, keyed by module ID.
     *
     * @return array<string, string[]>
     */
    public static function module_keys(): array {
        $keys = [];
        $manager = function_exists('elzo_forms_modules_manager') ? elzo_forms_modules_manager() : null;

        if ($manager) {
            foreach ($manager->all() as $module_id => $module) {
                foreach ($module->get_settings_schema() as $setting_key => $definition) {
                    if (is_array($definition) && !empty($definition['sensitive'])) {
                        $keys[(string) $module_id][] = (string) $setting_key;
                    }
                }
            }
        }

        /**
         * Filters the module settings Import / Export treats as sensitive.
         *
         * @filter elzo_forms/import_export/sensitive_settings
         * @param array<string, string[]> $keys Setting keys, keyed by module ID.
         */
        $keys = apply_filters('elzo_forms/import_export/sensitive_settings', $keys);

        $normalized = [];
        foreach (is_array($keys) ? $keys : [] as $module_id => $module_keys) {
            $module_id = sanitize_key((string) $module_id);
            if ($module_id === '' || !is_array($module_keys)) {
                continue;
            }

            foreach ($module_keys as $setting_key) {
                $setting_key = is_scalar($setting_key) ? sanitize_key((string) $setting_key) : '';
                if ($setting_key !== '') {
                    $normalized[$module_id][$setting_key] = $setting_key;
                }
            }
        }

        return array_map('array_values', $normalized);
    }

    /**
     * Remove sensitive module settings from form data.
     *
     * @param array $data Form data.
     * @return array{0: array, 1: string[]} Data without the settings, and the paths of removed non-empty values.
     */
    public static function strip(array $data): array {
        $removed = [];

        if (empty($data['modules']) || !is_array($data['modules'])) {
            return [$data, $removed];
        }

        foreach (self::module_keys() as $module_id => $setting_keys) {
            if (!isset($data['modules'][$module_id]['settings']) || !is_array($data['modules'][$module_id]['settings'])) {
                continue;
            }

            foreach ($setting_keys as $setting_key) {
                if (!array_key_exists($setting_key, $data['modules'][$module_id]['settings'])) {
                    continue;
                }

                $value = $data['modules'][$module_id]['settings'][$setting_key];
                unset($data['modules'][$module_id]['settings'][$setting_key]);

                if ($value !== '' && $value !== null && $value !== []) {
                    $removed[] = 'modules.' . $module_id . '.settings.' . $setting_key;
                }
            }
        }

        return [$data, $removed];
    }

    /**
     * Carry sensitive module settings over from the form being replaced.
     *
     * Only settings the incoming data does not carry at all are restored, and
     * only for modules the incoming data still configures, so an explicitly
     * exported empty value always wins.
     *
     * @param array $data Incoming, sanitized form data.
     * @param array $existing Stored data of the form being replaced.
     * @return array
     */
    public static function restore(array $data, array $existing): array {
        foreach (self::module_keys() as $module_id => $setting_keys) {
            $existing_settings = $existing['modules'][$module_id]['settings'] ?? null;
            if (!is_array($existing_settings) || !isset($data['modules'][$module_id]) || !is_array($data['modules'][$module_id])) {
                continue;
            }

            if (!isset($data['modules'][$module_id]['settings']) || !is_array($data['modules'][$module_id]['settings'])) {
                $data['modules'][$module_id]['settings'] = [];
            }

            foreach ($setting_keys as $setting_key) {
                if (array_key_exists($setting_key, $existing_settings) && !array_key_exists($setting_key, $data['modules'][$module_id]['settings'])) {
                    $data['modules'][$module_id]['settings'][$setting_key] = $existing_settings[$setting_key];
                }
            }
        }

        return $data;
    }
}
