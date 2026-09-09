<?php
/**
 * File Upload Handler class.
 *
 * Handles chunked file uploads for file fields.
 *
 * @package ElzoForms\Upload
 */

namespace ElzoForms\Upload;

// Exit if accessed directly
defined('ABSPATH') || exit;

class File_Upload_Handler {

    private const UPLOAD_SUBDIR = 'elzo-forms/user-uploads/unattached';
    private const USER_UPLOADS_SUBDIR = 'elzo-forms/user-uploads';
    private const UPLOAD_TRANSIENT_PREFIX = 'elzo_forms_upload_';
    private const UPLOAD_ID_QUERY_ARG = 'elzo_upload_id';
    private const UPLOAD_TOKEN_QUERY_ARG = 'elzo_upload_token';
    private const CLEANUP_HOOK = 'elzo_forms_cleanup_unattached_uploads';
    private const SESSION_MARKER_FILE = '.elzo-upload-session.json';
    private const SESSION_LOCK_FILE = '.elzo-upload-sessions.lock';
    private const SESSION_LIFETIME = 21600;
    private const SESSION_IDLE_LIFETIME = 900;
    private const MAX_CHUNKS_TOTAL = 1000;
    private const MAX_ACTIVE_UPLOAD_SESSIONS = 300;
    private const MAX_ACTIVE_UPLOAD_SESSIONS_PER_CLIENT = 20;
    private const MAX_ACTIVE_UPLOAD_SESSIONS_PER_FORM = 150;
    private const MAX_ACTIVE_UPLOAD_BYTES = 1073741824;
    private const DEFAULT_ALLOWED_FILE_TYPES = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'csv',
        'txt',
    ];

    /**
     * Register upload handlers.
     */
    public static function init(): void {
        add_action('wp_ajax_elzo_forms_upload_file', [__CLASS__, 'handle_upload']);
        add_action('wp_ajax_nopriv_elzo_forms_upload_file', [__CLASS__, 'handle_upload']);
        add_action(self::CLEANUP_HOOK, [__CLASS__, 'cleanup_expired_uploads']);
        self::schedule_cleanup();
    }

    /**
     * Handle file upload AJAX request.
     */
    public static function handle_upload(): void {
        // Verify nonce for CSRF protection
        check_ajax_referer('elzo_forms_upload_file', 'nonce');

        // Check if file was uploaded
        if (
            !isset($_FILES['file'])
            || !is_array($_FILES['file'])
            || empty($_FILES['file']['name'])
            || !is_scalar($_FILES['file']['name'])
            || !isset($_FILES['file']['tmp_name'])
            || !is_scalar($_FILES['file']['tmp_name'])
        ) {
            wp_send_json_error([
                'message' => esc_html__('No file uploaded', 'elzo-forms'),
            ]);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified above via check_ajax_referer().
        $upload_error = isset($_FILES['file']['error']) && is_scalar($_FILES['file']['error'])
            ? absint($_FILES['file']['error'])
            : UPLOAD_ERR_OK;
        if ($upload_error !== UPLOAD_ERR_OK) {
            wp_send_json_error([
                'message' => esc_html__('File upload failed', 'elzo-forms'),
            ]);
        }

        /*
         * A filesystem path must not be run through a text sanitizer: sanitizing
         * cannot prove the path is safe and may corrupt a legitimate one. The raw
         * PHP-provided path is validated with is_uploaded_file() instead, which is
         * the only check that proves it belongs to this request's upload.
         * $_FILES is not slashed by wp_magic_quotes(), so no wp_unslash() is needed.
         */
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Filesystem path validated with is_uploaded_file() below instead of a text sanitizer.
        $tmp_name = (string) $_FILES['file']['tmp_name'];

        if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
            wp_send_json_error([
                'message' => esc_html__('No file uploaded', 'elzo-forms'),
            ]);
        }

        // Validate required parameters
        $form_id = isset($_POST['form_id']) && is_scalar($_POST['form_id'])
            ? intval(wp_unslash((string) $_POST['form_id']))
            : 0;
        $field_id = isset($_POST['field_id']) && is_scalar($_POST['field_id'])
            ? self::normalize_field_id(sanitize_text_field(wp_unslash((string) $_POST['field_id'])))
            : '';

        if (empty($form_id) || $field_id === '') {
            wp_send_json_error([
                'message' => esc_html__('Form or field ID not set', 'elzo-forms'),
            ]);
        }

        // Get form and field
        $form = new \ElzoForms\Form\Form($form_id);
        if (!self::is_uploadable_form($form)) {
            wp_send_json_error([
                'message' => esc_html__('Form not found or invalid', 'elzo-forms'),
            ]);
        }

        $field_object = self::find_field_by_id($form, $field_id);
        if (!$field_object) {
            wp_send_json_error([
                'message' => esc_html__('Field not found or invalid', 'elzo-forms'),
            ]);
        }

        // Get upload constraints
        $max_file_size_bytes = self::get_effective_max_file_size_bytes($field_object->get('max_file_size', 0));
        $allowed_types = self::parse_allowed_types($field_object->get('allowed_file_types', ''));

        // Get upload info
        $chunk_index = isset($_POST['chunk_index']) && is_scalar($_POST['chunk_index'])
            ? intval(wp_unslash((string) $_POST['chunk_index']))
            : 0;
        $chunks_total = isset($_POST['chunks_total']) && is_scalar($_POST['chunks_total'])
            ? max(1, intval(wp_unslash((string) $_POST['chunks_total'])))
            : 1;
        $upload_id = isset($_POST['upload_id']) && is_scalar($_POST['upload_id'])
            ? sanitize_key(wp_unslash((string) $_POST['upload_id']))
            : '';
        $original_file_name = isset($_POST['file_name']) && is_scalar($_POST['file_name'])
            ? sanitize_text_field(wp_unslash((string) $_POST['file_name']))
            : '';

        if (empty($upload_id) || !preg_match('/^[a-z0-9][a-z0-9_-]{10,80}$/', $upload_id)) {
            wp_send_json_error([
                'message' => esc_html__('Upload session is invalid', 'elzo-forms'),
            ]);
        }

        if (!self::is_valid_chunk_request($chunk_index, $chunks_total)) {
            wp_send_json_error([
                'message' => esc_html__('Upload chunk is invalid', 'elzo-forms'),
            ]);
        }

        if (empty($original_file_name)) {
            wp_send_json_error([
                'message' => esc_html__('File name not provided', 'elzo-forms'),
            ]);
        }

        if (strpos($original_file_name, '..') !== false || strpos($original_file_name, '/') !== false || strpos($original_file_name, '\\') !== false) {
            wp_send_json_error([
                'message' => esc_html__('Invalid file name', 'elzo-forms'),
            ]);
        }

        // Prepare upload directory
        $upload_dir = wp_upload_dir();
        $custom_upload_dir = self::get_unattached_upload_dir($upload_dir);
        if (!self::ensure_upload_directory($custom_upload_dir, true)) {
            wp_send_json_error([
                'message' => esc_html__('The upload directory could not be secured. Please try again.', 'elzo-forms'),
            ]);
        }

        // Generate safe file name and validate against path traversal
        $file_name = sanitize_file_name($original_file_name);

        // Additional security: check for path traversal attempts
        if (empty($file_name) || strpos($file_name, '..') !== false || strpos($file_name, '/') !== false || strpos($file_name, '\\') !== false) {
            wp_send_json_error([
                'message' => esc_html__('Invalid file name', 'elzo-forms'),
            ]);
        }

        if (self::has_dangerous_extension($file_name)) {
            wp_send_json_error([
                'message' => esc_html__('PHP files are not allowed', 'elzo-forms'),
            ]);
        }

        // Reject disallowed filenames before the first chunk can create a public file.
        if ($chunk_index === 0) {
            $validation_error = self::validate_file_name_type($file_name, $allowed_types);
            if ($validation_error) {
                wp_send_json_error([
                    'message' => $validation_error,
                ]);
            }
        }

        $session = self::prepare_upload_session($upload_id, $chunk_index, $custom_upload_dir, $file_name, $form_id, $field_id, $chunks_total);
        if (($session['error'] ?? '') === 'session_limit_client') {
            wp_send_json_error([
                'message' => esc_html__('You have too many uploads in progress. Please wait for them to finish, or remove some files.', 'elzo-forms'),
            ]);
        }
        if (($session['error'] ?? '') === 'session_limit') {
            wp_send_json_error([
                'message' => esc_html__('Too many uploads are currently in progress. Please try again later.', 'elzo-forms'),
            ]);
        }
        if (empty($session['path']) || empty($session['file_name'])) {
            wp_send_json_error([
                'message' => esc_html__('Upload session is invalid', 'elzo-forms'),
            ]);
        }

        $upload_path = (string) $session['path'];
        $file_name = (string) $session['file_name'];

        $existing_size = $chunk_index > 0 && file_exists($upload_path) ? (int) filesize($upload_path) : 0;
        $chunk_size = (int) filesize($tmp_name);
        if ($chunk_size < 0 || $existing_size + $chunk_size > $max_file_size_bytes) {
            self::delete_upload_session($upload_id);
            wp_send_json_error([
                'message' => self::get_file_size_error_message($existing_size + max(0, $chunk_size), $max_file_size_bytes),
            ]);
        }

        // Handle chunk upload
        try {
            self::process_chunk($upload_path, $chunk_index, $tmp_name);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => esc_html($e->getMessage()),
            ]);
        }

        // Re-check the assembled file in case its size changed between the preflight check and write.
        $current_size = (int) filesize($upload_path);
        if ($current_size > $max_file_size_bytes) {
            self::delete_upload_session($upload_id);
            wp_send_json_error([
                'message' => self::get_file_size_error_message($current_size, $max_file_size_bytes),
            ]);
        }

        $is_final_chunk = $chunk_index === ($chunks_total - 1);
        if ($is_final_chunk) {
            $validation_error = self::validate_file_type($upload_path, $allowed_types);
            if ($validation_error) {
                wp_delete_file($upload_path);
                self::delete_upload_session($upload_id);
                wp_send_json_error([
                    'message' => $validation_error,
                ]);
            }

            $session = self::finalize_upload_session($upload_id, $session);
        }

        $file_url = self::path_to_upload_url($upload_path, $upload_dir);
        if ($is_final_chunk && !empty($session['token'])) {
            $file_url = self::add_upload_token_to_url($file_url, $upload_id, (string) $session['token']);
        }

        // Return success response
        wp_send_json_success([
            'chunk_index' => $chunk_index,
            'chunks_total' => $chunks_total,
            'file_original_name' => $original_file_name,
            'file_name' => $file_name,
            'file_url' => $file_url,
        ]);
    }

    /**
     * Normalize a field ID used to identify the field an upload belongs to.
     *
     * Field IDs live in the form JSON and are not necessarily numeric: forms
     * written outside the admin UI use readable IDs such as "cv_upload". They
     * are only ever compared against the form definition and the upload session
     * (never used to build paths, transient keys or queries), and the accepted
     * character set is the one the form save handler already enforces, so no
     * unexpected value can reach an upload session.
     *
     * "0" and the empty string are rejected: a field can never carry them,
     * because an empty ID is replaced by a generated one when a field is built.
     *
     * @param mixed $field_id Raw field ID.
     * @return string Usable field ID, or an empty string when there is none.
     */
    protected static function normalize_field_id($field_id): string {
        if (!is_scalar($field_id)) {
            return '';
        }

        $field_id = trim((string) $field_id);

        if ($field_id === '' || $field_id === '0') {
            return '';
        }

        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $field_id) === 1 ? $field_id : '';
    }

    /**
     * Find field by ID using Form/Step/Field objects.
     *
     * The comparison is a strict string match: a loose one would let a request
     * for field "5" reach a field whose ID merely starts with a 5, and pick up
     * that field's upload constraints.
     *
     * @param \ElzoForms\Form\Form $form Form object
     * @param string $field_id Field ID
     * @return \ElzoForms\Field\Field_File|null File field object or null
     */
    protected static function find_field_by_id($form, string $field_id): ?\ElzoForms\Field\Field_File {
        $field_id = self::normalize_field_id($field_id);

        if ($field_id === '') {
            return null;
        }

        foreach ($form->steps() as $step) {
            foreach ($step->fields() as $field) {
                if ($field instanceof \ElzoForms\Field\Field_File && (string) $field->get_id() === $field_id) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * Public uploads are accepted only for published forms.
     */
    protected static function is_uploadable_form($form): bool {
        $post = $form->get_post();

        return $post instanceof \WP_Post
            && $post->post_type === 'elzo_form'
            && $post->post_status === 'publish';
    }

    /**
     * Validate chunk bounds before creating or appending an upload session.
     */
    protected static function is_valid_chunk_request(int $chunk_index, int $chunks_total): bool {
        return $chunks_total >= 1
            && $chunks_total <= self::MAX_CHUNKS_TOTAL
            && $chunk_index >= 0
            && $chunk_index < $chunks_total;
    }

    /**
     * Resolve the field setting to a byte limit that can never exceed WordPress.
     * A zero field setting means "use the WordPress limit", not "unlimited".
     *
     * @param mixed $configured_size_mb Field limit in megabytes.
     */
    public static function get_effective_max_file_size_bytes($configured_size_mb): int {
        $wordpress_limit = max(1, (int) wp_max_upload_size());
        $configured_size_mb = max(0, (float) $configured_size_mb);

        if ($configured_size_mb <= 0) {
            return $wordpress_limit;
        }

        $configured_limit = (int) floor($configured_size_mb * 1024 * 1024);
        return max(1, min($configured_limit, $wordpress_limit));
    }

    /**
     * Format a consistent upload-size validation error.
     */
    protected static function get_file_size_error_message(int $current_size, int $max_size): string {
        return sprintf(
            /* translators: 1: Uploaded file size (MB), 2: Maximum allowed size (MB). */
            esc_html__('File size exceeds the maximum allowed size: %1$s MB > %2$s MB', 'elzo-forms'),
            esc_html(round($current_size / 1024 / 1024, 2)),
            esc_html(round($max_size / 1024 / 1024, 2))
        );
    }

    /**
     * Get the default safe file types used when a field has no explicit allowlist.
     *
     * @return array
     */
    public static function get_default_allowed_file_type_labels(): array {
        return self::normalize_allowed_types(self::get_default_allowed_file_types())['labels'];
    }

    /**
     * Get the default safe file types as a comma-separated string.
     */
    public static function get_default_allowed_file_types_string(): string {
        return implode(', ', self::get_default_allowed_file_type_labels());
    }

    /**
     * Resolve and verify a tokenized unattached upload URL from a submitted field value.
     *
     * @param string     $file_url Submitted URL from the hidden file field input.
     * @param string|int $field_id Expected field ID. Omitting it skips the check;
     *                             naming a field always enforces it.
     * @param int        $form_id  Expected form ID. 0 skips the check; a real ID
     *                             always enforces it.
     * @return array|null Verified upload data, or null when the URL/token is invalid.
     */
    public static function resolve_submitted_file_url(string $file_url, $field_id = '', $form_id = 0): ?array {
        $query = [];
        $parts = wp_parse_url($file_url);
        if (!is_array($parts)) {
            return null;
        }

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $upload_id_value = $query[self::UPLOAD_ID_QUERY_ARG] ?? '';
        $token_value = $query[self::UPLOAD_TOKEN_QUERY_ARG] ?? '';
        $upload_id = is_scalar($upload_id_value) ? sanitize_key((string) $upload_id_value) : '';
        $token = is_scalar($token_value) ? sanitize_text_field((string) $token_value) : '';
        if (empty($upload_id) || empty($token)) {
            return null;
        }

        $session = self::get_upload_session($upload_id);
        if (empty($session['finalized']) || empty($session['path']) || empty($session['token'])) {
            return null;
        }

        /*
         * Naming a field always enforces the binding, including when the ID
         * cannot belong to any session: an upload is only ever stored under a
         * usable field ID, so an unusable one must fail closed rather than skip
         * the check and let a file prepared for another field through.
         */
        $names_a_field = is_scalar($field_id) && !in_array(trim((string) $field_id), ['', '0'], true);
        $expected_field_id = self::normalize_field_id($field_id);

        if ($names_a_field && (string) ($session['field_id'] ?? '') !== $expected_field_id) {
            return null;
        }

        /*
         * Field IDs are unique only inside one form, so the field check alone
         * still lets an upload prepared under one form's size and type limits
         * be submitted to another form whose field happens to share that ID.
         * A caller that knows its form binds to it too. Unlike the field ID, 0
         * is a real state here — a field built outside a form has no form to
         * name — so it skips the check rather than failing closed.
         */
        $expected_form_id = is_scalar($form_id) ? max(0, intval($form_id)) : 0;

        if ($expected_form_id > 0 && intval($session['form_id'] ?? 0) !== $expected_form_id) {
            return null;
        }

        if (!hash_equals((string) $session['token'], $token)) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        $clean_url = self::strip_url_query_and_fragment($file_url);
        $base_url = rtrim((string) $upload_dir['baseurl'], '/');
        if (strpos($clean_url, $base_url . '/') !== 0) {
            return null;
        }

        $relative_path = ltrim(substr($clean_url, strlen($base_url)), '/');
        $file_path = rtrim((string) $upload_dir['basedir'], '/\\') . '/' . $relative_path;
        $file_path = self::normalize_path($file_path);
        $session_path = self::normalize_path((string) $session['path']);

        if ($file_path !== $session_path) {
            return null;
        }

        if (!file_exists($file_path) || !self::path_is_in_directory($file_path, self::get_unattached_upload_dir($upload_dir))) {
            return null;
        }

        return [
            'path' => $file_path,
            'url' => $clean_url,
            'upload_id' => $upload_id,
            'file_name' => basename($file_path),
        ];
    }

    /**
     * Delete a consumed upload session.
     */
    public static function consume_upload_session(string $upload_id): void {
        self::delete_upload_session($upload_id);
    }

    /**
     * Create the protected upload roots used by file fields.
     *
     * This is also called on activation so that existing upload directories no
     * longer expose directory listings before the next file is uploaded.
     */
    public static function initialize_upload_directories(): bool {
        $upload_dir = wp_upload_dir();
        $user_uploads_dir = self::get_user_uploads_dir($upload_dir);

        return self::ensure_upload_directory($user_uploads_dir)
            && self::ensure_upload_directory(self::get_unattached_upload_dir($upload_dir), true);
    }

    /**
     * Schedule hourly cleanup for uploads that were never attached to a submission.
     */
    public static function schedule_cleanup(): void {
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            $hour = defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600;
            wp_schedule_event(time() + $hour, 'hourly', self::CLEANUP_HOOK);
        }
    }

    /**
     * Remove unattached upload sessions that are expired or abandoned.
     *
     * A marker persists the session timestamps on disk because expired
     * transients can no longer identify the file that belonged to a session.
     */
    public static function cleanup_expired_uploads(): void {
        $unattached_dir = self::get_unattached_upload_dir();
        if (!is_dir($unattached_dir)) {
            return;
        }

        $now = time();
        $entries = new \FilesystemIterator($unattached_dir, \FilesystemIterator::SKIP_DOTS);

        foreach ($entries as $entry) {
            if (!$entry->isDir() || $entry->isLink() || !preg_match('/^[a-f0-9]{48}$/', $entry->getFilename())) {
                continue;
            }

            $session_dir = self::normalize_path($entry->getPathname());
            $marker = self::read_session_marker($session_dir);

            if (!self::upload_session_is_expired($marker, (int) $entry->getMTime(), $now)) {
                continue;
            }

            self::discard_upload_session_directory($session_dir, $marker);
        }
    }

    /**
     * Create a randomized permanent directory for one finalized upload.
     *
     * @param array $upload_dir Value returned by wp_upload_dir().
     * @return string Empty string when the directory could not be created.
     */
    public static function create_permanent_upload_directory(array $upload_dir): string {
        $user_uploads_dir = self::get_user_uploads_dir($upload_dir);
        $dated_upload_dir = $user_uploads_dir . '/' . gmdate('Y') . '/' . gmdate('m');

        if (!self::ensure_upload_directory($user_uploads_dir) || !self::ensure_upload_directory($dated_upload_dir)) {
            return '';
        }

        return self::create_randomized_upload_directory($dated_upload_dir);
    }

    /**
     * Generate an opaque storage filename while retaining the validated extension.
     */
    public static function generate_random_upload_filename(string $file_name, string $directory = ''): string {
        $extension = strtolower((string) pathinfo($file_name, PATHINFO_EXTENSION));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $random_name = self::generate_random_storage_component();
            $candidate = $extension === '' ? $random_name : $random_name . '.' . $extension;
            if ($directory === '' || !file_exists(rtrim($directory, '/\\') . '/' . $candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Parse allowed file types string.
     *
     * @param mixed $allowed_types_string Comma-separated types or an array of types.
     * @return array Normalized allowed extensions, MIME types, and display labels.
     */
    protected static function parse_allowed_types($allowed_types_string): array {
        $types = self::split_allowed_type_values($allowed_types_string);

        if (empty($types)) {
            $types = self::get_default_allowed_file_types();
        }

        return self::normalize_allowed_types($types);
    }

    /**
     * Get the default allowed file type values before normalization.
     */
    protected static function get_default_allowed_file_types(): array {
        /**
         * Filter the default allowed file types for public file upload fields.
         *
         * @param array $default_allowed_file_types Extensions or MIME types.
         */
        $default_allowed_file_types = apply_filters('elzo_forms_default_allowed_file_types', self::DEFAULT_ALLOWED_FILE_TYPES);

        return self::split_allowed_type_values($default_allowed_file_types);
    }

    /**
     * Split file type settings into individual values.
     *
     * @param mixed $allowed_types Allowed types as string or array.
     * @return array
     */
    protected static function split_allowed_type_values($allowed_types): array {
        if (is_array($allowed_types)) {
            $values = $allowed_types;
        } else {
            $values = explode(',', (string) $allowed_types);
        }

        $values = array_map(static function ($value) {
            return strtolower(trim((string) $value));
        }, $values);

        return array_values(array_filter($values, static function ($value) {
            return $value !== '';
        }));
    }

    /**
     * Normalize extensions and MIME types to strict comparison lists.
     */
    protected static function normalize_allowed_types(array $allowed_types): array {
        $normalized = [
            'extensions' => [],
            'mimes' => [],
            'labels' => [],
        ];

        foreach ($allowed_types as $allowed_type) {
            $allowed_type = strtolower(trim((string) $allowed_type));
            if ($allowed_type === '' || $allowed_type === '*') {
                continue;
            }

            if (strpos($allowed_type, '/') !== false) {
                if (!preg_match('/^[a-z0-9][a-z0-9.+-]*\/[a-z0-9][a-z0-9.+-]*$/', $allowed_type)) {
                    continue;
                }

                $normalized['mimes'][] = $allowed_type;
                $normalized['labels'][] = $allowed_type;
                continue;
            }

            $extension = preg_replace('/[^a-z0-9]/', '', ltrim($allowed_type, '.'));
            if (empty($extension)) {
                continue;
            }

            $normalized['extensions'][] = $extension;
            $normalized['labels'][] = '.' . $extension;
        }

        $normalized['extensions'] = array_values(array_unique($normalized['extensions']));
        $normalized['mimes'] = array_values(array_unique($normalized['mimes']));
        $normalized['labels'] = array_values(array_unique($normalized['labels']));

        return $normalized;
    }

    /**
     * Process file chunk upload.
     *
     * @param string $upload_path Full path to upload file
     * @param int $chunk_index Current chunk index
     * @param string $tmp_name Temporary uploaded chunk path
     * @throws \Exception If chunk processing fails
     */
    protected static function process_chunk(string $upload_path, int $chunk_index, string $tmp_name): void {
        if (empty($tmp_name)) {
            throw new \Exception(esc_html__('Uploaded file is empty', 'elzo-forms'));
        }

        /*
         * Chunked uploads arrive as slices, so wp_handle_upload() cannot assemble
         * the final file for us. The completed file is still validated with
         * WordPress filename and MIME helpers before it is accepted.
         */
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the local PHP upload temp file (already validated with is_uploaded_file()); wp_remote_get() is for remote URLs.
        $chunk = file_get_contents($tmp_name);

        if ($chunk === false) {
            throw new \Exception(esc_html__('Failed to read uploaded chunk', 'elzo-forms'));
        }

        // First chunk: create new file
        if ($chunk_index === 0) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Written with LOCK_EX to stay consistent with the appending branch below, which WP_Filesystem cannot express.
            $result = file_put_contents($upload_path, $chunk, LOCK_EX);
        } else {
            if (!file_exists($upload_path)) {
                throw new \Exception(esc_html__('Upload session is invalid', 'elzo-forms'));
            }

            // Subsequent chunks: append to existing file
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP_Filesystem has no append mode; assembling chunks requires FILE_APPEND | LOCK_EX.
            $result = file_put_contents($upload_path, $chunk, FILE_APPEND | LOCK_EX);
        }

        if ($result === false) {
            throw new \Exception(esc_html__('Failed to write file chunk', 'elzo-forms'));
        }
    }

    /**
     * Prepare or load an upload session.
     */
    protected static function prepare_upload_session(string $upload_id, int $chunk_index, string $custom_upload_dir, string $file_name, int $form_id, string $field_id, int $chunks_total = 1): array {
        if ($chunk_index === 0) {
            return self::create_upload_session($upload_id, $custom_upload_dir, $file_name, $form_id, $field_id, $chunks_total);
        }

        $session = self::get_upload_session($upload_id);
        if (empty($session['path']) || empty($session['file_name'])) {
            return [];
        }

        if (
            intval($session['form_id'] ?? 0) !== $form_id
            || (string) ($session['field_id'] ?? '') !== $field_id
            || intval($session['chunks_total'] ?? 0) !== $chunks_total
            || !empty($session['finalized'])
        ) {
            return [];
        }

        if (!file_exists((string) $session['path'])) {
            return [];
        }

        return self::touch_upload_session($upload_id, $session);
    }

    /**
     * Create one upload session while holding a filesystem lock so the global
     * session cap cannot be bypassed by concurrent first-chunk requests.
     */
    protected static function create_upload_session(string $upload_id, string $custom_upload_dir, string $file_name, int $form_id, string $field_id, int $chunks_total): array {
        $lock_path = rtrim($custom_upload_dir, '/\\') . '/' . self::SESSION_LOCK_FILE;
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- flock requires a native file handle.
        $lock = fopen($lock_path, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the native handle opened solely to acquire the flock() lock.
                fclose($lock);
            }
            return [];
        }

        try {
            $existing_session = self::get_upload_session($upload_id);
            if (!empty($existing_session)) {
                $same_upload = empty($existing_session['finalized'])
                    && intval($existing_session['form_id'] ?? 0) === $form_id
                    && (string) ($existing_session['field_id'] ?? '') === $field_id
                    && intval($existing_session['chunks_total'] ?? 0) === $chunks_total
                    && (string) ($existing_session['original_file_name'] ?? '') === $file_name
                    && !empty($existing_session['path'])
                    && self::path_is_in_directory(dirname((string) $existing_session['path']), $custom_upload_dir);

                return $same_upload ? self::touch_upload_session($upload_id, $existing_session) : [];
            }

            $client_id = self::get_upload_client_id();
            $capacity_error = self::check_upload_session_capacity($custom_upload_dir, $form_id, $client_id);
            if ($capacity_error !== '') {
                return ['error' => $capacity_error];
            }

            $session_directory = self::create_randomized_upload_directory($custom_upload_dir);
            if ($session_directory === '') {
                return [];
            }

            $unique_file_name = self::generate_random_upload_filename($file_name, $session_directory);
            if ($unique_file_name === '') {
                self::delete_directory($session_directory);
                return [];
            }

            $created_at = time();
            $session = [
                'path' => self::normalize_path($session_directory . '/' . $unique_file_name),
                'file_name' => $unique_file_name,
                'original_file_name' => $file_name,
                'form_id' => $form_id,
                'field_id' => $field_id,
                'client' => $client_id,
                'chunks_total' => $chunks_total,
                'created_at' => $created_at,
                'last_activity' => $created_at,
                'finalized' => false,
            ];

            if (!self::write_session_marker($upload_id, $session)) {
                self::delete_directory($session_directory);
                return [];
            }

            self::save_upload_session($upload_id, $session);
            return $session;
        } finally {
            flock($lock, LOCK_UN);
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the native handle opened solely to acquire the flock() lock.
            fclose($lock);
        }
    }

    /**
     * Check one new session against every active-session quota.
     *
     * Expired sessions are swept during the same pass. The caller holds the
     * creation lock and the scan already visits every directory, so abandoned
     * uploads are reclaimed by the next visitor rather than waiting for cron.
     *
     * @param string $custom_upload_dir Unattached upload root.
     * @param int    $form_id           Form the new session would belong to.
     * @param string $client_id         Hashed identity of the requesting client.
     * @return string Empty string when the session may be created, otherwise the
     *                error key naming the quota that stopped it.
     */
    protected static function check_upload_session_capacity(string $custom_upload_dir, int $form_id, string $client_id): string {
        $limits = self::get_upload_session_limits();
        $now = time();
        $sessions = 0;
        $client_sessions = 0;
        $form_sessions = 0;
        $bytes = 0;
        $entries = new \FilesystemIterator($custom_upload_dir, \FilesystemIterator::SKIP_DOTS);

        foreach ($entries as $entry) {
            if (!$entry->isDir() || $entry->isLink() || !preg_match('/^[a-f0-9]{48}$/', $entry->getFilename())) {
                continue;
            }

            $session_directory = self::normalize_path($entry->getPathname());
            $marker = self::read_session_marker($session_directory);

            if (self::upload_session_is_expired($marker, (int) $entry->getMTime(), $now)) {
                self::discard_upload_session_directory($session_directory, $marker);
                continue;
            }

            $sessions++;
            $bytes += self::measure_session_directory($session_directory);

            if ($client_id !== '' && hash_equals((string) ($marker['client'] ?? ''), $client_id)) {
                $client_sessions++;
            }

            if ($form_id > 0 && (int) ($marker['form_id'] ?? 0) === $form_id) {
                $form_sessions++;
            }
        }

        /*
         * The per-client quota is reported separately: it is the one a visitor
         * can act on, and it tells them nothing they do not already know about
         * their own uploads. The rest collapse into one message so a caller
         * cannot probe how loaded the site is.
         */
        if ($client_sessions >= $limits['client']) {
            return 'session_limit_client';
        }

        if ($sessions >= $limits['total'] || $form_sessions >= $limits['form'] || $bytes >= $limits['bytes']) {
            return 'session_limit';
        }

        return '';
    }

    /**
     * Resolve the active-session quotas.
     *
     * @return array{total:int,client:int,form:int,bytes:int}
     */
    protected static function get_upload_session_limits(): array {
        return [
            /**
             * Filter the maximum number of non-expired temporary upload sessions.
             *
             * This is the last-resort ceiling for the whole site. The narrower
             * per-client and per-form quotas are what stop one caller from
             * reaching it.
             *
             * @param int $max_sessions Maximum active sessions across all public forms.
             */
            'total' => max(1, absint(apply_filters('elzo_forms_max_active_upload_sessions', self::MAX_ACTIVE_UPLOAD_SESSIONS))),

            /**
             * Filter how many active upload sessions one client may hold.
             *
             * Files upload in parallel, so this has to cover a visitor picking
             * several files at once plus the finished uploads still waiting for
             * them to submit the form.
             *
             * @param int $max_sessions Maximum active sessions per client.
             */
            'client' => max(1, absint(apply_filters('elzo_forms_max_active_upload_sessions_per_client', self::MAX_ACTIVE_UPLOAD_SESSIONS_PER_CLIENT))),

            /**
             * Filter how many active upload sessions one form may hold.
             *
             * Keeps traffic on a single form from starving every other form on
             * the site.
             *
             * @param int $max_sessions Maximum active sessions per form.
             */
            'form' => max(1, absint(apply_filters('elzo_forms_max_active_upload_sessions_per_form', self::MAX_ACTIVE_UPLOAD_SESSIONS_PER_FORM))),

            /**
             * Filter the disk budget for unattached uploads, in bytes.
             *
             * Checked before a session is created, so the assembled files can
             * still exceed it by one upload.
             *
             * @param int $max_bytes Maximum bytes held by active sessions.
             */
            'bytes' => max(1, absint(apply_filters('elzo_forms_max_active_upload_bytes', self::MAX_ACTIVE_UPLOAD_BYTES))),
        ];
    }

    /**
     * Get the idle timeout for a partially uploaded file, in seconds.
     */
    protected static function get_session_idle_lifetime(): int {
        /**
         * Filter how long a partially uploaded file may sit idle before it is
         * treated as abandoned.
         *
         * Chunks arrive back to back, so this measures a stalled or discarded
         * upload, not a visitor taking their time over the rest of the form.
         *
         * @param int $idle_lifetime Idle timeout in seconds.
         */
        return max(60, absint(apply_filters('elzo_forms_upload_session_idle_lifetime', self::SESSION_IDLE_LIFETIME)));
    }

    /**
     * Decide whether an unattached session directory may be reclaimed.
     *
     * @param array $marker         Session marker read from the directory.
     * @param int   $fallback_mtime Directory mtime, used when the marker has no timestamp.
     * @param int   $now            Current timestamp.
     */
    protected static function upload_session_is_expired(array $marker, int $fallback_mtime, int $now): bool {
        $created_at = intval($marker['created_at'] ?? $fallback_mtime);

        // An unreadable timestamp is not evidence that the session is dead, and
        // deleting on it would throw away a live upload.
        if ($created_at <= 0) {
            return false;
        }

        if ($created_at <= $now - self::SESSION_LIFETIME) {
            return true;
        }

        /*
         * A finalized upload is waiting for the visitor to submit the form, not
         * for another chunk, so idleness says nothing about whether it is live.
         * It expires on the absolute lifetime checked above.
         */
        if (!empty($marker['finalized'])) {
            return false;
        }

        $last_activity = intval($marker['last_activity'] ?? 0);
        if ($last_activity <= 0) {
            $last_activity = $created_at;
        }

        return $last_activity <= $now - self::get_session_idle_lifetime();
    }

    /**
     * Delete one expired session directory and the transient behind it.
     */
    protected static function discard_upload_session_directory(string $session_directory, array $marker): void {
        self::delete_directory($session_directory);

        $upload_id = isset($marker['upload_id']) && is_string($marker['upload_id'])
            ? sanitize_key($marker['upload_id'])
            : '';
        if ($upload_id !== '') {
            self::delete_upload_session($upload_id);
        }
    }

    /**
     * Sum the bytes one session directory holds on disk.
     */
    protected static function measure_session_directory(string $session_directory): int {
        if (!is_dir($session_directory)) {
            return 0;
        }

        $bytes = 0;
        $entries = new \FilesystemIterator($session_directory, \FilesystemIterator::SKIP_DOTS);

        foreach ($entries as $entry) {
            if ($entry->isFile() && !$entry->isLink()) {
                $bytes += max(0, (int) $entry->getSize());
            }
        }

        return $bytes;
    }

    /**
     * Identify the client an upload session is counted against.
     *
     * Only REMOTE_ADDR is trusted. Proxy headers are supplied by the caller,
     * and a forged one would hand an attacker an unlimited supply of
     * identities, which is exactly what the per-client quota exists to stop.
     * Sites behind a CDN can resolve the real client through the filter.
     *
     * The address is stored hashed: quotas only ever compare identities, so
     * there is no reason to keep visitor IP addresses in the uploads directory.
     */
    protected static function get_upload_client_id(): string {
        $remote_addr = isset($_SERVER['REMOTE_ADDR']) && is_scalar($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']))
            : '';
        $client_ip = filter_var($remote_addr, FILTER_VALIDATE_IP);

        /**
         * Filter the client identity an upload session is counted against.
         *
         * @param string $client_ip Validated REMOTE_ADDR, or an empty string when there is none.
         */
        $client_ip = (string) apply_filters('elzo_forms_upload_client_id', $client_ip ? $client_ip : '');

        return $client_ip === '' ? '' : substr(wp_hash($client_ip), 0, 32);
    }

    /**
     * Refresh the activity stamp of a session that is still being uploaded.
     */
    protected static function touch_upload_session(string $upload_id, array $session): array {
        $session['last_activity'] = time();

        self::save_upload_session($upload_id, $session);
        self::write_session_marker($upload_id, $session);

        return $session;
    }

    /**
     * Mark an assembled upload as ready for form submission.
     */
    protected static function finalize_upload_session(string $upload_id, array $session): array {
        $session['token'] = self::generate_upload_token();
        $session['finalized'] = true;
        $session['completed_at'] = time();
        $session['last_activity'] = $session['completed_at'];

        self::save_upload_session($upload_id, $session);
        self::write_session_marker($upload_id, $session);
        return $session;
    }

    /**
     * Validate the assembled file against normalized allowed types.
     *
     * @return string Empty string when valid; escaped error message when invalid.
     */
    protected static function validate_file_type(string $upload_path, array $allowed_types): string {
        $validation_error = self::validate_file_name_type($upload_path, $allowed_types);
        if ($validation_error) {
            return $validation_error;
        }

        $allowed_mime_map = self::build_allowed_mime_map($allowed_types);
        if (!empty($allowed_mime_map) && function_exists('wp_check_filetype_and_ext')) {
            $checked = wp_check_filetype_and_ext($upload_path, basename($upload_path), $allowed_mime_map);
            if (empty($checked['ext']) || empty($checked['type'])) {
                return esc_html__('File content type does not match the allowed file types', 'elzo-forms');
            }
        }

        return '';
    }

    /**
     * Validate a filename against the configured allowlist before writing a chunk.
     *
     * @return string Empty string when valid; escaped error message when invalid.
     */
    protected static function validate_file_name_type(string $file_name, array $allowed_types): string {
        $file_info = function_exists('wp_check_filetype')
            ? wp_check_filetype($file_name, self::get_wordpress_mime_map())
            : [
                'ext' => strtolower(pathinfo($file_name, PATHINFO_EXTENSION)),
                'type' => '',
            ];

        $file_ext = strtolower((string) ($file_info['ext'] ?? pathinfo($file_name, PATHINFO_EXTENSION)));
        $file_type = strtolower((string) ($file_info['type'] ?? ''));
        $extension_allowed = $file_ext !== '' && in_array($file_ext, $allowed_types['extensions'], true);
        $mime_allowed = $file_type !== '' && in_array($file_type, $allowed_types['mimes'], true);

        if (!$extension_allowed && !$mime_allowed) {
            $detected_type = $file_type ?: ($file_ext ? '.' . $file_ext : esc_html__('unknown', 'elzo-forms'));

            return sprintf(
                /* translators: 1: Detected file type, 2: Comma-separated list of allowed file types. */
                esc_html__('File type is not allowed: %1$s (Allowed: %2$s)', 'elzo-forms'),
                esc_html($detected_type),
                esc_html(implode(', ', $allowed_types['labels']))
            );
        }

        return '';
    }

    /**
     * Build a WordPress MIME map constrained to the normalized allowed types.
     */
    protected static function build_allowed_mime_map(array $allowed_types): array {
        $mime_map = [];
        foreach (self::get_wordpress_mime_map() as $extensions => $mime_type) {
            $extension_list = explode('|', strtolower((string) $extensions));
            $mime_type = strtolower((string) $mime_type);

            if (array_intersect($extension_list, $allowed_types['extensions']) || in_array($mime_type, $allowed_types['mimes'], true)) {
                $mime_map[$extensions] = $mime_type;
            }
        }

        return $mime_map;
    }

    /**
     * Get WordPress MIME types with a small fallback for test and non-WP contexts.
     */
    protected static function get_wordpress_mime_map(): array {
        if (function_exists('get_allowed_mime_types')) {
            return get_allowed_mime_types();
        }

        if (function_exists('wp_get_mime_types')) {
            return wp_get_mime_types();
        }

        return [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'txt' => 'text/plain',
        ];
    }

    /**
     * Check dangerous extensions regardless of configured allowlists.
     */
    protected static function has_dangerous_extension(string $file_name): bool {
        $dangerous_extensions = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar'];

        /**
         * Filter the list of dangerous file extensions that are not allowed to be uploaded.
         *
         * @param array $dangerous_extensions List of dangerous file extensions
         * @return array Modified list of dangerous extensions
         */
        $dangerous_extensions = apply_filters('elzo_forms_dangerous_extensions', $dangerous_extensions);

        $extension = strtolower((string) pathinfo($file_name, PATHINFO_EXTENSION));

        return $extension !== '' && in_array($extension, array_map('strtolower', $dangerous_extensions), true);
    }

    /**
     * Store upload session metadata.
     */
    protected static function save_upload_session(string $upload_id, array $session): void {
        set_transient(self::get_upload_session_key($upload_id), $session, self::SESSION_LIFETIME);
    }

    /**
     * Read upload session metadata.
     */
    protected static function get_upload_session(string $upload_id): array {
        $session = get_transient(self::get_upload_session_key($upload_id));

        return is_array($session) ? $session : [];
    }

    /**
     * Delete upload session metadata.
     */
    protected static function delete_upload_session(string $upload_id): void {
        $session = self::get_upload_session($upload_id);
        delete_transient(self::get_upload_session_key($upload_id));

        $path = isset($session['path']) && is_string($session['path']) ? self::normalize_path($session['path']) : '';
        $unattached_dir = self::normalize_path(self::get_unattached_upload_dir());
        $session_directory = $path !== '' ? dirname($path) : '';
        if (
            $session_directory !== ''
            && $session_directory !== $unattached_dir
            && preg_match('/^[a-f0-9]{48}$/', basename($session_directory))
            && self::path_is_in_directory($session_directory, $unattached_dir)
        ) {
            self::delete_directory($session_directory);
        }
    }

    /**
     * Build a transient key for an upload session.
     */
    protected static function get_upload_session_key(string $upload_id): string {
        return self::UPLOAD_TRANSIENT_PREFIX . $upload_id;
    }

    /**
     * Generate an unguessable token for the finalized upload.
     */
    protected static function generate_upload_token(): string {
        try {
            return bin2hex(random_bytes(24));
        } catch (\Exception $e) {
            return md5(uniqid('', true));
        }
    }

    /**
     * Get the temporary unattached upload directory.
     */
    protected static function get_unattached_upload_dir(?array $upload_dir = null): string {
        $upload_dir = $upload_dir ?: wp_upload_dir();

        return rtrim((string) $upload_dir['basedir'], '/\\') . '/' . self::UPLOAD_SUBDIR;
    }

    /**
     * Get the root directory for all user-submitted files.
     */
    protected static function get_user_uploads_dir(array $upload_dir): string {
        return rtrim((string) $upload_dir['basedir'], '/\\') . '/' . self::USER_UPLOADS_SUBDIR;
    }

    /**
     * Create a directory and its directory-listing protections.
     */
    protected static function ensure_upload_directory(string $directory, bool $deny_access = false): bool {
        if (!wp_mkdir_p($directory) || !is_dir($directory)) {
            return false;
        }

        $index_file = rtrim($directory, '/\\') . '/index.php';
        if (!file_exists($index_file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Creates a static index file in the plugin-owned uploads directory.
            if (file_put_contents($index_file, "<?php\n// Silence is golden.\n", LOCK_EX) === false) {
                return false;
            }
        }

        $htaccess_file = rtrim($directory, '/\\') . '/.htaccess';
        $rules = self::get_upload_directory_rules($deny_access);
        $existing_rules = '';
        if (file_exists($htaccess_file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a small static configuration file from the plugin-owned uploads directory.
            $existing_rules = file_get_contents($htaccess_file);
            if ($existing_rules === false) {
                return false;
            }
        }

        if (strpos($existing_rules, '# BEGIN Elzo Forms') === false) {
            $separator = $existing_rules === '' || substr($existing_rules, -1) === "\n" ? '' : "\n";
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes static access rules in the plugin-owned uploads directory.
            if (file_put_contents($htaccess_file, $existing_rules . $separator . $rules, LOCK_EX) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create a unique opaque subdirectory below a protected upload directory.
     */
    protected static function create_randomized_upload_directory(string $parent_directory): string {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $directory = rtrim($parent_directory, '/\\') . '/' . self::generate_random_storage_component();
            if (file_exists($directory)) {
                continue;
            }

            if (self::ensure_upload_directory($directory)) {
                return $directory;
            }
        }

        return '';
    }

    /**
     * Persist the quota and cleanup metadata independently of the transient.
     *
     * Cleanup and the quota scan read only this file, so everything they need
     * to judge a session has to survive the transient expiring.
     */
    protected static function write_session_marker(string $upload_id, array $session): bool {
        $session_directory = !empty($session['path']) ? dirname((string) $session['path']) : '';
        if ($session_directory === '' || !is_dir($session_directory)) {
            return false;
        }

        $created_at = intval($session['created_at'] ?? time());
        $marker_path = rtrim($session_directory, '/\\') . '/' . self::SESSION_MARKER_FILE;
        $marker_data = wp_json_encode([
            'upload_id' => $upload_id,
            'created_at' => $created_at,
            'last_activity' => intval($session['last_activity'] ?? $created_at),
            'finalized' => !empty($session['finalized']),
            'form_id' => intval($session['form_id'] ?? 0),
            'client' => (string) ($session['client'] ?? ''),
        ]);

        if (!is_string($marker_data)) {
            return false;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes private cleanup metadata inside the plugin-owned protected upload directory.
        return file_put_contents($marker_path, $marker_data, LOCK_EX) !== false;
    }

    /**
     * Read cleanup metadata for an unattached upload directory.
     */
    protected static function read_session_marker(string $session_directory): array {
        $marker_path = rtrim($session_directory, '/\\') . '/' . self::SESSION_MARKER_FILE;
        if (!is_file($marker_path)) {
            return [];
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a small private metadata file from the plugin-owned upload directory.
        $contents = file_get_contents($marker_path);
        if (!is_string($contents)) {
            return [];
        }

        $marker = json_decode($contents, true);
        return is_array($marker) ? $marker : [];
    }

    /**
     * Recursively delete one validated, plugin-owned session directory.
     */
    protected static function delete_directory(string $directory): void {
        if (!is_dir($directory) || is_link($directory)) {
            return;
        }

        if (!class_exists('\WP_Filesystem_Base', false)) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        }

        if (!class_exists('\WP_Filesystem_Direct', false)) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        }

        $entries = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);
        foreach ($entries as $entry) {
            $path = $entry->getPathname();
            if ($entry->isDir() && !$entry->isLink()) {
                self::delete_directory($path);
            } else {
                wp_delete_file($path);
            }
        }

        $filesystem = new \WP_Filesystem_Direct(false);
        $filesystem->rmdir($directory);
    }

    /**
     * Build Apache rules that prevent directory listings and, for temporary
     * files, deny every direct HTTP request.
     */
    protected static function get_upload_directory_rules(bool $deny_access): string {
        $rules = "# BEGIN Elzo Forms\nOptions -Indexes\n";

        if ($deny_access) {
            $rules .= "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n";
            $rules .= "<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";
        }

        return $rules . "# END Elzo Forms\n";
    }

    /**
     * Generate a cryptographically random path component.
     */
    protected static function generate_random_storage_component(): string {
        try {
            return bin2hex(random_bytes(24));
        } catch (\Exception $e) {
            return str_replace('-', '', wp_generate_uuid4());
        }
    }

    /**
     * Convert an upload path to its public URL.
     */
    protected static function path_to_upload_url(string $upload_path, array $upload_dir): string {
        $base_dir = rtrim(self::normalize_path((string) $upload_dir['basedir']), '/');
        $upload_path = self::normalize_path($upload_path);
        $relative_path = ltrim(substr($upload_path, strlen($base_dir)), '/');

        return rtrim((string) $upload_dir['baseurl'], '/') . '/' . $relative_path;
    }

    /**
     * Add upload verification data to a URL that will be submitted by the hidden field.
     */
    protected static function add_upload_token_to_url(string $file_url, string $upload_id, string $token): string {
        return add_query_arg([
            self::UPLOAD_ID_QUERY_ARG => $upload_id,
            self::UPLOAD_TOKEN_QUERY_ARG => $token,
        ], $file_url);
    }

    /**
     * Remove all query and fragment data from an uploaded file URL.
     */
    protected static function strip_url_query_and_fragment(string $url): string {
        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return '';
        }

        if (empty($parts['scheme']) || empty($parts['host']) || empty($parts['path'])) {
            return '';
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $parts['scheme'] . '://' . $parts['host'] . $port . $parts['path'];
    }

    /**
     * Normalize filesystem separators.
     */
    protected static function normalize_path(string $path): string {
        return str_replace('\\', '/', $path);
    }

    /**
     * Confirm a file path resolves inside a directory.
     */
    protected static function path_is_in_directory(string $path, string $directory): bool {
        $real_path = realpath($path);
        $real_directory = realpath($directory);
        if (!$real_path || !$real_directory) {
            return false;
        }

        $real_path = self::normalize_path($real_path);
        $real_directory = rtrim(self::normalize_path($real_directory), '/') . '/';

        return strpos($real_path, $real_directory) === 0;
    }
}
