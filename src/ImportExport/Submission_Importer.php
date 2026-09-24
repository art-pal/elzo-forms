<?php
/**
 * Submission importer.
 *
 * Imported submissions are historical records. They are stored through the
 * Submission model so the post content, title and index meta stay in sync,
 * but none of the frontend submission pipeline runs: no validation against
 * the current form, no spam detection, no notifications, no module handlers
 * and no after-submission actions.
 *
 * Their file values stay the URLs they were stored with. Files are neither
 * downloaded nor owned, so deleting an imported submission never deletes the
 * files those URLs point at.
 *
 * Every submission is written with the identifier it was exported with, so
 * importing a file again, for instance after an interrupted import, skips
 * what is already there.
 *
 * @package ElzoForms\ImportExport
 */

namespace ElzoForms\ImportExport;

use ElzoForms\Submission\Submission;
use ElzoForms\Upload\Submission_File_Cleanup;

// Exit if accessed directly
defined('ABSPATH') || exit;

final class Submission_Importer {

    /** Post meta holding the identifier a submission was imported with. */
    public const UID_META = '_elzo_forms_import_uid';

    /** Accepted submission identifiers. */
    public const UID_PATTERN = '/^[A-Za-z0-9_-]{8,64}$/';

    /** Map a reference to the form imported, or matched, from the same file. */
    public const DESTINATION_PACKAGE = 'package';

    /** Leave the submissions of a reference out. */
    public const DESTINATION_SKIP = 'skip';

    /** Upper bound of fields accepted from one imported submission. */
    private const MAX_FIELDS = 500;

    /** Upper bound of items accepted in one field value. */
    private const MAX_VALUE_ITEMS = 1000;

    /** Deepest nesting accepted in one field value. */
    private const MAX_VALUE_DEPTH = 4;

    /** Identifiers checked per duplicate lookup. */
    private const UID_LOOKUP_BATCH = 500;

    /** Submissions saved between runtime cache releases. */
    private const CACHE_RELEASE_INTERVAL = 200;

    /** @var Form_Importer */
    private $form_importer;

    public function __construct(?Form_Importer $form_importer = null) {
        $this->form_importer = $form_importer ?: new Form_Importer();
    }

    /**
     * Describe where the submissions of a package would go.
     *
     * @param Package $package Parsed package.
     * @return array{mappings: array<int, array>, total: int, spam: int, duplicates: int, invalid: int}
     */
    public function preview(Package $package): array {
        $summary = [
            'mappings' => [],
            'total' => 0,
            'spam' => 0,
            'duplicates' => 0,
            'invalid' => 0,
        ];

        $existing_uids = $this->existing_uids($package);
        $seen_uids = [];

        foreach ($package->submissions() as $item) {
            $summary['total']++;

            if (!self::has_fields($item)) {
                $summary['invalid']++;
                continue;
            }

            $uid = self::sanitize_uid($item['uid'] ?? '');
            if ($uid !== '' && (isset($existing_uids[$uid]) || isset($seen_uids[$uid]))) {
                $summary['duplicates']++;
                continue;
            }
            if ($uid !== '') {
                $seen_uids[$uid] = true;
            }

            if (($item['status'] ?? '') === 'spam') {
                $summary['spam']++;
            }
        }

        foreach ($this->references($package) as $index => $reference) {
            $match = $this->form_importer->find_existing($reference['key']);
            $summary['mappings'][$index] = [
                'index' => $index,
                'ref' => $reference['ref'],
                'key' => $reference['key'],
                'title' => $reference['title'],
                'count' => $reference['count'],
                'has_form' => $reference['has_form'],
                'match' => $match ? [
                    'id' => (int) $match->ID,
                    'title' => (string) $match->post_title,
                    'edit_url' => (string) get_edit_post_link($match->ID, 'raw'),
                ] : null,
                'default' => $reference['has_form']
                    ? self::DESTINATION_PACKAGE
                    : ($match ? (string) $match->ID : self::DESTINATION_SKIP),
            ];
        }

        return $summary;
    }

    /**
     * Import the submissions of a package.
     *
     * @param Package $package Parsed package.
     * @param array<int, string> $mappings Destination by reference index: "package", "skip" or a form ID.
     * @param array<string, int> $form_map Form IDs of the forms handled from the same file, by reference.
     * @return array{imported: int, spam: int, duplicates: int, skipped: int, failed: int}
     */
    public function import(Package $package, array $mappings, array $form_map = []): array {
        $result = [
            'imported' => 0,
            'spam' => 0,
            'duplicates' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $destinations = $this->destinations($package, $mappings, $form_map);
        $existing_uids = $this->existing_uids($package);
        $seen_uids = [];
        $saved_since_release = 0;

        foreach ($package->submissions() as $item) {
            $form_id = $destinations[Package::submission_form_ref($item)] ?? 0;
            if ($form_id <= 0) {
                $result['skipped']++;
                continue;
            }

            $uid = self::sanitize_uid($item['uid'] ?? '');
            if ($uid !== '' && (isset($existing_uids[$uid]) || isset($seen_uids[$uid]))) {
                $result['duplicates']++;
                continue;
            }

            $prepared = $this->prepare($item);
            if ($prepared === null) {
                $result['failed']++;
                continue;
            }

            try {
                /**
                 * Filters sanitized submission data before an import stores it.
                 *
                 * Return anything other than an array to leave the submission out.
                 * $context['item'] is the untrusted item from the file.
                 *
                 * @filter elzo_forms/import_export/import_submission
                 * @param array $data    Sanitized submission data: fields, user, submitted_in and page.
                 * @param array $context {
                 *     @type array  $item     Untrusted submission item.
                 *     @type int    $form_id  Destination form ID.
                 *     @type string $status   "publish" or "spam".
                 *     @type string $date_gmt Original GMT date, Y-m-d H:i:s, or an empty string.
                 * }
                 */
                $data = apply_filters('elzo_forms/import_export/import_submission', $prepared['data'], [
                    'item' => $item,
                    'form_id' => $form_id,
                    'status' => $prepared['status'],
                    'date_gmt' => $prepared['date_gmt'],
                ]);
            } catch (\Throwable $throwable) {
                unset($throwable);
                $result['failed']++;
                continue;
            }

            if (!is_array($data)) {
                $result['skipped']++;
                continue;
            }

            if ($this->save($data, $form_id, $prepared['status'], $prepared['date_gmt'], $uid) <= 0) {
                $result['failed']++;
                continue;
            }

            $result['imported']++;
            if ($prepared['status'] === 'spam') {
                $result['spam']++;
            }
            if ($uid !== '') {
                $seen_uids[$uid] = true;
            }

            $saved_since_release++;
            if ($saved_since_release >= self::CACHE_RELEASE_INTERVAL) {
                $saved_since_release = 0;
                if (function_exists('wp_cache_supports') && function_exists('wp_cache_flush_runtime') && wp_cache_supports('flush_runtime')) {
                    wp_cache_flush_runtime();
                }
            }
        }

        /** This action is documented in src/ImportExport/Form_Importer.php */
        do_action('elzo_forms/import_export/import_completed', 'submissions', $result, $package);

        return $result;
    }

    /**
     * Get the form references the submissions of a package use, with counts.
     *
     * The position in this list identifies a reference in the admin form, so
     * references read from the file never become request keys.
     *
     * @param Package $package Parsed package.
     * @return array<int, array{ref: string, key: string, title: string, count: int, has_form: bool}>
     */
    public function references(Package $package): array {
        $counts = [];
        foreach ($package->submissions() as $item) {
            $ref = Package::submission_form_ref($item);
            $counts[$ref] = ($counts[$ref] ?? 0) + 1;
        }

        $references = [];
        foreach ($package->form_references() as $ref => $reference) {
            $ref = (string) $ref;
            if (empty($counts[$ref])) {
                continue;
            }

            $references[] = [
                'ref' => $ref,
                'key' => (string) $reference['key'],
                'title' => (string) $reference['title'],
                'count' => $counts[$ref],
                'has_form' => !empty($reference['has_form']),
            ];
            unset($counts[$ref]);
        }

        // Submissions without any reference form one group of their own.
        if (!empty($counts[''])) {
            $references[] = [
                'ref' => '',
                'key' => '',
                'title' => '',
                'count' => $counts[''],
                'has_form' => false,
            ];
        }

        return $references;
    }

    /**
     * Resolve the destination form of every reference.
     *
     * Forms handled from the same file come first, then a form with the same
     * key, then the form the administrator picked. A picked form is accepted
     * only when it is an existing form.
     *
     * @param Package $package Parsed package.
     * @param array<int, string> $mappings Requested destinations by reference index.
     * @param array<string, int> $form_map Form IDs by reference.
     * @return array<string, int>
     */
    private function destinations(Package $package, array $mappings, array $form_map): array {
        $destinations = [];

        foreach ($this->references($package) as $index => $reference) {
            $match = $this->form_importer->find_existing($reference['key']);
            $choice = isset($mappings[$index]) && is_string($mappings[$index])
                ? $mappings[$index]
                : ($reference['has_form'] ? self::DESTINATION_PACKAGE : ($match ? (string) $match->ID : self::DESTINATION_SKIP));

            $form_id = 0;
            if ($choice === self::DESTINATION_PACKAGE) {
                $form_id = (int) ($form_map[$reference['ref']] ?? ($match ? $match->ID : 0));
            } elseif ($choice !== self::DESTINATION_SKIP && ctype_digit($choice)) {
                $candidate = get_post((int) $choice);
                if ($candidate instanceof \WP_Post && $candidate->post_type === 'elzo_form' && $candidate->post_status !== 'trash') {
                    $form_id = (int) $candidate->ID;
                }
            }

            $destinations[$reference['ref']] = $form_id;
        }

        return $destinations;
    }

    /**
     * Validate and sanitize one submission item.
     *
     * The structure is checked and every value is sanitized, but values are
     * not validated against the current form: the form may have changed
     * since the submission was made, and the record must stay as it was.
     * WordPress user IDs from another site are never mapped.
     *
     * @param array $item Untrusted submission item.
     * @return array{data: array, status: string, date_gmt: string}|null
     */
    private function prepare(array $item): ?array {
        if (!self::has_fields($item)) {
            return null;
        }

        $data = $item['data'];
        $fields = [];

        foreach (array_values($data['fields']) as $field) {
            if (!is_array($field) || count($fields) >= self::MAX_FIELDS) {
                continue;
            }

            $snapshot = [
                'id' => self::text($field['id'] ?? ''),
                'field_key' => self::text($field['field_key'] ?? ''),
                'admin_label' => self::text($field['admin_label'] ?? ''),
                'label' => self::text($field['label'] ?? ''),
                'value' => self::sanitize_value($field['value'] ?? '', 0),
                'primary_field' => !empty($field['primary_field']),
            ];
            $type = \ElzoForms\Field\Field_Type::sanitize_identifier($field['type'] ?? null);
            if ($type !== '') {
                $snapshot['type'] = $type;
            }
            $fields[] = $snapshot;
        }

        if (!$fields) {
            return null;
        }

        $submitter = isset($item['submitter']) && is_array($item['submitter']) ? $item['submitter'] : [];
        $ip = isset($submitter['ip']) && is_scalar($submitter['ip']) ? (string) filter_var(trim((string) $submitter['ip']), FILTER_VALIDATE_IP) : '';

        $page_url = isset($data['page']['url']) && is_scalar($data['page']['url']) ? esc_url_raw((string) $data['page']['url']) : '';

        return [
            'data' => [
                'fields' => $fields,
                'user' => [
                    'user_id' => 0,
                    'user_agent' => isset($submitter['user_agent']) && is_scalar($submitter['user_agent']) ? sanitize_text_field((string) $submitter['user_agent']) : '',
                    'ip' => $ip,
                ],
                'submitted_in' => isset($data['submitted_in']) && is_scalar($data['submitted_in']) ? absint($data['submitted_in']) : 0,
                'page' => $page_url !== '' ? ['id' => 0, 'url' => $page_url] : [],
            ],
            'status' => ($item['status'] ?? '') === 'spam' ? 'spam' : 'publish',
            'date_gmt' => self::date_gmt($item['submitted_at'] ?? ''),
        ];
    }

    /**
     * Store one submission together with its import meta.
     *
     * The post and its meta are written in one database transaction, so an
     * interrupted import never leaves a submission without the identifier
     * that makes a retry skip it, or without the marker that keeps the files
     * it links to safe. On a database engine without transactions the writes
     * simply happen one after another.
     *
     * @param array $data Sanitized submission data.
     * @param int $form_id Destination form ID.
     * @param string $status "publish" or "spam".
     * @param string $date_gmt Original GMT date, or an empty string.
     * @param string $uid Submission identifier, or an empty string.
     * @return int Submission ID, or 0 on failure.
     */
    private function save(array $data, int $form_id, string $status, string $date_gmt, string $uid): int {
        $page = isset($data['page']) && is_array($data['page']) ? $data['page'] : [];
        unset($data['page']);

        $submission = new Submission($data);
        $submission->set_form_id($form_id);

        if (!empty($page['url'])) {
            $submission->set_page_data($page);
        }
        if ($status === 'spam') {
            $submission->mark_as_spam();
        }
        if ($date_gmt !== '') {
            $submission->set_post_date_gmt($date_gmt);
        }

        $transaction = self::begin_transaction();
        $submission_id = 0;

        try {
            $saved = $submission->save();
            $submission_id = is_wp_error($saved) ? 0 : (int) $saved;
            $stored = $submission_id > 0
                && update_post_meta($submission_id, Submission_File_Cleanup::FILES_NOT_OWNED_META, '1') !== false
                && ($uid === '' || update_post_meta($submission_id, self::UID_META, $uid) !== false);
        } catch (\Throwable $throwable) {
            unset($throwable);
            $stored = false;
        }

        self::end_transaction($transaction, $stored);

        if ($stored) {
            return $submission_id;
        }

        // A rolled-back post may still sit in the object cache.
        $submission_id = $submission_id ?: $submission->get_id();
        if ($submission_id > 0 && function_exists('clean_post_cache')) {
            clean_post_cache($submission_id);
        }

        return 0;
    }

    /**
     * Start a database transaction.
     *
     * @return bool Whether a transaction was started.
     */
    private static function begin_transaction(): bool {
        global $wpdb;

        if (!$wpdb instanceof \wpdb) {
            return false;
        }

        return $wpdb->query('START TRANSACTION') !== false; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control around one imported submission.
    }

    /**
     * Commit or roll back a transaction started by begin_transaction().
     *
     * @param bool $started Whether a transaction was started.
     * @param bool $commit Whether to commit.
     * @return void
     */
    private static function end_transaction(bool $started, bool $commit): void {
        global $wpdb;

        if (!$started || !$wpdb instanceof \wpdb) {
            return;
        }

        $wpdb->query($commit ? 'COMMIT' : 'ROLLBACK'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Transaction control around one imported submission; the statement is a constant.
    }

    /**
     * Find the identifiers of a package that were imported before.
     *
     * @param Package $package Parsed package.
     * @return array<string, bool>
     */
    private function existing_uids(Package $package): array {
        $uids = [];
        foreach ($package->submissions() as $item) {
            $uid = self::sanitize_uid($item['uid'] ?? '');
            if ($uid !== '') {
                $uids[$uid] = true;
            }
        }

        $existing = [];
        foreach (array_chunk(array_keys($uids), self::UID_LOOKUP_BATCH) as $chunk) {
            $post_ids = get_posts([
                'post_type' => 'elzo_submission',
                'post_status' => ['publish', 'private', 'pending', 'draft', 'future', 'spam', 'trash'],
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'update_post_term_cache' => false,
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Duplicate detection runs once per import, on the import identifier meta only.
                'meta_query' => [
                    [
                        'key' => self::UID_META,
                        'value' => $chunk,
                        'compare' => 'IN',
                    ],
                ],
            ]);

            $post_ids = array_values(array_filter(array_map('absint', is_array($post_ids) ? $post_ids : [])));
            if ($post_ids && function_exists('update_meta_cache')) {
                update_meta_cache('post', $post_ids);
            }

            foreach ($post_ids as $post_id) {
                $stored = get_post_meta($post_id, self::UID_META, true);
                if (is_string($stored) && $stored !== '') {
                    $existing[$stored] = true;
                }
            }
        }

        return $existing;
    }

    /**
     * Whether an item carries a field list.
     *
     * @param array $item Submission item.
     * @return bool
     */
    private static function has_fields(array $item): bool {
        return isset($item['data']['fields']) && is_array($item['data']) && is_array($item['data']['fields']) && $item['data']['fields'];
    }

    /**
     * Keep a valid submission identifier.
     *
     * @param mixed $uid Raw identifier.
     * @return string
     */
    private static function sanitize_uid($uid): string {
        return is_string($uid) && preg_match(self::UID_PATTERN, $uid) ? $uid : '';
    }

    /**
     * Sanitize a single-line text property.
     *
     * @param mixed $value Raw value.
     * @return string
     */
    private static function text($value): string {
        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }

    /**
     * Sanitize a stored field value, keeping its structure.
     *
     * @param mixed $value Raw value.
     * @param int $depth Current nesting depth.
     * @return mixed
     */
    private static function sanitize_value($value, int $depth) {
        if (is_array($value)) {
            if ($depth >= self::MAX_VALUE_DEPTH) {
                return [];
            }

            $sanitized = [];
            foreach (array_slice($value, 0, self::MAX_VALUE_ITEMS, true) as $key => $item) {
                $key = is_int($key) ? $key : sanitize_text_field((string) $key);
                $sanitized[$key] = self::sanitize_value($item, $depth + 1);
            }

            return $sanitized;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        return is_string($value) ? self::sanitize_text_value($value) : '';
    }

    /**
     * Sanitize submitted text without changing what the visitor entered.
     *
     * Invalid UTF-8, control characters and markup are removed. Other text,
     * including percent-encoded data and line breaks, is kept as stored:
     * every output of submission values escapes it.
     *
     * @param string $value Raw text.
     * @return string
     */
    private static function sanitize_text_value(string $value): string {
        $value = wp_check_invalid_utf8($value, true);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';

        if (strpos($value, '<') !== false) {
            $value = wp_strip_all_tags(wp_pre_kses_less_than($value));
        }

        return $value;
    }

    /**
     * Convert an ISO 8601 date to a GMT MySQL date.
     *
     * A missing, invalid or future date leaves the date to WordPress, which
     * uses the time of the import; a future date would schedule the post.
     *
     * @param mixed $date Raw date.
     * @return string
     */
    private static function date_gmt($date): string {
        if (!is_string($date) || trim($date) === '') {
            return '';
        }

        $timestamp = strtotime($date);
        if (!$timestamp || $timestamp > time()) {
            return '';
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
