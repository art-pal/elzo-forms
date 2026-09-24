<?php
/**
 * Submission exporter.
 *
 * Submissions are read in ID order, one batch at a time, and written straight
 * to the output stream, so memory use does not grow with the number of
 * submissions. Only the CSV header needs a first pass, which reads field
 * identities and keeps nothing else.
 *
 * @package ElzoForms\ImportExport
 */

namespace ElzoForms\ImportExport;

// Exit if accessed directly
defined('ABSPATH') || exit;

final class Submission_Exporter {

    public const STATUS_NORMAL = 'normal';
    public const STATUS_SPAM = 'spam';
    public const STATUS_ALL = 'all';

    /** Submission statuses that count as normal, that is neither spam nor trash. */
    private const NORMAL_STATUSES = ['publish', 'private', 'pending', 'draft', 'future'];

    /** Submissions read per query. */
    private const BATCH_SIZE = 200;

    /** Keys of a stored submission field that are exported. */
    private const FIELD_KEYS = ['id', 'field_key', 'type', 'admin_label', 'label', 'value', 'primary_field'];

    /** Query variable holding the keyset cursor. */
    private const AFTER_ID_VAR = 'elzo_after_id';

    /** JSON flags for exported items. */
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

    /** @var array */
    private $filters;

    /** @var array<int, string> Package reference by form ID */
    private $form_refs = [];

    /** @var array<string, array{ref: string, key: string, title: string}> */
    private $references = [];

    /** @var array<string, \WP_Post> Referenced form posts by reference */
    private $form_posts = [];

    /** @var array<string, bool> */
    private $used_refs = [];

    /** @var array<int, string> Form titles by form ID */
    private $form_titles = [];

    /**
     * @param array $filters Export filters, see normalize_filters().
     */
    public function __construct(array $filters = []) {
        $this->filters = self::normalize_filters($filters);
    }

    /**
     * Normalize export filters.
     *
     * @param array $filters {
     *     @type int[]  $form_ids         Form IDs, or an empty list for every form.
     *     @type string $status           "normal", "spam" or "all".
     *     @type string $date_from        First day to include, Y-m-d in the site timezone.
     *     @type string $date_to          Last day to include, Y-m-d in the site timezone.
     *     @type int[]  $ids              Selected submission IDs.
     *     @type bool   $include_metadata Whether to add IP, user agent and user ID.
     *     @type bool   $include_headers  Whether a CSV export starts with a header row. Default true.
     *     @type bool   $include_forms    Whether a JSON export carries the related forms.
     * }
     * @return array
     */
    public static function normalize_filters(array $filters): array {
        $status = isset($filters['status']) && is_string($filters['status']) ? $filters['status'] : self::STATUS_NORMAL;

        return [
            'form_ids' => array_values(array_unique(array_filter(array_map('absint', array_filter((array) ($filters['form_ids'] ?? []), 'is_scalar'))))),
            'status' => in_array($status, [self::STATUS_NORMAL, self::STATUS_SPAM, self::STATUS_ALL], true) ? $status : self::STATUS_NORMAL,
            'date_from' => self::normalize_date($filters['date_from'] ?? ''),
            'date_to' => self::normalize_date($filters['date_to'] ?? ''),
            'ids' => array_values(array_unique(array_filter(array_map('absint', array_filter((array) ($filters['ids'] ?? []), 'is_scalar'))))),
            'include_metadata' => !empty($filters['include_metadata']),
            'include_headers' => !array_key_exists('include_headers', $filters) || !empty($filters['include_headers']),
            'include_forms' => !empty($filters['include_forms']),
        ];
    }

    /**
     * Keep a valid Y-m-d date.
     *
     * @param mixed $date Raw date.
     * @return string
     */
    private static function normalize_date($date): string {
        if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            return '';
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]) ? $date : '';
    }

    /**
     * Get the normalized filters.
     *
     * @return array
     */
    public function get_filters(): array {
        return $this->filters;
    }

    /**
     * Build the query of one batch.
     *
     * @param int $after_id Last ID of the previous batch.
     * @return array
     */
    public function query_args(int $after_id = 0): array {
        switch ($this->filters['status']) {
            case self::STATUS_SPAM:
                $statuses = ['spam'];
                break;
            case self::STATUS_ALL:
                $statuses = array_merge(self::NORMAL_STATUSES, ['spam']);
                break;
            default:
                $statuses = self::NORMAL_STATUSES;
        }

        $args = [
            'post_type' => 'elzo_submission',
            'post_status' => $statuses,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => true,
        ];

        if ($this->filters['ids']) {
            $args['post__in'] = $this->filters['ids'];
        }

        if ($this->filters['form_ids']) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- The form_id index meta is the stored relation between a submission and its form.
            $args['meta_query'] = [
                [
                    'key' => 'form_id',
                    'value' => array_map('strval', $this->filters['form_ids']),
                    'compare' => 'IN',
                ],
            ];
        }

        if ($this->filters['date_from'] !== '' || $this->filters['date_to'] !== '') {
            $date_query = [
                'column' => 'post_date',
                'inclusive' => true,
            ];
            if ($this->filters['date_from'] !== '') {
                $date_query['after'] = $this->filters['date_from'] . ' 00:00:00';
            }
            if ($this->filters['date_to'] !== '') {
                $date_query['before'] = $this->filters['date_to'] . ' 23:59:59';
            }
            $args['date_query'] = [$date_query];
        }

        /**
         * Filters the query that selects submissions to export.
         *
         * Batching keys (order, page size and cursor) are set after this filter.
         *
         * @filter elzo_forms/import_export/submission_export_query
         * @param array $args    WP_Query arguments.
         * @param array $filters Normalized export filters.
         */
        $filtered = apply_filters('elzo_forms/import_export/submission_export_query', $args, $this->filters);
        if (is_array($filtered)) {
            $args = $filtered;
        }

        $args['post_type'] = 'elzo_submission';
        $args['posts_per_page'] = self::BATCH_SIZE;
        $args['orderby'] = 'ID';
        $args['order'] = 'ASC';
        $args['no_found_rows'] = true;
        $args['ignore_sticky_posts'] = true;
        $args['suppress_filters'] = false;
        $args['fields'] = 'all';
        $args[self::AFTER_ID_VAR] = $after_id;
        unset($args['paged'], $args['offset']);

        return $args;
    }

    /**
     * Restrict a batch query to IDs after the cursor.
     *
     * @param string $where WHERE clause.
     * @param \WP_Query $query Query.
     * @return string
     */
    public static function filter_where_after_id($where, $query) {
        if (!$query instanceof \WP_Query || $query->get('post_type') !== 'elzo_submission') {
            return $where;
        }

        $after_id = absint($query->get(self::AFTER_ID_VAR));
        if ($after_id > 0) {
            global $wpdb;
            $where .= $wpdb->prepare(" AND {$wpdb->posts}.ID > %d", $after_id);
        }

        return $where;
    }

    /**
     * Visit every matching submission in ID order.
     *
     * @param callable $callback Receives each \WP_Post.
     * @return int Number of visited submissions.
     */
    public function each(callable $callback): int {
        $after_id = 0;
        $count = 0;

        add_filter('posts_where', [self::class, 'filter_where_after_id'], 10, 2);

        try {
            do {
                $query = new \WP_Query($this->query_args($after_id));
                $posts = is_array($query->posts) ? $query->posts : [];
                $batch_size = count($posts);
                $previous_after_id = $after_id;
                unset($query);

                foreach ($posts as $post) {
                    // The cursor also guards against a query that ignored it.
                    if (!$post instanceof \WP_Post || (int) $post->ID <= $previous_after_id) {
                        continue;
                    }

                    $after_id = max($after_id, (int) $post->ID);
                    $callback($post);
                    $count++;
                }

                unset($posts);
                self::release_runtime_cache();
            } while ($batch_size === self::BATCH_SIZE && $after_id > $previous_after_id);
        } finally {
            remove_filter('posts_where', [self::class, 'filter_where_after_id'], 10);
        }

        return $count;
    }

    /**
     * Drop the posts and meta a finished batch left in the runtime cache.
     *
     * @return void
     */
    private static function release_runtime_cache(): void {
        if (function_exists('wp_cache_supports') && function_exists('wp_cache_flush_runtime') && wp_cache_supports('flush_runtime')) {
            wp_cache_flush_runtime();
        }
    }

    /**
     * Write a JSON package.
     *
     * Submissions are written first, as they are read; the references and
     * forms they need follow, since JSON readers do not depend on key order.
     *
     * @param resource $handle Writable stream.
     * @return int Number of exported submissions.
     */
    public function write_json($handle): int {
        $header = wp_json_encode(Package::header(false), self::JSON_FLAGS);
        $this->write($handle, substr((string) $header, 0, -1) . ',"submissions":[');

        $first = true;
        $count = $this->each(function (\WP_Post $post) use ($handle, &$first): void {
            $encoded = wp_json_encode($this->submission_item($post), self::JSON_FLAGS);
            if (!is_string($encoded)) {
                return;
            }

            $this->write($handle, ($first ? "\n" : ",\n") . $encoded);
            $first = false;
        });

        $this->write($handle, "\n],\"references\":" . wp_json_encode(['forms' => array_values($this->references)], self::JSON_FLAGS));

        if ($this->filters['include_forms']) {
            $exporter = new Form_Exporter();
            $forms = [];
            foreach ($this->form_posts as $ref => $form_post) {
                $forms[] = $exporter->export_form($form_post, $ref, false);
            }
            $this->write($handle, ',"forms":' . wp_json_encode($forms, self::JSON_FLAGS));
        }

        $this->write($handle, "}\n");

        return $count;
    }

    /**
     * Build the portable item of one submission.
     *
     * @param \WP_Post $post Submission post.
     * @return array
     */
    public function submission_item(\WP_Post $post): array {
        $decoded = json_decode((string) $post->post_content, true);
        $data = is_array($decoded) ? $decoded : [];

        $fields = [];
        foreach ((isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : []) as $field) {
            if (is_array($field)) {
                $fields[] = array_intersect_key($field, array_flip(self::FIELD_KEYS));
            }
        }

        $item_data = [
            'fields' => $fields,
            'submitted_in' => isset($data['submitted_in']) && is_scalar($data['submitted_in']) ? absint($data['submitted_in']) : 0,
        ];

        $page_url = isset($data['page']['url']) && is_scalar($data['page']['url']) ? (string) $data['page']['url'] : '';
        if ($page_url !== '') {
            $item_data['page'] = ['url' => $page_url];
        }

        $item = [
            'uid' => self::uid($post),
            'form_ref' => $this->form_ref($this->form_id($post, $data)),
            'status' => in_array($post->post_status, ['publish', 'private', 'pending', 'draft', 'spam'], true) ? (string) $post->post_status : 'publish',
            'submitted_at' => self::iso_date((string) $post->post_date_gmt),
            'data' => $item_data,
        ];

        if ($this->filters['include_metadata']) {
            $user = isset($data['user']) && is_array($data['user']) ? $data['user'] : [];
            $item['submitter'] = [
                'ip' => isset($user['ip']) && is_scalar($user['ip']) ? (string) $user['ip'] : '',
                'user_agent' => isset($user['user_agent']) && is_scalar($user['user_agent']) ? (string) $user['user_agent'] : '',
                'user_id' => isset($user['user_id']) && is_scalar($user['user_id']) ? absint($user['user_id']) : 0,
            ];
        }

        /**
         * Filters the exported item of a submission.
         *
         * @filter elzo_forms/import_export/export_submission
         * @param array $item    Exported item.
         * @param array $context {
         *     @type \WP_Post $post             Submission post.
         *     @type array    $source           Complete stored submission data.
         *     @type bool     $include_metadata Whether submitter metadata was requested.
         * }
         */
        $filtered = apply_filters('elzo_forms/import_export/export_submission', $item, [
            'post' => $post,
            'source' => $data,
            'include_metadata' => $this->filters['include_metadata'],
        ]);

        return is_array($filtered) ? $filtered : $item;
    }

    /**
     * Write a CSV file.
     *
     * @param resource $handle Writable stream.
     * @return int Number of exported submissions.
     */
    public function write_csv($handle): int {
        $columns = [];
        $this->each(function (\WP_Post $post) use (&$columns): void {
            foreach ($this->csv_fields($post) as $identity => $field) {
                if (!isset($columns[$identity])) {
                    $columns[$identity] = $field;
                }
            }
        });

        $writer = new Csv_Writer($handle);
        $writer->write_bom();
        if ($this->filters['include_headers']) {
            $writer->write_row($this->csv_headers($columns));
        }

        return $this->each(function (\WP_Post $post) use ($writer, $columns): void {
            $decoded = json_decode((string) $post->post_content, true);
            $data = is_array($decoded) ? $decoded : [];
            $fields = $this->csv_fields($post, $data);
            $form_id = $this->form_id($post, $data);

            $row = [
                (int) $post->ID,
                (string) $post->post_date,
                $post->post_status === 'spam' ? __('Spam', 'elzo-forms') : __('Normal', 'elzo-forms'),
                $this->form_title($form_id),
                isset($data['page']['url']) && is_scalar($data['page']['url']) ? (string) $data['page']['url'] : '',
            ];

            foreach (array_keys($columns) as $identity) {
                $row[] = isset($fields[$identity]) ? self::flatten_value($fields[$identity]['value'] ?? '') : '';
            }

            if ($this->filters['include_metadata']) {
                $user = isset($data['user']) && is_array($data['user']) ? $data['user'] : [];
                $row[] = isset($user['ip']) && is_scalar($user['ip']) ? (string) $user['ip'] : '';
                $row[] = isset($user['user_agent']) && is_scalar($user['user_agent']) ? (string) $user['user_agent'] : '';
                $row[] = !empty($user['user_id']) && is_scalar($user['user_id']) ? absint($user['user_id']) : '';
            }

            $writer->write_row($row);
        });
    }

    /**
     * Get the fields of a submission keyed by their column identity.
     *
     * A field is identified by its stable field key, falling back to its field
     * ID and then its position for submissions stored before field keys.
     *
     * @param \WP_Post $post Submission post.
     * @param array|null $data Decoded submission data, when already available.
     * @return array<string, array>
     */
    private function csv_fields(\WP_Post $post, ?array $data = null): array {
        if ($data === null) {
            $decoded = json_decode((string) $post->post_content, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        $fields = [];
        foreach (array_values(isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : []) as $position => $field) {
            if (!is_array($field)) {
                continue;
            }

            $field_key = isset($field['field_key']) && is_scalar($field['field_key']) ? trim((string) $field['field_key']) : '';
            $field_id = isset($field['id']) && is_scalar($field['id']) ? trim((string) $field['id']) : '';

            if ($field_key !== '') {
                $identity = 'key:' . $field_key;
                $field['_suffix'] = $field_key;
            } elseif ($field_id !== '') {
                $identity = 'id:' . $field_id;
                $field['_suffix'] = $field_id;
            } else {
                $identity = 'position:' . ($position + 1);
                $field['_suffix'] = (string) ($position + 1);
            }

            // A repeated identity within one submission gets its own column.
            $base_identity = $identity;
            $repeat = 2;
            while (isset($fields[$identity])) {
                $identity = $base_identity . '#' . $repeat;
                $repeat++;
            }

            $fields[$identity] = $field;
        }

        return $fields;
    }

    /**
     * Build unique, human-readable CSV headers.
     *
     * A header is the field's admin label, label or key. When two columns
     * would share a header, the later one gets its field key or ID appended,
     * then a number, in the order the columns were first seen.
     *
     * @param array<string, array> $columns Field columns by identity, in first-seen order.
     * @return string[]
     */
    private function csv_headers(array $columns): array {
        $headers = [
            __('Submission ID', 'elzo-forms'),
            __('Submitted at', 'elzo-forms'),
            __('Status', 'elzo-forms'),
            __('Form', 'elzo-forms'),
            __('Page URL', 'elzo-forms'),
        ];
        $metadata_headers = $this->filters['include_metadata']
            ? [__('IP address', 'elzo-forms'), __('User agent', 'elzo-forms'), __('User ID', 'elzo-forms')]
            : [];

        $used = [];
        foreach (array_merge($headers, $metadata_headers) as $header) {
            $used[self::header_key($header)] = true;
        }

        foreach ($columns as $field) {
            $label = '';
            foreach (['admin_label', 'label', 'field_key'] as $label_key) {
                if (isset($field[$label_key]) && is_scalar($field[$label_key]) && trim((string) $field[$label_key]) !== '') {
                    $label = wp_strip_all_tags(trim((string) $field[$label_key]));
                    break;
                }
            }
            if ($label === '') {
                /* translators: %s: Field ID or position, shown when the field has no label. */
                $label = sprintf(__('Field %s', 'elzo-forms'), $field['_suffix']);
            }

            $header = $label;
            if (isset($used[self::header_key($header)])) {
                $header = $label . ' (' . $field['_suffix'] . ')';
                $number = 2;
                while (isset($used[self::header_key($header)])) {
                    $header = $label . ' (' . $field['_suffix'] . ') ' . $number;
                    $number++;
                }
            }

            $used[self::header_key($header)] = true;
            $headers[] = $header;
        }

        return array_merge($headers, $metadata_headers);
    }

    /**
     * Case-insensitive comparison key of a header.
     *
     * @param string $header Header.
     * @return string
     */
    private static function header_key(string $header): string {
        return function_exists('mb_strtolower') ? mb_strtolower($header) : strtolower($header);
    }

    /**
     * Turn a stored value into cell text.
     *
     * @param mixed $value Stored value.
     * @return string
     */
    private static function flatten_value($value): string {
        if (is_array($value)) {
            $parts = [];
            array_walk_recursive($value, static function ($item) use (&$parts): void {
                if (is_bool($item)) {
                    $parts[] = $item ? '1' : '0';
                } elseif (is_scalar($item) && (string) $item !== '') {
                    $parts[] = (string) $item;
                }
            });

            return implode(', ', $parts);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Get the form ID of a submission.
     *
     * @param \WP_Post $post Submission post.
     * @param array $data Decoded submission data.
     * @return int
     */
    private function form_id(\WP_Post $post, array $data): int {
        if (isset($data['form_id']) && is_numeric($data['form_id'])) {
            return absint($data['form_id']);
        }

        $meta = get_post_meta((int) $post->ID, 'form_id', true);

        return is_numeric($meta) ? absint($meta) : 0;
    }

    /**
     * Get the package reference of a form, recording it on first use.
     *
     * @param int $form_id Form ID.
     * @return string
     */
    private function form_ref(int $form_id): string {
        if (isset($this->form_refs[$form_id])) {
            return $this->form_refs[$form_id];
        }

        $form_post = $form_id > 0 ? get_post($form_id) : null;
        if ($form_post instanceof \WP_Post && $form_post->post_type === 'elzo_form') {
            $ref = Form_Exporter::reference_for($form_post, $this->used_refs);
            $this->references[$ref] = [
                'ref' => $ref,
                'key' => Form_Exporter::form_key($form_post),
                'title' => (string) $form_post->post_title,
            ];
            if ($form_post->post_status !== 'trash') {
                $this->form_posts[$ref] = $form_post;
            }
        } else {
            // A submission whose form is gone still needs a shared reference,
            // so it can be mapped to a form on import.
            $base = 'unknown-form';
            $ref = $base;
            $suffix = 2;
            while (isset($this->used_refs[$ref])) {
                $ref = $base . '-' . $suffix;
                $suffix++;
            }
            $this->used_refs[$ref] = true;
            $this->references[$ref] = [
                'ref' => $ref,
                'key' => '',
                'title' => '',
            ];
        }

        $this->form_refs[$form_id] = $ref;

        return $ref;
    }

    /**
     * Get the title of a form for the CSV Form column.
     *
     * @param int $form_id Form ID.
     * @return string
     */
    private function form_title(int $form_id): string {
        if (!isset($this->form_titles[$form_id])) {
            $form_post = $form_id > 0 ? get_post($form_id) : null;
            $this->form_titles[$form_id] = $form_post instanceof \WP_Post ? (string) $form_post->post_title : '';
        }

        return $this->form_titles[$form_id];
    }

    /**
     * Get the stable identifier of a submission.
     *
     * A submission restored from an export keeps the identifier it was
     * exported with, so exporting it again and importing that file elsewhere
     * still recognizes it. Otherwise the identifier is derived from the ID
     * with the site's salt, so it reveals nothing about the ID.
     *
     * @param \WP_Post $post Submission post.
     * @return string
     */
    public static function uid(\WP_Post $post): string {
        $stored = get_post_meta((int) $post->ID, Submission_Importer::UID_META, true);
        if (is_string($stored) && preg_match(Submission_Importer::UID_PATTERN, $stored)) {
            return $stored;
        }

        return wp_hash('elzo_forms_submission:' . (int) $post->ID);
    }

    /**
     * Convert a GMT MySQL date to ISO 8601.
     *
     * @param string $date_gmt GMT date.
     * @return string Empty string for an unset date.
     */
    private static function iso_date(string $date_gmt): string {
        if ($date_gmt === '' || strpos($date_gmt, '0000-00-00') === 0) {
            return '';
        }

        $timestamp = strtotime($date_gmt . ' UTC');

        return $timestamp ? gmdate('Y-m-d\TH:i:s\Z', $timestamp) : '';
    }

    /**
     * Write to the output stream.
     *
     * @param resource $handle Writable stream.
     * @param string $text Text.
     * @return void
     */
    private function write($handle, string $text): void {
        fwrite($handle, $text); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes to the download stream opened by the caller.
    }
}
