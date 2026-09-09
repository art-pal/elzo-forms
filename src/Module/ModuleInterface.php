<?php
/**
 * Module Interface.
 *
 * @package ElzoForms\Module
 */

namespace ElzoForms\Module;

// Exit if accessed directly
defined('ABSPATH') || exit;

interface ModuleInterface {
    public function get_id(): string;
    public function get_name(): string;
    public function get_description(): string;
    public function get_version(): string;

    public function get_dependencies(): array;
    public function is_compatible(): bool;

    public function defaults(): array;
    public function get_settings_schema(): array;

    public function on_register(Manager $manager): void;
    public function on_boot(array $global_settings): void;
    public function on_form_boot(\ElzoForms\Form\Form $form, array $settings): void;

    public function enqueue_front_assets(): void;
    public function enqueue_admin_assets(): void;

    public function handle_submission(\ElzoForms\Submission\Submission $submission, array $settings): void;

    public function get_template_paths(): array;
}
