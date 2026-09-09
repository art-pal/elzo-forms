<?php
/**
 * Form JSON Storage class.
 *
 * Loads forms from developer-managed JSON files and supports explicit imports.
 *
 * @package ElzoForms\Form
 * @since 1.0.0
 */

namespace ElzoForms\Form;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Form_JSON_Storage {

    /** @var bool Whether JSON source hooks are enabled */
    protected static bool $enabled = false;

    /** @var array Normalized JSON source path entries */
    protected static array $paths = [];

    /** @var array Array of import paths (where forms will be loaded/imported from) */
    protected static array $import_paths = [];

    /** @var bool Whether an import operation is currently updating posts */
    protected static bool $importing = false;

    /** @var bool Whether WordPress hooks have already been registered */
    protected static bool $hooks_registered = false;

    /**
     * Initialize JSON storage system.
     *
     * Resolves configured paths and registers the related WordPress hooks.
     *
     * @return void
     */
    public static function init(): void {
        self::reset_paths();

        // Allow developers to configure read-only import paths.
        self::apply_path_filters();

        // Enable only when a developer has explicitly configured usable paths.
        self::$enabled = self::check_if_enabled();

        // Register hooks if enabled.
        if (self::$enabled && !self::$hooks_registered) {
            self::register_hooks();
            self::$hooks_registered = true;
        }
    }

    /**
     * Reset configured storage paths.
     *
     * @return void
     */
    protected static function reset_paths(): void {
        self::$paths = [];
        self::$import_paths = [];
    }

    /**
     * Apply the developer filter for read-only import paths.
     *
     * @return void
     */
    protected static function apply_path_filters(): void {
        $paths = apply_filters('elzo_forms/json_storage/paths', self::$paths);
        self::$paths = self::normalize_path_entries(is_array($paths) ? $paths : []);
        self::compile_import_paths();
    }

    /**
     * Normalize a directory path.
     *
     * @param mixed $path Directory path candidate
     * @return string|false Normalized path without a trailing slash, or false when empty
     */
    protected static function normalize_path($path) {
        if (!is_string($path)) {
            return false;
        }

        $path = trim($path);
        if ($path === '') {
            return false;
        }

        return rtrim(wp_normalize_path($path), '/');
    }

    /**
     * Check whether a JSON file belongs directly to a configured import path.
     *
     * Import paths may point to themes, plugins, or other developer-managed
     * locations. This check grants read access only and never write access.
     *
     * @param string $filepath JSON file path
     * @return bool True when the file is in a configured import directory
     */
    protected static function is_configured_import_file(string $filepath): bool {
        if (strtolower((string) pathinfo($filepath, PATHINFO_EXTENSION)) !== 'json') {
            return false;
        }

        $directory = dirname($filepath);
        foreach (self::$import_paths as $import_path) {
            if (self::paths_match($directory, $import_path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read a JSON file from a configured import directory.
     *
     * @param string $filepath JSON file path
     * @return string|false File contents, or false when unavailable
     */
    protected static function read_import_file(string $filepath) {
        if (!self::is_configured_import_file($filepath)) {
            return false;
        }

        if (!class_exists('\WP_Filesystem_Base', false)) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        }

        if (!class_exists('\WP_Filesystem_Direct', false)) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        }

        $filesystem = new \WP_Filesystem_Direct(false);

        return $filesystem->get_contents($filepath);
    }

    /**
     * Normalize and deduplicate import path entries.
     *
     * @param array $paths Import path entries
     * @return array Normalized import path entries
     */
    protected static function normalize_path_entries(array $paths): array {
        $normalized_entries = [];
        $seen = [];

        foreach ($paths as $entry) {
            if (!is_array($entry) || empty($entry['path']) || empty($entry['mode'])) {
                continue;
            }

            $mode = is_string($entry['mode']) ? strtolower(trim($entry['mode'])) : '';
            if ($mode !== 'import') {
                continue;
            }

            $path = self::normalize_path($entry['path']);
            if (!$path || strpos($path, "\0") !== false) {
                continue;
            }

            $key = $path . '|' . $mode;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized_entries[] = [
                'path' => $path,
                'mode' => $mode,
            ];
        }

        return $normalized_entries;
    }

    /**
     * Build the import path list from normalized path entries.
     *
     * @return void
     */
    protected static function compile_import_paths(): void {
        self::$import_paths = [];

        foreach (self::$paths as $entry) {
            self::add_unique_path(self::$import_paths, $entry['path']);
        }
    }

    /**
     * Append a path to a list if it is not already present.
     *
     * @param array $paths Path list passed by reference
     * @param string $path Directory path
     * @return void
     */
    protected static function add_unique_path(array &$paths, string $path): void {
        foreach ($paths as $existing_path) {
            if (self::paths_match($existing_path, $path)) {
                return;
            }
        }

        $paths[] = $path;
    }

    /**
     * Compare two directory paths.
     *
     * @param string $left First directory path
     * @param string $right Second directory path
     * @return bool True when both paths resolve to the same directory
     */
    protected static function paths_match(string $left, string $right): bool {
        $left_real = realpath($left);
        $right_real = realpath($right);

        if ($left_real && $right_real) {
            return rtrim($left_real, '/\\') === rtrim($right_real, '/\\');
        }

        return self::normalize_path($left) === self::normalize_path($right);
    }

    /**
     * Check if JSON storage should be enabled.
     *
     * JSON storage is enabled when at least one configured import directory exists.
     *
     * @return bool True if JSON loading and import are active
     */
    protected static function check_if_enabled(): bool {
        // Check if any import path exists.
        foreach (self::$import_paths as $path) {
            if (is_dir($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Register WordPress hooks.
     *
     * Sets up loading, explicit import actions, and the read-only admin state.
     *
     * @return void
     */
    protected static function register_hooks(): void {
        // Add admin notices for import results and read-only JSON forms.
        add_action('admin_notices', [__CLASS__, 'display_import_notices']);
        add_action('admin_notices', [__CLASS__, 'display_locked_form_notice']);
        add_action('post_submitbox_misc_actions', [__CLASS__, 'display_locked_submitbox_notice']);

        // Handle explicit JSON-to-database import actions.
        add_action('admin_action_elzo_forms_import_json', [__CLASS__, 'handle_manual_import']);
        add_action('admin_action_elzo_forms_import_single', [__CLASS__, 'handle_single_form_import']);

        // Add row actions for individual forms.
        add_filter('post_row_actions', [__CLASS__, 'add_form_row_actions'], 10, 2);

        // Identify forms whose active source is JSON.
        add_filter('display_post_states', [__CLASS__, 'add_form_json_state'], 10, 2);

        // Load form data from JSON (highest priority).
        add_filter('elzo_forms/load_form_data', [__CLASS__, 'load_form_from_json'], 10, 2);

        // JSON sources are developer-managed and therefore read-only in wp-admin.
        add_filter('wp_insert_post_data', [__CLASS__, 'prevent_locked_form_post_update'], 10, 2);
    }

    /**
     * Load form data from JSON file if it exists.
     *
     * WordPress filter callback that loads forms directly from JSON files
     * without first copying their payload into the database.
     *
     * **Priority:** JSON files take precedence over database content.
     *
     * @param array|null $data Current form data from post content
     * @param \WP_Post $post Form post object
     * @return array|null Form data from JSON or original database data
     */
    public static function load_form_from_json($data, \WP_Post $post) {
        if (!self::$enabled) {
            return $data;
        }

        // Get JSON file for this form
        $json_file = self::find_json_file($post->post_name);

        if (!$json_file) {
            return $data;
        }

        // Read and decode JSON
        $json = self::read_import_file($json_file);
        if ($json === false) {
            return $data;
        }

        try {
            $form_data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $data;
        }

        if (!is_array($form_data) || !isset($form_data['data']) || !is_array($form_data['data'])) {
            return $data;
        }

        return $form_data['data'];
    }

    /**
     * Find JSON file for a form by slug.
     *
     * Searches all configured import paths in priority order.
     * Returns path to first matching file found.
     *
     * @param string $slug Form slug (post_name)
     * @return string|false File path if found, false otherwise
     */
    protected static function find_json_file(string $slug) {
        if (empty($slug)) {
            return false;
        }

        $filename = sanitize_file_name($slug) . '.json';

        // Check all import paths
        foreach (self::$import_paths as $path) {
            $filepath = $path . '/' . $filename;
            if (self::is_configured_import_file($filepath) && file_exists($filepath)) {
                return $filepath;
            }
        }

        return false;
    }

    /**
     * Get all JSON files from import paths.
     *
     * Scans all import paths for *.json files and returns deduplicated list.
     * Used by the explicit bulk import action.
     *
     * @return array Array of absolute file paths
     */
    protected static function get_json_files(): array {
        $files = [];

        foreach (self::$import_paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $json_files = glob($path . '/*.json');

            if ($json_files) {
                foreach ($json_files as $json_file) {
                    if (self::is_configured_import_file($json_file)) {
                        $files[] = $json_file;
                    }
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Import a JSON file and create/update form in database.
     *
     * Reads JSON file, validates data, and creates new form or updates existing.
     * Updates database only; does not affect JSON file.
     *
     * **Returns:**
     * - Success: array with 'post_id' and 'action' (created/updated)
     * - Error: WP_Error with details
     *
     * @param string $filepath Absolute path to JSON file
     * @return array|\WP_Error Import result or error
     */
    protected static function import_json_file(string $filepath) {
        if (!self::is_configured_import_file($filepath)) {
            return new \WP_Error('invalid_path', esc_html__('JSON file is outside the configured import directories', 'elzo-forms'));
        }

        // Read file
        $json = self::read_import_file($filepath);

        if ($json === false) {
            return new \WP_Error('read_error', esc_html__('Unable to read file', 'elzo-forms'));
        }

        // Decode JSON
        try {
            $form_data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return new \WP_Error('json_error', esc_html__('Invalid JSON', 'elzo-forms'));
        }

        if (!is_array($form_data)) {
            return new \WP_Error('invalid_data', esc_html__('Missing required fields', 'elzo-forms'));
        }

        $key = isset($form_data['key']) && is_scalar($form_data['key'])
            ? trim((string) $form_data['key'])
            : '';
        $title = isset($form_data['title']) && is_scalar($form_data['title'])
            ? trim((string) $form_data['title'])
            : '';
        if ($key === '' || $title === '') {
            return new \WP_Error('invalid_data', esc_html__('Missing required fields', 'elzo-forms'));
        }

        $status = isset($form_data['status']) && is_scalar($form_data['status'])
            ? (string) $form_data['status']
            : 'publish';
        $existing = get_page_by_path($key, OBJECT, 'elzo_form');

        $import_payload = isset($form_data['data']) && is_array($form_data['data']) ? $form_data['data'] : [];
        $filtered_payload = apply_filters('elzo_forms/json_storage/import_form_payload', $import_payload, $form_data, $filepath);
        if (is_array($filtered_payload)) {
            $import_payload = $filtered_payload;
        }
        unset($import_payload['automations']);
        unset($import_payload['disable_global_automations']);
        unset($import_payload['disabled_global_automation_ids']);

        self::$importing = true;

        try {
            if ($existing) {
                // Update existing form
                $post_id = wp_update_post([
                    'ID' => $existing->ID,
                    'post_title' => $title,
                    'post_status' => $status,
                    'post_content' => wp_json_encode($import_payload, JSON_UNESCAPED_UNICODE),
                ], true);
                $action = 'updated';
            } else {
                // Create new form
                $post_id = wp_insert_post([
                    'post_type' => 'elzo_form',
                    'post_title' => $title,
                    'post_name' => $key,
                    'post_status' => $status,
                    'post_content' => wp_json_encode($import_payload, JSON_UNESCAPED_UNICODE),
                ], true);
                $action = 'created';
            }
        } finally {
            self::$importing = false;
        }

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        return [
            'post_id' => $post_id,
            'action' => $action,
            'compatibility_warnings' => self::compatibility_warning_messages($import_payload, (int) $post_id),
        ];
    }

    protected static function compatibility_warning_messages(array $payload, int $post_id = 0): array {
        if (!class_exists('\ElzoForms\Form\Compatibility\FormCompatibilityInspector')) {
            return [];
        }

        $installed_version = function_exists('elzo_forms_version')
            ? (string) elzo_forms_version()
            : (defined('ELZO_FORMS_VERSION') ? (string) ELZO_FORMS_VERSION : '0.0.0');
        $result = (new Compatibility\FormCompatibilityInspector())->inspect($payload, $installed_version);
        if (!$result->has_warnings()) {
            return [];
        }

        if (function_exists('elzo_forms_notify_compatibility_warnings')) {
            elzo_forms_notify_compatibility_warnings($result, $post_id);
        } elseif (function_exists('do_action')) {
            foreach ($result->get_warnings() as $warning) {
                try {
                    do_action('elzo_forms_form_compatibility_warning', $warning, $result, $post_id);
                } catch (\Throwable $throwable) {
                    unset($throwable);
                }
            }
        }

        $messages = [];
        foreach ($result->get_warnings() as $warning) {
            $message = isset($warning['message']) ? (string) $warning['message'] : '';
            if ($message !== '') {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * Display admin notices for explicit JSON import results.
     */
    public static function display_import_notices(): void {
        $result = get_transient('elzo_forms_json_import_result');

        if (!$result) {
            return;
        }

        // Delete transient
        delete_transient('elzo_forms_json_import_result');

        // Display success messages
        if (!empty($result['messages'])) {
            printf(
                '<div class="notice notice-success is-dismissible"><p><strong>%s:</strong> %s</p></div>',
                esc_html__('Elzo Forms JSON Import', 'elzo-forms'),
                esc_html(implode(', ', $result['messages']))
            );
        }

        // Display errors
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                printf(
                    '<div class="notice notice-error is-dismissible"><p><strong>%s:</strong> %s</p></div>',
                    esc_html__('Elzo Forms JSON Import Error', 'elzo-forms'),
                    esc_html($error)
                );
            }
        }

        if (!empty($result['warnings']) && is_array($result['warnings'])) {
            foreach ($result['warnings'] as $warning) {
                printf(
                    '<div class="notice notice-warning is-dismissible"><p><strong>%s:</strong> %s</p></div>',
                    esc_html__('Elzo Forms JSON Import Warning', 'elzo-forms'),
                    esc_html((string) $warning)
                );
            }
        }
    }

    /**
     * Get the JSON source file for a form.
     *
     * @param int|\WP_Post $post Form post object or ID
     * @return string|false JSON file path if found, false otherwise
     */
    public static function get_form_json_file($post) {
        $post = $post instanceof \WP_Post ? $post : get_post((int) $post);

        if (!$post || $post->post_type !== 'elzo_form') {
            return false;
        }

        $slug = is_scalar($post->post_name) ? (string) $post->post_name : '';

        return self::find_json_file($slug);
    }

    /**
     * Check whether a form is loaded from a read-only JSON source.
     *
     * @param int|\WP_Post $post Form post object or ID
     * @return bool True if the admin editor should be locked
     */
    public static function is_form_edit_locked($post): bool {
        return (bool) self::get_form_json_file($post);
    }

    /**
     * Restore original post fields when a locked JSON-backed form is submitted.
     *
     * @param array $data Sanitized post data
     * @param array $postarr Raw post array
     * @return array Post data
     */
    public static function prevent_locked_form_post_update(array $data, array $postarr): array {
        if (self::$importing) {
            return $data;
        }

        $post_id = isset($postarr['ID']) ? absint($postarr['ID']) : 0;
        if (!$post_id || !self::is_form_edit_locked($post_id)) {
            return $data;
        }

        $post = get_post($post_id);
        if (!$post) {
            return $data;
        }

        foreach (['post_title', 'post_status', 'post_name', 'post_content'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = (string) ($post->{$field} ?? '');
            }
        }

        return $data;
    }

    /**
     * Display a warning when the current form is backed by read-only JSON.
     *
     * @return void
     */
    public static function display_locked_form_notice(): void {
        $post_id = self::get_current_admin_form_post_id();
        if (!$post_id || !self::is_form_edit_locked($post_id)) {
            return;
        }

        $json_file = self::get_form_json_file($post_id);
        $reason = self::get_locked_form_reason();

        printf(
            '<div class="notice notice-warning"><p><strong>%1$s:</strong> %2$s</p></div>',
            esc_html__('Elzo Forms JSON Source', 'elzo-forms'),
            sprintf(
                /* translators: 1: JSON file path. 2: Reason the form cannot be edited. */
                esc_html__('This form is loaded from %1$s and is read-only because %2$s', 'elzo-forms'),
                esc_html((string) $json_file),
                esc_html($reason)
            )
        );
    }

    /**
     * Display read-only status inside the Publish box.
     *
     * @return void
     */
    public static function display_locked_submitbox_notice(): void {
        $post_id = self::get_current_admin_form_post_id();
        if (!$post_id || !self::is_form_edit_locked($post_id)) {
            return;
        }

        $json_file = self::get_form_json_file($post_id);
        $reason = self::get_locked_form_reason();

        printf(
            '<div class="misc-pub-section elzo-forms-json-readonly-box" data-locked-label="%1$s" data-locked-title="%2$s"><strong>%3$s</strong><p>%4$s</p><p class="description">%5$s</p><code>%6$s</code></div>',
            esc_attr__('Read-only', 'elzo-forms'),
            esc_attr__('This JSON-backed form is read-only.', 'elzo-forms'),
            esc_html__('Read-only JSON form', 'elzo-forms'),
            sprintf(
                /* translators: %s: Reason the form cannot be edited. */
                esc_html__('This form cannot be updated in the admin because %s', 'elzo-forms'),
                esc_html($reason)
            ),
            esc_html__('Edit the source JSON file directly, or remove its directory from the configured import paths to use the database copy.', 'elzo-forms'),
            esc_html((string) $json_file)
        );
    }

    /**
     * Get the read-only reason for the active JSON-backed form.
     *
     * @return string Human-readable reason
     */
    protected static function get_locked_form_reason(): string {
        return __('developer-managed JSON sources cannot be edited or overwritten by the plugin.', 'elzo-forms');
    }

    /**
     * Get the current admin form post ID.
     *
     * @return int Form post ID, or 0 outside an elzo_form edit screen
     */
    protected static function get_current_admin_form_post_id(): int {
        global $post;

        if ($post instanceof \WP_Post && $post->post_type === 'elzo_form') {
            return (int) $post->ID;
        }

        $post_id = isset($_GET['post']) && is_scalar($_GET['post']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen detection; the value is only used to look up the current post below.
            ? absint(wp_unslash((string) $_GET['post'])) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen detection; the value is only used to look up the current post below.
            : 0;
        if (!$post_id) {
            return 0;
        }

        $current_post = get_post($post_id);
        if (!$current_post || $current_post->post_type !== 'elzo_form') {
            return 0;
        }

        return $post_id;
    }

    /**
     * Get all configured JSON source path entries.
     *
     * @return array Array of path entries with path and mode keys
     */
    public static function get_paths(): array {
        return self::$paths;
    }

    /**
     * Get all import paths.
     *
     * Returns all paths where forms will be searched for loading/importing.
     * Paths are checked in order; first match wins.
     *
     * @return array Array of absolute directory paths
     */
    public static function get_import_paths(): array {
        return self::$import_paths;
    }

    /**
     * Check if JSON storage is enabled.
     *
     * Returns true if at least one configured import directory exists.
     * This is checked automatically on initialization.
     *
     * @return bool True if JSON storage is active
     */
    public static function is_enabled(): bool {
        return self::$enabled;
    }

    /**
     * Handle the explicit bulk JSON-to-database import action.
     *
     * WordPress admin action callback that imports all configured JSON files.
     * Verifies user permissions and nonce before proceeding.
     * Stores results in transient for display to user.
     *
     * @return void Exits with redirect after processing
     */
    public static function handle_manual_import(): void {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access', 'elzo-forms'));
        }

        // Verify nonce
        $nonce = isset($_GET['_wpnonce']) && is_scalar($_GET['_wpnonce'])
            ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce']))
            : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'elzo_forms_import_json')) {
            wp_die(esc_html__('Invalid nonce', 'elzo-forms'));
        }

        // Get all JSON files
        $json_files = self::get_json_files();

        if (empty($json_files)) {
            set_transient('elzo_forms_json_import_result', [
                'imported' => 0,
                'errors' => [esc_html__('No JSON files found', 'elzo-forms')],
            ], 30);

            wp_safe_redirect(add_query_arg([
                'post_type' => 'elzo_form',
            ], admin_url('edit.php')));
            exit;
        }

        $created_count = 0;
        $updated_count = 0;
        $errors = [];
        $warnings = [];

        foreach ($json_files as $filepath) {
            $result = self::import_json_file($filepath);

            if (is_wp_error($result)) {
                $errors[] = basename($filepath) . ': ' . esc_html($result->get_error_message());
            } else {
                foreach ((array) ($result['compatibility_warnings'] ?? []) as $warning) {
                    $warnings[] = basename($filepath) . ': ' . (string) $warning;
                }
                if ($result['action'] === 'created') {
                    $created_count++;
                } else {
                    $updated_count++;
                }
            }
        }

        // Store results
        $messages = [];
        if ($created_count > 0) {
            $messages[] = sprintf(
                /* translators: %d: Number of forms created from JSON. */
                esc_html(_n('%d form imported', '%d forms imported', $created_count, 'elzo-forms')),
                $created_count
            );
        }
        if ($updated_count > 0) {
            $messages[] = sprintf(
                /* translators: %d: Number of database forms updated from JSON. */
                esc_html(_n('%d database form updated', '%d database forms updated', $updated_count, 'elzo-forms')),
                $updated_count
            );
        }

        set_transient('elzo_forms_json_import_result', [
            'messages' => $messages,
            'errors' => $errors,
            'warnings' => $warnings,
        ], 30);

        // Redirect back
        wp_safe_redirect(add_query_arg([
            'post_type' => 'elzo_form',
        ], admin_url('edit.php')));
        exit;
    }

    /**
     * Add an explicit JSON-to-database import action for JSON-backed forms.
     *
     * @param array $actions Current row action links
     * @param \WP_Post $post Post object
     * @return array Modified row actions
     */
    public static function add_form_row_actions(array $actions, \WP_Post $post): array {
        if ($post->post_type !== 'elzo_form' || !self::$enabled || !current_user_can('manage_options')) {
            return $actions;
        }

        if (self::get_form_json_file($post)) {
            $import_url = wp_nonce_url(
                admin_url('admin.php?action=elzo_forms_import_single&post=' . $post->ID),
                'elzo_forms_import_single_' . $post->ID
            );

            $actions['import_from_json'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url($import_url),
                esc_html__('Import JSON into database', 'elzo-forms')
            );
        }

        return $actions;
    }

    /**
     * Identify forms whose active runtime source is JSON.
     *
     * @param array $states Current post states
     * @param \WP_Post $post Post object
     * @return array Modified post states
     */
    public static function add_form_json_state(array $states, \WP_Post $post): array {
        if ($post->post_type !== 'elzo_form') {
            return $states;
        }

        if (!self::$enabled) {
            return $states;
        }

        if (self::is_form_edit_locked($post)) {
            $states['json_source'] = esc_html__('JSON source (read-only)', 'elzo-forms');
        }

        return $states;
    }

    /**
     * Handle an explicit single-form JSON-to-database import action.
     *
     * WordPress admin action callback that imports a single form from JSON.
     * Verifies user permissions and nonce before proceeding.
     * Updates form in database from its JSON file.
     *
     * @return void Exits with redirect after processing
     */
    public static function handle_single_form_import(): void {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access', 'elzo-forms'));
        }

        // Get post ID
        $post_id = isset($_GET['post']) && is_scalar($_GET['post'])
            ? absint(wp_unslash((string) $_GET['post']))
            : 0;
        if (!$post_id) {
            wp_die(esc_html__('Invalid post ID', 'elzo-forms'));
        }

        // Verify nonce
        $nonce = isset($_GET['_wpnonce']) && is_scalar($_GET['_wpnonce'])
            ? sanitize_text_field(wp_unslash((string) $_GET['_wpnonce']))
            : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'elzo_forms_import_single_' . $post_id)) {
            wp_die(esc_html__('Invalid nonce', 'elzo-forms'));
        }

        // Get post
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'elzo_form') {
            wp_die(esc_html__('Invalid form', 'elzo-forms'));
        }

        // Find JSON file
        $json_file = self::find_json_file($post->post_name);
        if (!$json_file) {
            set_transient('elzo_forms_json_import_result', [
                'messages' => [],
                'errors' => [esc_html__('JSON file not found', 'elzo-forms')],
            ], 30);

            wp_safe_redirect(admin_url('edit.php?post_type=elzo_form'));
            exit;
        }

        // Import the file
        $result = self::import_json_file($json_file);

        if (is_wp_error($result)) {
            set_transient('elzo_forms_json_import_result', [
                'messages' => [],
                'errors' => [esc_html($result->get_error_message())],
            ], 30);
        } else {
            $message = sprintf(
                /* translators: %s: Form title. */
                esc_html__('Form "%s" imported from JSON into the database', 'elzo-forms'),
                esc_html(get_the_title($post_id))
            );
            set_transient('elzo_forms_json_import_result', [
                'messages' => [$message],
                'errors' => [],
                'warnings' => (array) ($result['compatibility_warnings'] ?? []),
            ], 30);
        }

        // Redirect back
        wp_safe_redirect(admin_url('edit.php?post_type=elzo_form'));
        exit;
    }
}
