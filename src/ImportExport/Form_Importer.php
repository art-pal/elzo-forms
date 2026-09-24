<?php
/**
 * Form importer.
 *
 * Forms are matched to existing forms by key only. The numeric IDs of the
 * site that produced a package are never used: they mean nothing here and
 * could point at an unrelated form.
 *
 * @package ElzoForms\ImportExport
 */

namespace ElzoForms\ImportExport;

use ElzoForms\Form\Compatibility\FormCompatibilityInspector;
use ElzoForms\Form\Compatibility\MinimumVersion;
use ElzoForms\Form\Form_JSON_Storage;

// Exit if accessed directly
defined('ABSPATH') || exit;

final class Form_Importer {

    /** Import a form that has no conflict. */
    public const ACTION_CREATE = 'create';

    /** Import a conflicting form as a new form with its own key. */
    public const ACTION_COPY = 'copy';

    /** Overwrite the conflicting form, keeping its ID and status. */
    public const ACTION_REPLACE = 'replace';

    /** Leave the form out. */
    public const ACTION_SKIP = 'skip';

    /** Statuses an imported form may keep. */
    private const STATUSES = ['publish', 'draft', 'pending', 'private'];

    /**
     * Describe what importing each form of a package would do.
     *
     * @param Package $package Parsed package.
     * @param string $conflict_default Default action for a form whose key exists.
     * @return array<int, array> Rows keyed by form index.
     */
    public function preview(Package $package, string $conflict_default = self::ACTION_COPY): array {
        $rows = [];
        $seen_keys = [];

        foreach ($package->forms() as $item) {
            $existing = $this->find_existing($item['key']);
            $can_replace = false;
            $row = [
                'index' => $item['index'],
                'ref' => $item['ref'],
                'key' => $item['key'],
                'title' => $item['title'],
                'status' => $item['status'],
                'existing' => null,
                'actions' => [self::ACTION_CREATE, self::ACTION_SKIP],
                'default' => self::ACTION_CREATE,
                'warnings' => [],
                'replace_warnings' => [],
                'errors' => [],
            ];

            if ($existing) {
                $locked = Form_JSON_Storage::is_form_edit_locked($existing);
                $can_replace = !$locked && current_user_can('edit_post', $existing->ID);

                $row['existing'] = [
                    'id' => (int) $existing->ID,
                    'title' => (string) $existing->post_title,
                    'status' => (string) $existing->post_status,
                    'edit_url' => (string) get_edit_post_link($existing->ID, 'raw'),
                ];
                $row['actions'] = $can_replace
                    ? [self::ACTION_COPY, self::ACTION_REPLACE, self::ACTION_SKIP]
                    : [self::ACTION_COPY, self::ACTION_SKIP];
                $row['default'] = in_array($conflict_default, $row['actions'], true) ? $conflict_default : self::ACTION_COPY;

                if ($locked) {
                    $row['warnings'][] = __('The existing form is loaded from a developer JSON source, so it cannot be replaced.', 'elzo-forms');
                }
            }

            if ($item['key'] !== '' && isset($seen_keys[$item['key']])) {
                $row['warnings'][] = __('This file contains more than one form with this key.', 'elzo-forms');
            }
            $seen_keys[$item['key']] = true;

            if ($item['data'] === null) {
                $row['errors'][] = __('This form has no form data and cannot be imported.', 'elzo-forms');
                $row['actions'] = [self::ACTION_SKIP];
                $row['default'] = self::ACTION_SKIP;
            } else {
                $prepared = $this->prepare($item, $package, self::ACTION_CREATE, null, true);
                $row['warnings'] = array_merge($row['warnings'], $prepared['warnings']);

                if ($can_replace) {
                    $row['replace_warnings'] = $this->prepare($item, $package, self::ACTION_REPLACE, $existing, true)['replace_losses'];
                }
            }

            $rows[$item['index']] = $row;
        }

        return $rows;
    }

    /**
     * Import the forms of a package.
     *
     * Every decision is checked again against the current state of the site,
     * so a stale or crafted request cannot replace a form the preview did not
     * offer to replace.
     *
     * @param Package $package Parsed package.
     * @param array<int, string> $decisions Requested action by form index.
     * @param string $conflict_default Action for a conflicting form without a valid decision.
     * @return array{forms: array, form_map: array<string, int>, created: int, replaced: int, skipped: int, failed: int}
     */
    public function import(Package $package, array $decisions, string $conflict_default = self::ACTION_COPY): array {
        $result = [
            'forms' => [],
            'form_map' => [],
            'created' => 0,
            'replaced' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];
        $actions = [self::ACTION_CREATE, self::ACTION_COPY, self::ACTION_REPLACE, self::ACTION_SKIP];

        foreach ($package->forms() as $item) {
            $existing = $this->find_existing($item['key']);
            $action = $decisions[$item['index']] ?? '';
            if (!in_array($action, $actions, true)) {
                $action = $existing ? $conflict_default : self::ACTION_CREATE;
            }

            $outcome = $this->import_item($item, $package, $action, $existing);
            $result['forms'][] = $outcome;
            $result[$outcome['result']]++;

            // The first form of a reference is the one its submissions follow.
            if ($outcome['post_id'] > 0 && !isset($result['form_map'][$item['ref']])) {
                $result['form_map'][$item['ref']] = $outcome['post_id'];
            }
        }

        /**
         * Fires after an import has written its forms or submissions.
         *
         * @action elzo_forms/import_export/import_completed
         * @param string  $type    "forms" or "submissions".
         * @param array   $result  Import summary.
         * @param Package $package Imported package.
         */
        do_action('elzo_forms/import_export/import_completed', 'forms', $result, $package);

        return $result;
    }

    /**
     * Import one form item.
     *
     * @param array $item Form item.
     * @param Package $package Parsed package.
     * @param string $action Requested action.
     * @param \WP_Post|null $existing Form with the same key, if any.
     * @return array{title: string, key: string, result: string, post_id: int, messages: string[]}
     */
    private function import_item(array $item, Package $package, string $action, ?\WP_Post $existing): array {
        $outcome = [
            'title' => $item['title'] !== '' ? $item['title'] : $item['key'],
            'key' => $item['key'],
            'result' => 'skipped',
            'post_id' => 0,
            'messages' => [],
        ];

        if ($action === self::ACTION_SKIP) {
            // Submissions of a skipped form still belong to the form with its key.
            $outcome['post_id'] = $existing ? (int) $existing->ID : 0;
            return $outcome;
        }

        if ($item['data'] === null) {
            $outcome['result'] = 'failed';
            $outcome['messages'][] = __('This form has no form data and cannot be imported.', 'elzo-forms');
            return $outcome;
        }

        if ($action === self::ACTION_REPLACE && $existing) {
            if (Form_JSON_Storage::is_form_edit_locked($existing) || !current_user_can('edit_post', $existing->ID)) {
                $outcome['result'] = 'failed';
                $outcome['messages'][] = __('The existing form cannot be replaced, so this form was not imported.', 'elzo-forms');
                return $outcome;
            }

            $prepared = $this->prepare($item, $package, self::ACTION_REPLACE, $existing, false);
            $content = $this->encode($prepared['data']);
            if ($content === '') {
                $outcome['result'] = 'failed';
                $outcome['messages'][] = __('The form data could not be encoded.', 'elzo-forms');
                return $outcome;
            }

            // The ID stays, so shortcodes, blocks and submissions keep pointing
            // at this form; so does the status, so a live form stays live.
            $post_id = wp_update_post([
                'ID' => $existing->ID,
                'post_title' => wp_slash($this->title($item)),
                'post_content' => wp_slash($content),
            ], true);
            $outcome['result'] = 'replaced';
        } else {
            // Without a conflict, "copy" and "replace" simply create the form.
            $prepared = $this->prepare($item, $package, self::ACTION_CREATE, null, false);
            $content = $this->encode($prepared['data']);
            if ($content === '') {
                $outcome['result'] = 'failed';
                $outcome['messages'][] = __('The form data could not be encoded.', 'elzo-forms');
                return $outcome;
            }

            $post_id = wp_insert_post([
                'post_type' => 'elzo_form',
                'post_title' => wp_slash($this->title($item)),
                'post_name' => $this->unique_key($item['key'] !== '' ? $item['key'] : sanitize_title($this->title($item))),
                'post_status' => $this->status($item['status']),
                'post_content' => wp_slash($content),
            ], true);
            $outcome['result'] = 'created';
        }

        if (is_wp_error($post_id) || !$post_id) {
            $outcome['result'] = 'failed';
            $outcome['messages'][] = is_wp_error($post_id) ? $post_id->get_error_message() : __('The form could not be saved.', 'elzo-forms');
            return $outcome;
        }

        $outcome['post_id'] = (int) $post_id;
        $outcome['messages'] = array_merge($prepared['warnings'], $prepared['replace_losses']);
        $this->notify_compatibility($prepared['compatibility'], (int) $post_id);

        return $outcome;
    }

    /**
     * Sanitize an item and let extensions add the data they own.
     *
     * Runs for the preview as well, with $dry_run set, so callbacks of the
     * import_form filter must not have side effects.
     *
     * @param array $item Form item.
     * @param Package $package Parsed package.
     * @param string $mode ACTION_CREATE or ACTION_REPLACE.
     * @param \WP_Post|null $target Form being replaced.
     * @param bool $dry_run Whether nothing will be written.
     * @return array{data: array, warnings: string[], replace_losses: string[], compatibility: \ElzoForms\Form\Compatibility\FormCompatibilityResult|null}
     */
    private function prepare(array $item, Package $package, string $mode, ?\WP_Post $target, bool $dry_run): array {
        $raw = $item['data'];
        $sanitizer = new Form_Data_Sanitizer();
        $data = $sanitizer->sanitize($raw);
        $warnings = $sanitizer->get_notices();

        $existing_data = [];
        if ($mode === self::ACTION_REPLACE && $target) {
            $existing_data = \ElzoForms\Form\Form_Data_Normalizer::decode_form_json((string) $target->post_content) ?? [];

            // A file without secrets must not wipe the ones the form already has.
            if (!$package->includes_sensitive()) {
                $data = Sensitive_Settings::restore($data, $existing_data);
            }
        }

        /**
         * Filters sanitized form data before an import stores it.
         *
         * $data holds the sanitized core schema only. Add extension data from
         * $context['raw_data'], which is untrusted and must be sanitized. The
         * filter also runs for the preview ($context['dry_run']), so it must
         * not have side effects; use elzo_forms/import_export/import_completed
         * for those.
         *
         * @filter elzo_forms/import_export/import_form
         * @param array $data    Sanitized form data.
         * @param array $context {
         *     @type array  $item          Form item: ref, key, title, status and redacted.
         *     @type array  $raw_data      Untrusted form data from the file.
         *     @type string $mode          "create" or "replace".
         *     @type int    $target_id     ID of the form being replaced, or 0.
         *     @type array  $existing_data Stored data of the form being replaced.
         *     @type array  $package       plugin_version, schema_version and includes_sensitive of the file.
         *     @type bool   $dry_run       Whether this is the preview.
         * }
         */
        $filtered = apply_filters('elzo_forms/import_export/import_form', $data, [
            'item' => [
                'ref' => $item['ref'],
                'key' => $item['key'],
                'title' => $item['title'],
                'status' => $item['status'],
                'redacted' => $item['redacted'],
            ],
            'raw_data' => $raw,
            'mode' => $mode,
            'target_id' => $target ? (int) $target->ID : 0,
            'existing_data' => $existing_data,
            'package' => [
                'plugin_version' => $package->plugin_version(),
                'schema_version' => $package->schema_version(),
                'includes_sensitive' => $package->includes_sensitive(),
            ],
            'dry_run' => $dry_run,
        ]);
        if (is_array($filtered)) {
            $data = $filtered;
        }

        $unsupported = self::unsupported_sections($raw, $data);
        if ($unsupported) {
            $warnings[] = sprintf(
                /* translators: %s: Comma-separated list of form data sections. */
                __('Not imported because the active edition of Elzo Forms does not support it: %s.', 'elzo-forms'),
                implode(', ', $unsupported)
            );
        }

        // Replacing writes only what is imported from the file, so data the
        // form holds beyond that, for instance from another edition or an
        // inactive addon, would be lost.
        $replace_losses = [];
        $lost = self::unsupported_sections($existing_data, $data);
        if ($lost) {
            $replace_losses[] = sprintf(
                /* translators: %s: Comma-separated list of form data sections. */
                __('Replacing removes data of the existing form that is not imported from this file: %s.', 'elzo-forms'),
                implode(', ', $lost)
            );
        }

        if ($item['redacted']) {
            $warnings[] = sprintf(
                /* translators: %d: Number of sensitive settings. */
                _n(
                    'This file does not include %d sensitive setting, such as an API key or secret. When a form is replaced, the value already stored on this site is kept; otherwise enter it again after importing.',
                    'This file does not include %d sensitive settings, such as API keys or secrets. When a form is replaced, the values already stored on this site are kept; otherwise enter them again after importing.',
                    count($item['redacted']),
                    'elzo-forms'
                ),
                count($item['redacted'])
            );
        }

        // A form states the version it needs; otherwise the version that
        // exported it is the best available hint.
        $compatibility = null;
        $minimum = array_key_exists(MinimumVersion::KEY, $raw) ? $raw[MinimumVersion::KEY] : $package->plugin_version();
        if ($minimum !== '' && $minimum !== null) {
            $compatibility = (new FormCompatibilityInspector())->inspect([MinimumVersion::KEY => $minimum], Package::installed_version());
            foreach ($compatibility->get_warnings() as $warning) {
                if (!empty($warning['message'])) {
                    $warnings[] = (string) $warning['message'];
                }
            }
        }

        return [
            'data' => $data,
            'warnings' => array_values(array_unique($warnings)),
            'replace_losses' => $replace_losses,
            'compatibility' => $compatibility,
        ];
    }

    /**
     * List the non-empty top-level sections of form data that are neither core
     * nor present in the data about to be stored.
     *
     * @param array $source Form data that may hold such sections.
     * @param array $stored Form data about to be stored.
     * @return string[] Readable section names.
     */
    private static function unsupported_sections(array $source, array $stored): array {
        $sections = [];

        foreach ($source as $key => $value) {
            if (is_string($key) && !in_array($key, Form_Data_Sanitizer::CORE_KEYS, true) && !array_key_exists($key, $stored) && !self::is_empty_value($value)) {
                $sections[] = str_replace('_', ' ', sanitize_key($key));
            }
        }

        return $sections;
    }

    /**
     * Find the form an item conflicts with.
     *
     * @param string $key Form key.
     * @return \WP_Post|null
     */
    public function find_existing(string $key): ?\WP_Post {
        if ($key === '') {
            return null;
        }

        $post = get_page_by_path($key, OBJECT, 'elzo_form');

        return $post instanceof \WP_Post && $post->post_type === 'elzo_form' && $post->post_status !== 'trash' ? $post : null;
    }

    /**
     * Get a key no other form uses.
     *
     * WordPress leaves the slug of drafts and pending posts alone, so the
     * key is made unique as if the form were published.
     *
     * @param string $key Requested key.
     * @return string
     */
    private function unique_key(string $key): string {
        if ($key === '') {
            return '';
        }

        return function_exists('wp_unique_post_slug') ? wp_unique_post_slug($key, 0, 'publish', 'elzo_form', 0) : $key;
    }

    /**
     * Get the status of a newly created form.
     *
     * Only users who may publish forms import them as published or private.
     *
     * @param string $status Status from the file.
     * @return string
     */
    private function status(string $status): string {
        if (!in_array($status, self::STATUSES, true)) {
            return 'draft';
        }

        if (in_array($status, ['publish', 'private'], true)) {
            $post_type = get_post_type_object('elzo_form');
            $capability = $post_type && isset($post_type->cap->publish_posts) ? (string) $post_type->cap->publish_posts : 'publish_posts';
            if (!current_user_can($capability)) {
                return 'pending';
            }
        }

        return $status;
    }

    /**
     * Get the title of an imported form.
     *
     * @param array $item Form item.
     * @return string
     */
    private function title(array $item): string {
        if ($item['title'] !== '') {
            return $item['title'];
        }

        return $item['key'] !== '' ? $item['key'] : __('Imported form', 'elzo-forms');
    }

    /**
     * Encode form data for post_content.
     *
     * @param array $data Form data.
     * @return string Empty string when the data cannot be encoded.
     */
    private function encode(array $data): string {
        $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return is_string($json) ? $json : '';
    }

    /**
     * Report compatibility warnings the same way the form editor does.
     *
     * @param \ElzoForms\Form\Compatibility\FormCompatibilityResult|null $result Inspection result.
     * @param int $post_id Imported form ID.
     * @return void
     */
    private function notify_compatibility($result, int $post_id): void {
        if (!$result || !$result->has_warnings() || !function_exists('elzo_forms_notify_compatibility_warnings')) {
            return;
        }

        elzo_forms_notify_compatibility_warnings($result, $post_id);
    }

    /**
     * Whether a value carries no data worth reporting.
     *
     * @param mixed $value Value.
     * @return bool
     */
    private static function is_empty_value($value): bool {
        return $value === null || $value === false || $value === '' || $value === [];
    }
}
