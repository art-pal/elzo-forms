<?php
/**
 * Submission model class.
 *
 * Represents a form submission with data validation, storage, and retrieval.
 *
 * @package ElzoForms\Submission
 */

namespace ElzoForms\Submission;

use ElzoForms\Form\Form;
use ElzoForms\Field\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Submission {

    /** @var int Submission ID */
    protected $id = 0;

    /** @var \WP_Post Submission post object */
    protected $post = null;

    /** @var array Submission data */
    protected $data = [];

    /** @var Form Associated form object */
    protected $form = null;

    /** @var bool Whether submission data has already been loaded */
    protected bool $loaded = false;

    /** @var array Static cache per submission ID to avoid double decoding */
    protected static array $cache = [];

    /** @var bool Whether submission should be marked as spam */
    protected bool $is_spam_flag = false;

    /** @var string Original GMT submission date applied when the submission is first saved */
    protected string $post_date_gmt = '';

    /**
     * Constructor.
     *
     * @param int|\WP_Post|array $submission Submission ID, post object, or data array
     */
    public function __construct($submission = 0) {
        if (is_numeric($submission)) {
            $this->id = intval($submission);
            $this->post = get_post($this->id);

            if ($this->post && $this->post->post_type === 'elzo_submission') {
                $this->load_data();
            }
        } elseif ($submission instanceof \WP_Post) {
            $this->post = $submission;
            $this->id = $submission->ID;
            $this->load_data();
        } elseif (is_array($submission)) {
            $this->data = wp_parse_args($submission, $this->get_defaults());
        }
    }

    /**
     * Get submission defaults.
     */
    protected function get_defaults(): array {
        return [
            'form_id' => 0,
            'fields' => [],
            'user' => [],
            'submitted_in' => 0,
            'page' => [],
        ];
    }

    /**
     * Load submission data from post content.
     */
    protected function load_data(): void {
        if ($this->loaded) {
            return; // Prevent double decoding/logging within the same instance
        }

        // If we already decoded this submission elsewhere, reuse it
        if ($this->id && isset(self::$cache[$this->id])) {
            $this->data = self::$cache[$this->id];
            $this->loaded = true;
            return;
        }

        if (!$this->post || !$this->post->post_content) {
            $this->data = $this->get_defaults();
            $this->loaded = true;
            return;
        }

        $decoded = json_decode($this->post->post_content, true);

        $this->data = $decoded ? wp_parse_args($decoded, $this->get_defaults()) : $this->get_defaults();
        if ($this->id) {
            self::$cache[$this->id] = $this->data;
        }
        $this->loaded = true;
    }

    /**
     * Get submission ID.
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Get submission post object.
     */
    public function get_post(): ?\WP_Post {
        return $this->post;
    }

    /**
     * Get raw post content (JSON string).
     */
    public function get_raw_content(): string {
        return $this->post->post_content ?? '';
    }

    /**
     * Get submission title.
     */
    public function get_title(): string {
        return $this->post ? get_the_title($this->post) : '';
    }

    /**
     * Get submission status.
     */
    public function get_status(): string {
        return $this->post ? $this->post->post_status : '';
    }

    /**
     * Check if submission is spam.
     */
    public function is_spam(): bool {
        return $this->get_status() === 'spam';
    }

    /**
     * Get all submission data as array.
     */
    public function to_array(): array {
        return $this->data;
    }

    /**
     * Get form ID.
     */
    public function get_form_id(): int {
        return intval($this->data['form_id'] ?? 0);
    }

    /**
     * Get associated form object.
     */
    public function get_form(): ?Form {
        if (!$this->form && $this->get_form_id()) {
            $this->form = new Form($this->get_form_id());
        }
        return $this->form;
    }

    /**
     * Get submission fields.
     */
    public function get_fields(): array {
        return $this->data['fields'] ?? [];
    }

    /**
     * Get field value by field ID.
     */
    public function get_field_value(string $field_id) {
        $fields = $this->get_fields();
        foreach ($fields as $field) {
            if (isset($field['id']) && (string) $field['id'] === $field_id) {
                return $field['value'] ?? null;
            }
        }
        return null;
    }

    /**
     * Get user data.
     */
    public function get_user_data(): array {
        return $this->data['user'] ?? [];
    }

    /**
     * Get user IP address.
     */
    public function get_user_ip(): string {
        $user = $this->get_user_data();
        return $user['ip'] ?? '';
    }

    /**
     * Get user agent.
     */
    public function get_user_agent(): string {
        $user = $this->get_user_data();
        return $user['user_agent'] ?? '';
    }

    /**
     * Get submission time in seconds.
     */
    public function get_submitted_in(): int {
        return intval($this->data['submitted_in'] ?? 0);
    }

    /**
     * Get the page the form was submitted on.
     *
     * Empty when the submission carries no page context: it either predates
     * this field or the request never sent one. An 'id' of 0 with a URL is a
     * real answer, not a missing one, because a form can be submitted from a
     * view that has no queried post (archive, search, 404).
     *
     * @return array{id?: int, url?: string}
     */
    public function get_page_data(): array {
        $page = $this->data['page'] ?? [];
        return is_array($page) ? $page : [];
    }

    /**
     * Get the ID of the page the form was submitted on. 0 when unknown.
     */
    public function get_page_id(): int {
        $page = $this->get_page_data();
        return intval($page['id'] ?? 0);
    }

    /**
     * Get the URL of the page the form was submitted on. '' when unknown.
     */
    public function get_page_url(): string {
        $page = $this->get_page_data();
        return isset($page['url']) && is_scalar($page['url']) ? (string) $page['url'] : '';
    }

    /**
     * Get submission date.
     */
    public function get_date(): string {
        return $this->post ? get_the_date('', $this->post) : '';
    }

    /**
     * Save submission to database.
     *
     * @return int|\WP_Error Post ID on success, WP_Error on failure
     */
    public function save() {
        $submission_data = $this->to_array();

        // Initialize post content
        $post_content = '';

        try {
            // Encode submission data to JSON
            $post_content = wp_json_encode($submission_data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            /* translators: %s: JSON encoding error message. */
            return new \WP_Error('json_encode_error', __('Failed to encode submission data to JSON: %s', 'elzo-forms'), $e->getMessage());
        }

        // Prepare post data
        $post_data = [
            'post_type' => 'elzo_submission',
            'post_status' => $this->is_spam_flag ? 'spam' : 'publish',
            'post_content' => wp_slash($post_content),
        ];

        // Set post author at insert/update time if available
        $user_data = $this->get_user_data();
        $user_id = isset($user_data['user_id']) ? intval($user_data['user_id']) : 0;
        if ($user_id > 0) {
            $post_data['post_author'] = $user_id;
        }

        // Set title if available
        $title = $this->generate_title();
        if ($title) {
            $post_data['post_title'] = wp_slash($title);
        }

        // Update or insert
        if ($this->id) {
            $post_data['ID'] = $this->id;
            $result = wp_update_post($post_data, true);

            if (!is_wp_error($result)) {
                $this->sync_index_meta();
            }
        } else {
            // The submitter owns the post: a guest is stored as 0 rather than
            // as whoever runs the request that stores the submission.
            $post_data['post_author'] = max(0, $user_id);

            if ($this->post_date_gmt !== '') {
                $post_data['post_date_gmt'] = $this->post_date_gmt;
                $post_data['post_date'] = get_date_from_gmt($this->post_date_gmt);
            }

            $result = wp_insert_post($post_data, true);

            if (!is_wp_error($result)) {
                $this->id = $result;
                $this->post = get_post($this->id);

                $this->sync_index_meta();
            }
        }

        return $result;
    }

    /**
     * Keep lookup meta in sync for fast queries (throttle, admin filters, etc.).
     */
    protected function sync_index_meta(): void {
        if (!$this->id) {
            return;
        }

        if ($this->get_form_id()) {
            update_post_meta($this->id, 'form_id', $this->get_form_id());
        }

        $user_ip = $this->get_user_ip();
        if ($user_ip !== '') {
            update_post_meta($this->id, 'user_ip', $user_ip);
        }
    }

    /**
     * Generate submission title from primary field.
     */
    protected function generate_title(): string {
        $fields = $this->get_fields();
        $fallback_value = null;

        foreach ($fields as $field) {
            if (empty($field['value'])) {
                continue;
            }

            // Return immediately if this is the primary field
            if (!empty($field['primary_field'])) {
                $value = is_array($field['value']) ? implode(', ', $field['value']) : $field['value'];
                return $this->truncate_title(wp_strip_all_tags($value));
            }

            // Store first non-empty field value as fallback (defer processing)
            if ($fallback_value === null) {
                $fallback_value = $field['value'];
            }
        }

        if ($fallback_value !== null) {
            $fallback = is_array($fallback_value) ? implode(', ', $fallback_value) : $fallback_value;
            return $this->truncate_title(wp_strip_all_tags($fallback));
        }

        return $this->truncate_title(__('New submission', 'elzo-forms'));
    }

    /**
     * Truncate title to maximum length.
     *
     * @param string $title The title to truncate
     * @return string Truncated title
     */
    protected function truncate_title(string $title): string {
        $max_length = apply_filters('elzo_forms/submission/title_max_length', 120);

        if (mb_strlen($title) <= $max_length) {
            return $title;
        }

        return mb_substr($title, 0, $max_length) . '…';
    }

    /**
     * Mark submission as spam.
     * If not saved yet, sets flag to create as spam. Otherwise updates existing post.
     */
    public function mark_as_spam(): bool {
        if (!$this->id) {
            // Not saved yet - just set the flag
            $this->is_spam_flag = true;
            return true;
        }

        // Already saved - update the post
        $result = wp_update_post([
            'ID' => $this->id,
            'post_status' => 'spam',
        ]);

        if ($result && !is_wp_error($result)) {
            $this->post = get_post($this->id);
            $this->is_spam_flag = true;
            return true;
        }

        return false;
    }

    /**
     * Mark submission as not spam.
     */
    public function unmark_as_spam(): bool {
        if (!$this->id) {
            return false;
        }

        $result = wp_update_post([
            'ID' => $this->id,
            'post_status' => 'publish',
        ]);

        if ($result && !is_wp_error($result)) {
            $this->post = get_post($this->id);
            return true;
        }

        return false;
    }

    /**
     * Delete submission.
     */
    public function delete(bool $force_delete = false): bool {
        if (!$this->id) {
            return false;
        }

        $result = wp_delete_post($this->id, $force_delete);

        if ($result) {
            $this->id = 0;
            $this->post = null;
            return true;
        }

        return false;
    }

    /**
     * Set submission data.
     */
    public function set_data(array $data): self {
        $this->data = wp_parse_args($data, $this->data);
        return $this;
    }

    /**
     * Set form ID.
     */
    public function set_form_id(int $form_id): self {
        $this->data['form_id'] = $form_id;
        return $this;
    }

    /**
     * Set fields.
     */
    public function set_fields(array $fields): self {
        $this->data['fields'] = $fields;
        return $this;
    }

    /**
     * Set user data.
     */
    public function set_user_data(array $user): self {
        $this->data['user'] = $user;
        return $this;
    }

    /**
     * Set submitted in time.
     */
    public function set_submitted_in(int $seconds): self {
        $this->data['submitted_in'] = $seconds;
        return $this;
    }

    /**
     * Set the original date of a submission stored after the fact.
     *
     * Applies when the submission is first saved, so a restored submission
     * keeps the date it was made rather than the date it was stored.
     *
     * @param string $date_gmt GMT date in Y-m-d H:i:s format.
     */
    public function set_post_date_gmt(string $date_gmt): self {
        $this->post_date_gmt = $date_gmt;
        return $this;
    }

    /**
     * Set the page the form was submitted on.
     *
     * Stored so anything running after the request, such as a re-run
     * automation, can still tell where the form was filled in.
     */
    public function set_page_data(array $page): self {
        $url = isset($page['url']) && is_scalar($page['url']) ? esc_url_raw((string) $page['url']) : '';

        $this->data['page'] = [
            'id' => isset($page['id']) ? absint($page['id']) : 0,
            'url' => $url,
        ];

        return $this;
    }
}
