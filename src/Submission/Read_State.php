<?php
/**
 * Read/unread state of submissions.
 *
 * A submission stored from the front end is marked unread, and opening it in
 * the admin marks it read. The state is a per-submission meta flag rather than
 * a post status, so it never interferes with spam handling, and the absence of
 * the flag means read: submissions stored before this feature, and imported
 * ones, stay out of the way.
 *
 * The state is optional. A site that reads submissions in email or in an
 * integration can turn it off globally or per form, and the submissions list
 * then shows no marker, no filter and no actions.
 *
 * @package ElzoForms\Submission
 */

namespace ElzoForms\Submission;

// Exit if accessed directly
defined('ABSPATH') || exit;

final class Read_State {

    /** Meta key set on unread submissions. Absent means read. */
    public const META_KEY = '_elzo_forms_unread';

    /** Form setting that turns the state on or off. */
    public const SETTING = 'submission_read_state';

    /** Query argument that narrows the submissions list to unread submissions. */
    public const FILTER_QUERY_ARG = 'elzo_unread';

    /** admin-post.php action behind the row action. */
    public const ACTION = 'elzo_forms_submission_read_state';

    /** Bulk action that marks submissions read. */
    private const BULK_READ = 'elzo_mark_read';

    /** Bulk action that marks submissions unread. */
    private const BULK_UNREAD = 'elzo_mark_unread';

    /** Query argument that carries a bulk result to its notice. */
    private const NOTICE_COUNT_ARG = 'elzo_read_updated';

    /** Query argument that carries which bulk result to announce. */
    private const NOTICE_STATE_ARG = 'elzo_read_state';

    /** @var bool */
    private static $initialized = false;

    /** @var bool|null Memoized result of is_enabled_anywhere(). */
    private static $enabled_anywhere = null;

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init(): void {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        add_action('elzo_forms_after_submission_saved', [__CLASS__, 'mark_new_submission'], 10, 3);

        if (!is_admin()) {
            return;
        }

        add_action('load-post.php', [__CLASS__, 'mark_opened_submission_read']);
        add_filter('post_class', [__CLASS__, 'add_row_class'], 10, 3);
        add_filter('post_row_actions', [__CLASS__, 'add_row_action'], 10, 2);
        add_filter('bulk_actions-edit-elzo_submission', [__CLASS__, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-edit-elzo_submission', [__CLASS__, 'handle_bulk_actions'], 10, 3);
        add_filter('views_edit-elzo_submission', [__CLASS__, 'add_unread_view']);
        add_action('parse_query', [__CLASS__, 'filter_unread_query']);
        add_action('admin_post_' . self::ACTION, [__CLASS__, 'handle_request']);
        add_action('admin_notices', [__CLASS__, 'render_notice']);
        add_filter('removable_query_args', [__CLASS__, 'removable_query_args']);
    }

    /**
     * Whether a submission is unread.
     *
     * @param int $submission_id Submission post ID.
     * @return bool
     */
    public static function is_unread(int $submission_id): bool {
        return $submission_id > 0 && get_post_meta($submission_id, self::META_KEY, true) !== '';
    }

    /**
     * Mark a submission unread.
     *
     * @param int $submission_id Submission post ID.
     * @return void
     */
    public static function mark_unread(int $submission_id): void {
        if ($submission_id <= 0 || get_post_type($submission_id) !== 'elzo_submission') {
            return;
        }

        update_post_meta($submission_id, self::META_KEY, '1');
    }

    /**
     * Mark a submission read.
     *
     * @param int $submission_id Submission post ID.
     * @return void
     */
    public static function mark_read(int $submission_id): void {
        if ($submission_id <= 0) {
            return;
        }

        delete_post_meta($submission_id, self::META_KEY);
    }

    /**
     * Whether submissions of a form are marked unread when they arrive.
     *
     * @param int $form_id Form post ID.
     * @return bool
     */
    public static function is_enabled_for_form(int $form_id): bool {
        if ($form_id <= 0 || !get_post($form_id)) {
            return self::global_setting() === 'yes';
        }

        $settings = \ElzoForms\Services\Settings::get_form_settings(['form_id' => $form_id]);
        $value = is_array($settings) && isset($settings[self::SETTING]) && is_scalar($settings[self::SETTING])
            ? sanitize_key((string) $settings[self::SETTING])
            : '';

        return $value === '' ? self::global_setting() === 'yes' : $value === 'yes';
    }

    /**
     * Whether the submissions list should offer the read/unread interface.
     *
     * The state is shown when it is on globally, or when any single form turns
     * it on while it is off globally.
     *
     * @return bool
     */
    public static function is_enabled_anywhere(): bool {
        if (self::$enabled_anywhere !== null) {
            return self::$enabled_anywhere;
        }

        if (self::global_setting() === 'yes') {
            self::$enabled_anywhere = true;

            return true;
        }

        $form_ids = get_posts([
            'post_type' => 'elzo_form',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        self::$enabled_anywhere = false;
        foreach ((array) $form_ids as $form_id) {
            if (self::is_enabled_for_form((int) $form_id)) {
                self::$enabled_anywhere = true;
                break;
            }
        }

        return self::$enabled_anywhere;
    }

    /**
     * Mark a submission stored from the front end as unread.
     *
     * Spam never reaches this hook, so spam submissions are not marked.
     *
     * @param mixed $form Form the submission belongs to.
     * @param mixed $submission Stored submission.
     * @param mixed $submission_id Stored submission post ID.
     * @return void
     */
    public static function mark_new_submission($form, $submission, $submission_id): void {
        $submission_id = is_scalar($submission_id) ? absint($submission_id) : 0;
        if ($submission_id <= 0) {
            return;
        }

        $form_id = $submission instanceof Submission ? (int) $submission->get_form_id() : 0;
        if (!self::is_enabled_for_form($form_id)) {
            return;
        }

        self::mark_unread($submission_id);
    }

    /**
     * Mark a submission read when it is opened in the admin.
     *
     * @return void
     */
    public static function mark_opened_submission_read(): void {
        $post_id = absint(self::query_arg('post'));

        if ($post_id <= 0 || get_post_type($post_id) !== 'elzo_submission') {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        self::mark_read($post_id);
    }

    /**
     * Mark the row of an unread submission.
     *
     * The list marks unread rows with a dot and a stripe rather than with a
     * column of its own, so the marker is carried by a row class.
     *
     * @param array $classes Row classes.
     * @param mixed $css_class Extra classes passed to get_post_class().
     * @param mixed $post_id Post the row shows.
     * @return array
     */
    public static function add_row_class($classes, $css_class, $post_id): array {
        $classes = is_array($classes) ? $classes : [];
        $post_id = is_scalar($post_id) ? absint($post_id) : 0;

        if ($post_id <= 0 || get_post_type($post_id) !== 'elzo_submission') {
            return $classes;
        }

        // Flags left over from before the state was turned off must not keep
        // marking rows that no longer have an action to clear them.
        if (!self::is_enabled_anywhere()) {
            return $classes;
        }

        if (self::is_unread($post_id)) {
            $classes[] = 'elzo-forms-submission-unread';
        }

        return $classes;
    }

    /**
     * Add the action that toggles the state of a single submission.
     *
     * @param array $actions Row actions.
     * @param mixed $post Post the row shows.
     * @return array
     */
    public static function add_row_action($actions, $post): array {
        $actions = is_array($actions) ? $actions : [];

        if (!$post instanceof \WP_Post || $post->post_type !== 'elzo_submission') {
            return $actions;
        }

        if (!self::is_enabled_anywhere() || !current_user_can('edit_post', $post->ID)) {
            return $actions;
        }

        $unread = self::is_unread((int) $post->ID);
        $state = $unread ? 'read' : 'unread';

        $actions['elzo_read_state'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url(self::url((int) $post->ID, $state)),
            $unread
                ? esc_html__('Mark as Read', 'elzo-forms')
                : esc_html__('Mark as Unread', 'elzo-forms')
        );

        return $actions;
    }

    /**
     * Get the URL that marks one submission read or unread.
     *
     * @param int $submission_id Submission post ID.
     * @param string $state Target state: read or unread.
     * @return string
     */
    public static function url(int $submission_id, string $state): string {
        return wp_nonce_url(
            add_query_arg(
                [
                    'action' => self::ACTION,
                    'post' => $submission_id,
                    'state' => $state === 'unread' ? 'unread' : 'read',
                ],
                admin_url('admin-post.php')
            ),
            self::ACTION . '_' . $submission_id
        );
    }

    /**
     * Add the bulk actions.
     *
     * @param array $actions Bulk actions.
     * @return array
     */
    public static function add_bulk_actions($actions): array {
        $actions = is_array($actions) ? $actions : [];

        if (!self::is_enabled_anywhere()) {
            return $actions;
        }

        $actions[self::BULK_READ] = esc_html__('Mark as Read', 'elzo-forms');
        $actions[self::BULK_UNREAD] = esc_html__('Mark as Unread', 'elzo-forms');

        return $actions;
    }

    /**
     * Apply the bulk actions.
     *
     * @param string $redirect_to Redirect URL.
     * @param string $action Bulk action being applied.
     * @param array $post_ids Selected submissions.
     * @return string
     */
    public static function handle_bulk_actions($redirect_to, $action, $post_ids): string {
        $redirect_to = is_string($redirect_to) ? $redirect_to : '';

        if ($action !== self::BULK_READ && $action !== self::BULK_UNREAD) {
            return $redirect_to;
        }

        $mark_unread = $action === self::BULK_UNREAD;
        $updated_count = 0;

        foreach ((array) $post_ids as $post_id) {
            $post_id = absint($post_id);
            if (
                $post_id <= 0
                || get_post_type($post_id) !== 'elzo_submission'
                || !current_user_can('edit_post', $post_id)
            ) {
                continue;
            }

            if ($mark_unread) {
                self::mark_unread($post_id);
            } else {
                self::mark_read($post_id);
            }

            $updated_count++;
        }

        return add_query_arg(
            [
                self::NOTICE_COUNT_ARG => $updated_count,
                self::NOTICE_STATE_ARG => $mark_unread ? 'unread' : 'read',
            ],
            $redirect_to
        );
    }

    /**
     * Add the Unread view to the submissions list.
     *
     * @param array $views List views.
     * @return array
     */
    public static function add_unread_view($views): array {
        $views = is_array($views) ? $views : [];

        if (!self::is_enabled_anywhere()) {
            return $views;
        }

        $count = self::count_unread();
        if ($count < 1 && !self::is_unread_filter_active()) {
            return $views;
        }

        $url = add_query_arg(
            [self::FILTER_QUERY_ARG => '1'],
            admin_url('edit.php?post_type=elzo_submission')
        );

        if (self::is_unread_filter_active()) {
            // Only one view is the current one: the status views the list built
            // do not know about this filter.
            foreach ($views as $key => $view) {
                $views[$key] = is_string($view)
                    ? str_replace([' class="current"', " class='current'", ' aria-current="page"', " aria-current='page'"], '', $view)
                    : $view;
            }
        }

        $views['elzo_unread'] = sprintf(
            '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
            esc_url($url),
            self::is_unread_filter_active() ? ' class="current" aria-current="page"' : '',
            esc_html__('Unread', 'elzo-forms'),
            $count
        );

        return $views;
    }

    /**
     * Narrow the submissions list to unread submissions.
     *
     * @param mixed $query Query being parsed.
     * @return void
     */
    public static function filter_unread_query($query): void {
        global $pagenow;

        if (!$query instanceof \WP_Query || $pagenow !== 'edit.php' || !$query->is_main_query()) {
            return;
        }

        if (!self::is_unread_filter_active()) {
            return;
        }

        if (sanitize_key(self::query_arg('post_type')) !== 'elzo_submission') {
            return;
        }

        $meta_query = isset($query->query_vars['meta_query']) && is_array($query->query_vars['meta_query'])
            ? $query->query_vars['meta_query']
            : [];

        $meta_query[] = [
            'key' => self::META_KEY,
            'compare' => 'EXISTS',
        ];

        $query->query_vars['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for admin list-table filtering by read state.
    }

    /**
     * Handle the row action.
     *
     * @return void
     */
    public static function handle_request(): void {
        $post_id = absint(self::query_arg('post'));

        if ($post_id <= 0) {
            wp_die(esc_html__('Security check failed', 'elzo-forms'));
        }

        check_admin_referer(self::ACTION . '_' . $post_id);

        if (get_post_type($post_id) !== 'elzo_submission' || !current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('You are not allowed to update this submission.', 'elzo-forms'));
        }

        $state = sanitize_key(self::query_arg('state'));

        if ($state === 'unread') {
            self::mark_unread($post_id);
        } else {
            self::mark_read($post_id);
        }

        $redirect_to = wp_get_referer();
        if (!is_string($redirect_to) || $redirect_to === '') {
            $redirect_to = admin_url('edit.php?post_type=elzo_submission');
        }

        wp_safe_redirect($redirect_to);
        exit;
    }

    /**
     * Announce the result of a bulk action.
     *
     * @return void
     */
    public static function render_notice(): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'edit-elzo_submission') {
            return;
        }

        $reported_count = self::query_arg(self::NOTICE_COUNT_ARG);
        if ($reported_count === '') {
            return;
        }

        $count = absint($reported_count);
        $state = sanitize_key(self::query_arg(self::NOTICE_STATE_ARG));

        $message = $state === 'unread'
            ? sprintf(
                /* translators: %s: number of submissions. */
                _n('%s submission marked as unread.', '%s submissions marked as unread.', $count, 'elzo-forms'),
                number_format_i18n($count)
            )
            : sprintf(
                /* translators: %s: number of submissions. */
                _n('%s submission marked as read.', '%s submissions marked as read.', $count, 'elzo-forms'),
                number_format_i18n($count)
            );

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html($message)
        );
    }

    /**
     * Keep the notice arguments out of the URL once the notice was shown.
     *
     * @param array $args Removable query arguments.
     * @return array
     */
    public static function removable_query_args($args): array {
        $args = is_array($args) ? $args : [];
        $args[] = self::NOTICE_COUNT_ARG;
        $args[] = self::NOTICE_STATE_ARG;

        return $args;
    }

    /**
     * Count unread submissions.
     *
     * @return int
     */
    private static function count_unread(): int {
        $query = new \WP_Query([
            'post_type' => 'elzo_submission',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to count unread submissions for the list view.
                [
                    'key' => self::META_KEY,
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        return (int) $query->found_posts;
    }

    /**
     * Whether the list is narrowed down to unread submissions.
     *
     * @return bool
     */
    private static function is_unread_filter_active(): bool {
        return sanitize_key(self::query_arg(self::FILTER_QUERY_ARG)) === '1';
    }

    /**
     * Read one admin query argument.
     *
     * The submissions list is reached by ordinary links, so its arguments carry
     * no nonce; callers normalize what they read, and the only state change
     * behind this class, the row action, verifies its own nonce.
     *
     * @param string $key Query argument name.
     * @return string
     */
    private static function query_arg(string $key): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only admin query access; the value is normalized by the caller.
        $value = isset($_GET[$key]) && is_scalar($_GET[$key]) ? wp_unslash($_GET[$key]) : '';

        return is_string($value) ? $value : '';
    }

    /**
     * Get the global value of the setting.
     *
     * @return string
     */
    private static function global_setting(): string {
        $global_settings = get_option('elzo_forms_form_settings', []);
        $value = is_array($global_settings) && isset($global_settings[self::SETTING]) && is_scalar($global_settings[self::SETTING])
            ? sanitize_key((string) $global_settings[self::SETTING])
            : '';

        if ($value === '') {
            $value = (string) \ElzoForms\Services\Settings::get_default_settings(self::SETTING);
        }

        return $value === 'no' ? 'no' : 'yes';
    }
}
