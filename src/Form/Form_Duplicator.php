<?php
/**
 * Form duplication.
 *
 * A copy carries the complete definition of the form as it renders, a form
 * served from a developer JSON source included, and becomes an ordinary
 * editable draft with its own key. Submissions, revisions and per-user editor
 * state stay with the original.
 *
 * @package ElzoForms\Form
 */

namespace ElzoForms\Form;

// Exit if accessed directly
defined('ABSPATH') || exit;

final class Form_Duplicator {

    /** admin-post.php action of the Duplicate links. */
    public const ACTION = 'elzo_forms_duplicate_form';

    /** Query argument that carries the ID of a new copy to its notice. */
    public const NOTICE_QUERY_ARG = 'elzo_forms_duplicated';

    /** @var bool */
    private static $initialized = false;

    /**
     * Register admin hooks.
     *
     * @return void
     */
    public static function init(): void {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        add_action('admin_post_' . self::ACTION, [__CLASS__, 'handle_request']);
        add_filter('post_row_actions', [__CLASS__, 'add_row_action'], 10, 2);
        add_action('post_submitbox_start', [__CLASS__, 'render_submitbox_action']);
        add_action('admin_notices', [__CLASS__, 'render_notice']);
        add_filter('removable_query_args', [__CLASS__, 'removable_query_args']);
    }

    /**
     * Whether the current user may duplicate a form.
     *
     * Duplicating reads the form and creates a new one, so it takes both
     * permissions.
     *
     * @param mixed $post Form post.
     * @return bool
     */
    public static function can_duplicate($post): bool {
        if (!$post instanceof \WP_Post || $post->post_type !== 'elzo_form' || in_array($post->post_status, ['trash', 'auto-draft'], true)) {
            return false;
        }

        $post_type = get_post_type_object('elzo_form');
        $create_capability = $post_type && isset($post_type->cap->create_posts) ? (string) $post_type->cap->create_posts : 'edit_posts';

        return current_user_can('edit_post', $post->ID) && current_user_can($create_capability);
    }

    /**
     * Get the URL that duplicates a form.
     *
     * @param \WP_Post $post Form post.
     * @param bool $open_copy Whether to open the copy in the editor instead of returning to the forms list.
     * @return string
     */
    public static function url(\WP_Post $post, bool $open_copy = false): string {
        $args = [
            'action' => self::ACTION,
            'post' => (int) $post->ID,
        ];
        if ($open_copy) {
            $args['open'] = '1';
        }
        $args['_wpnonce'] = wp_create_nonce(self::ACTION . '_' . (int) $post->ID);

        return add_query_arg($args, admin_url('admin-post.php'));
    }

    /**
     * Create a draft copy of a form.
     *
     * @param \WP_Post $source Form to copy.
     * @return int|\WP_Error ID of the copy.
     */
    public function duplicate(\WP_Post $source) {
        if ($source->post_type !== 'elzo_form') {
            return new \WP_Error('elzo_forms_duplicate_invalid', __('Only forms can be duplicated.', 'elzo-forms'));
        }

        $data = (new Form($source))->to_array();

        /**
         * Filters the data of a form copy before it is stored.
         *
         * @filter elzo_forms/duplicate_form_data
         * @param array    $data   Complete form data of the original, as it renders.
         * @param \WP_Post $source Form being copied.
         */
        $filtered = apply_filters('elzo_forms/duplicate_form_data', $data, $source);
        if (is_array($filtered)) {
            $data = $filtered;
        }

        $content = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (!is_string($content)) {
            return new \WP_Error('elzo_forms_duplicate_encode', __('The form data could not be encoded.', 'elzo-forms'));
        }

        $title = (string) $source->post_title;
        $key = is_scalar($source->post_name) && (string) $source->post_name !== '' ? (string) $source->post_name : sanitize_title($title);

        $post_id = wp_insert_post([
            'post_type' => 'elzo_form',
            'post_title' => wp_slash(sprintf(
                /* translators: %s: Title of the form that was copied. */
                __('%s (Copy)', 'elzo-forms'),
                $title !== '' ? $title : __('(no title)', 'elzo-forms')
            )),
            'post_name' => $key !== '' ? self::unique_key($key) : '',
            // A copy stays out of sight until it is reviewed and published.
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
            'post_content' => wp_slash($content),
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }
        if (!$post_id) {
            return new \WP_Error('elzo_forms_duplicate_failed', __('The form could not be saved.', 'elzo-forms'));
        }

        /**
         * Fires after a form has been duplicated.
         *
         * @action elzo_forms/form_duplicated
         * @param int      $post_id ID of the copy.
         * @param \WP_Post $source  Form that was copied.
         */
        do_action('elzo_forms/form_duplicated', (int) $post_id, $source);

        return (int) $post_id;
    }

    /**
     * Get a key no other form uses.
     *
     * WordPress leaves the slug of drafts alone, so the key is made unique as
     * if the form were published.
     *
     * @param string $key Key of the original.
     * @return string
     */
    private static function unique_key(string $key): string {
        return wp_unique_post_slug($key, 0, 'publish', 'elzo_form', 0);
    }

    /**
     * Duplicate the requested form and return to the list or open the copy.
     *
     * @return void
     */
    public static function handle_request(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The ID names the nonce action verified immediately below.
        $post_id = isset($_GET['post']) && is_scalar($_GET['post']) ? absint(wp_unslash((string) $_GET['post'])) : 0;
        check_admin_referer(self::ACTION . '_' . $post_id);

        $source = $post_id > 0 ? get_post($post_id) : null;
        if (!self::can_duplicate($source)) {
            wp_die(esc_html__('You are not allowed to duplicate this form.', 'elzo-forms'), '', ['response' => 403]);
        }

        $copy_id = (new self())->duplicate($source);
        if (is_wp_error($copy_id)) {
            wp_die(esc_html($copy_id->get_error_message()), '', ['response' => 500, 'back_link' => true]);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
        $open_copy = isset($_GET['open']) && $_GET['open'] === '1';
        $target = $open_copy
            ? admin_url('post.php?post=' . $copy_id . '&action=edit')
            : admin_url('edit.php?post_type=elzo_form');

        wp_safe_redirect(add_query_arg(self::NOTICE_QUERY_ARG, $copy_id, $target));
        exit;
    }

    /**
     * Add a Duplicate row action to forms, before Trash.
     *
     * @param array $actions Row actions.
     * @param \WP_Post $post Post.
     * @return array
     */
    public static function add_row_action($actions, $post) {
        if (!is_array($actions) || !$post instanceof \WP_Post || $post->post_type !== 'elzo_form' || !self::can_duplicate($post)) {
            return $actions;
        }

        $link = sprintf(
            '<a href="%s" aria-label="%s">%s</a>',
            esc_url(self::url($post)),
            /* translators: %s: Form title. */
            esc_attr(sprintf(__('Duplicate “%s”', 'elzo-forms'), self::title($post))),
            esc_html__('Duplicate', 'elzo-forms')
        );

        $position = array_search('trash', array_keys($actions), true);
        if ($position === false) {
            $actions['elzo_forms_duplicate'] = $link;
            return $actions;
        }

        return array_slice($actions, 0, $position, true)
            + ['elzo_forms_duplicate' => $link]
            + array_slice($actions, $position, null, true);
    }

    /**
     * Add a Duplicate link to the Publish box of the form editor.
     *
     * @param \WP_Post|null $post Post being edited.
     * @return void
     */
    public static function render_submitbox_action($post = null): void {
        if (!$post instanceof \WP_Post || $post->post_type !== 'elzo_form' || !self::can_duplicate($post)) {
            return;
        }

        printf(
            '<div class="elzo-forms-duplicate-action"><a href="%s" title="%s">%s</a></div>',
            esc_url(self::url($post, true)),
            esc_attr__('Create a draft copy of the saved form and open it', 'elzo-forms'),
            esc_html__('Duplicate', 'elzo-forms')
        );
    }

    /**
     * Confirm a new copy on the forms list or in the editor of the copy.
     *
     * @return void
     */
    public static function render_notice(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice state; the copy is checked below.
        $copy_id = isset($_GET[self::NOTICE_QUERY_ARG]) && is_scalar($_GET[self::NOTICE_QUERY_ARG]) ? absint(wp_unslash((string) $_GET[self::NOTICE_QUERY_ARG])) : 0;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($copy_id <= 0 || !$screen || $screen->post_type !== 'elzo_form') {
            return;
        }

        $copy = get_post($copy_id);
        if (!$copy instanceof \WP_Post || $copy->post_type !== 'elzo_form' || !current_user_can('edit_post', $copy->ID)) {
            return;
        }

        if ($screen->base === 'post') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
            $edited_id = isset($_GET['post']) && is_scalar($_GET['post']) ? absint(wp_unslash((string) $_GET['post'])) : 0;
            if ($edited_id !== $copy_id) {
                return;
            }
            $message = esc_html__('Form duplicated. You are editing the copy, which is saved as a draft.', 'elzo-forms');
        } elseif ($screen->base === 'edit') {
            $message = sprintf(
                /* translators: %s: Linked title of the form copy. */
                esc_html__('Form duplicated. The copy %s is saved as a draft.', 'elzo-forms'),
                sprintf('<a href="%s">%s</a>', esc_url((string) get_edit_post_link($copy->ID)), esc_html(self::title($copy)))
            );
        } else {
            return;
        }

        printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', $message); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above.
    }

    /**
     * Drop the notice argument from the address once the notice is shown.
     *
     * @param string[] $args Removable query arguments.
     * @return string[]
     */
    public static function removable_query_args($args) {
        if (is_array($args)) {
            $args[] = self::NOTICE_QUERY_ARG;
        }

        return $args;
    }

    /**
     * Get the display title of a form.
     *
     * @param \WP_Post $post Form post.
     * @return string
     */
    private static function title(\WP_Post $post): string {
        $title = (string) $post->post_title;

        return $title !== '' ? $title : __('(no title)', 'elzo-forms');
    }
}
