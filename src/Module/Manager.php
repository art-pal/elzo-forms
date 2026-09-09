<?php
/**
 * Module Manager class.
 *
 * @package ElzoForms\Module
 */

namespace ElzoForms\Module;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Manager {
    /** @var ModuleInterface[] */
    private array $modules = [];

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_global_assets'], 5);
    }

    public function register(ModuleInterface $module): void {
        $id = $module->get_id();
        $this->modules[$id] = $module;
    }

    public function all(): array { return $this->modules; }

    public function get(string $id): ?ModuleInterface { return $this->modules[$id] ?? null; }

    public function get_settings_option_name(string $id): string {
        return 'elzo_forms_module_' . sanitize_key($id) . '_settings';
    }

    private function get_global_enabled(): array {
        $enabled = get_option('elzo_forms_modules_enabled', []);
        return is_array($enabled) ? $enabled : [];
    }

    private function get_global_settings(string $id): array {
        $settings = get_option($this->get_settings_option_name($id), []);
        return is_array($settings) ? $this->sanitize_module_settings($id, $settings) : [];
    }

    private function get_form_enabled_override(int $form_id): array {
        $content = get_post_field('post_content', $form_id);
        $data = $content ? json_decode($content, true) : null;
        $overrides = [];
        if (is_array($data) && isset($data['modules']) && is_array($data['modules'])) {
            foreach ($data['modules'] as $modId => $modData) {
                if (is_array($modData) && isset($modData['status'])) {
                    $status = $modData['status'];
                    $overrides[$modId] = in_array($status, ['inherit', 'enabled', 'disabled'], true) ? $status : 'inherit';
                }
            }
        }
        return $overrides;
    }

    private function get_form_settings(string $id, int $form_id): array {
        $content = get_post_field('post_content', $form_id);
        $data = $content ? json_decode($content, true) : null;
        if (is_array($data) && isset($data['modules'][$id]['settings']) && is_array($data['modules'][$id]['settings'])) {
            return $data['modules'][$id]['settings'];
        }
        return [];
    }

    public function enabled_for_form(int $form_id): array {
        $globals = $this->get_global_enabled();
        $overrides = $this->get_form_enabled_override($form_id);
        $enabled = [];
        foreach ($this->modules as $id => $module) {
            $override = $overrides[$id] ?? 'inherit';
            $is_global = !empty($globals[$id]);
            $is_enabled = $override === 'enabled' ? true : ($override === 'disabled' ? false : $is_global);
            if ($is_enabled && $module->is_compatible()) {
                $enabled[] = $id;
            }
        }
        return apply_filters('elzo_forms/modules/enabled', $enabled, $form_id);
    }

    public function settings_for_form(string $id, int $form_id): array {
        $module = $this->get($id);
        if (!$module) return [];
        $schema = $module->get_settings_schema();
        $defaults = $module->defaults();
        $global = $this->get_global_settings($id);
        $per_form = $this->get_form_settings($id, $form_id);
        $merged = wp_parse_args($per_form, wp_parse_args($global, $defaults));
        $merged = apply_filters('elzo_forms/module/' . $id . '/settings', $merged, $form_id);
        return $this->sanitize_settings($merged, $schema);
    }

    public function sanitize_module_settings(string $id, array $settings): array {
        $module = $this->get($id);

        if (!$module) {
            return [];
        }

        return $this->sanitize_settings($settings, $module->get_settings_schema());
    }

    public function sanitize_settings(array $settings, array $schema): array {
        $sanitized = [];

        foreach ($settings as $raw_key => $val) {
            $key = sanitize_key($raw_key);

            if ($key === '' || !isset($schema[$key])) continue;

            $def = $schema[$key];
            $type = $def['type'] ?? 'text';
            switch ($type) {
                case 'boolean': $sanitized[$key] = is_scalar($val) && !empty($val); break;
                case 'integer': $sanitized[$key] = is_scalar($val) ? absint($val) : 0; break;
                case 'number': $sanitized[$key] = is_scalar($val) ? floatval($val) : 0.0; break;
                case 'select':
                    $options = isset($def['options']) && is_array($def['options']) ? $def['options'] : [];
                    $candidate = is_scalar($val) ? sanitize_text_field((string) $val) : '';
                    $fallback = $def['default'] ?? '';
                    if ($fallback === '' && !empty($options)) {
                        $fallback = reset($options);
                    }
                    $sanitized[$key] = in_array($candidate, $options, true) ? $candidate : $fallback;
                    break;
                case 'text':
                default:
                    $sanitized[$key] = is_scalar($val) ? sanitize_text_field((string) $val) : '';
            }
        }

        return $sanitized;
    }

    public function boot_all(): void {
        foreach ($this->modules as $id => $module) {
            $module->on_register($this);
            $module->on_boot($this->get_global_settings($id));
        }
    }

    /**
     * Enqueue assets for globally-enabled modules.
     * Called during wp_enqueue_scripts hook at priority 5 (early).
     */
    public function enqueue_global_assets(): void {
        $globals = $this->get_global_enabled();
        foreach ($globals as $module_id => $enabled) {
            if (!empty($enabled)) {
                $module = $this->get($module_id);
                if ($module && $module->is_compatible()) {
                    $module->enqueue_front_assets();
                }
            }
        }
    }

    /**
     * Check if a module is globally enabled.
     */
    public function is_globally_enabled(string $module_id): bool {
        $globals = $this->get_global_enabled();
        return !empty($globals[$module_id]);
    }
}
