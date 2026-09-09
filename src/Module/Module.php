<?php
/**
 * Base Module abstract class.
 *
 * @package ElzoForms\Module
 */

namespace ElzoForms\Module;

// Exit if accessed directly
defined('ABSPATH') || exit;

abstract class Module implements ModuleInterface {
    public function get_description(): string { return ''; }
    public function get_version(): string { return '1.0.0'; }
    public function get_dependencies(): array { return []; }
    public function is_compatible(): bool { return true; }
    public function defaults(): array { return []; }
    public function get_settings_schema(): array { return []; }
    public function on_register(Manager $manager): void {}
    public function on_boot(array $global_settings): void {}
    public function on_form_boot(\ElzoForms\Form\Form $form, array $settings): void {}
    public function enqueue_front_assets(): void {}
    public function enqueue_admin_assets(): void {}
    public function handle_submission(\ElzoForms\Submission\Submission $submission, array $settings): void {}
    public function get_template_paths(): array { return []; }

    /**
     * Get configuration warnings for this module.
     * Modules can override this to provide context-aware warnings.
     *
     * @param array $settings Module settings
     * @param bool $is_enabled Whether the module is enabled in this context
     * @param bool $is_global Whether this is global settings context (vs per-form)
     * @return array Array of warning messages, empty if no warnings
     */
    public function get_configuration_warnings(array $settings, bool $is_enabled, bool $is_global = true): array {
        return [];
    }
}
