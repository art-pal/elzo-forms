<?php
/**
 * Admin AJAX Handler class.
 *
 * Handles AJAX requests from the admin area.
 *
 * @package ElzoForms\Admin
 */

namespace ElzoForms\Admin;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Ajax_Handler {

    /**
     * Largest number of results a single search request may return.
     */
    private const CONTENT_SEARCH_MAX_LIMIT = 20;

    /**
     * Largest number of stored IDs a picker may resolve labels for at once.
     */
    private const CONTENT_SEARCH_MAX_INCLUDE = 50;

    /**
     * Register AJAX handlers.
     */
    public static function init(): void {
        add_action('wp_ajax_elzo_forms', [__CLASS__, 'handle_field_settings_request']);
        add_action('wp_ajax_elzo_forms_content_search', [__CLASS__, 'handle_content_search_request']);
    }

    /**
     * Handle content search AJAX request.
     *
     * Backs the searchable pickers used wherever a condition points at a page or
     * post instead of asking for a hand-typed ID.
     */
    public static function handle_content_search_request(): void {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error([
                'code' => 'unauthorized',
                'message' => __('You are not allowed to search content.', 'elzo-forms'),
            ], 403);
        }

        if (!check_ajax_referer('elzo_forms_admin', 'nonce', false)) {
            wp_send_json_error([
                'code' => 'invalid_nonce',
                'message' => __('Invalid nonce', 'elzo-forms'),
            ], 403);
        }

        $search = isset($_POST['search']) && is_scalar($_POST['search'])
            ? sanitize_text_field(wp_unslash((string) $_POST['search']))
            : '';
        $limit = isset($_POST['limit']) && is_scalar($_POST['limit'])
            ? absint(wp_unslash((string) $_POST['limit']))
            : self::CONTENT_SEARCH_MAX_LIMIT;
        $page = isset($_POST['page']) && is_scalar($_POST['page'])
            ? max(1, absint(wp_unslash((string) $_POST['page'])))
            : 1;
        $include = isset($_POST['include']) && is_scalar($_POST['include'])
            ? explode(',', sanitize_text_field(wp_unslash((string) $_POST['include'])))
            : [];
        $post_types = isset($_POST['post_types']) && is_scalar($_POST['post_types'])
            ? explode(',', sanitize_text_field(wp_unslash((string) $_POST['post_types'])))
            : [];

        wp_send_json_success(self::search_content_page($search, $include, $post_types, $limit, $page));
    }

    /**
     * Search public content and non-public content the current user may edit.
     *
     * Passing IDs in $include resolves those exact records instead of running a
     * search, which is how a picker turns stored IDs back into readable labels.
     *
     * @param string $search Free text matched against the title.
     * @param array<int, mixed> $include Specific post IDs to resolve.
     * @param array<int, mixed> $post_types Post types to search within.
     * @param int $limit Maximum number of results.
     * @return array<int, array<string, mixed>>
     */
    public static function search_content(string $search, array $include, array $post_types, int $limit): array {
        return self::search_content_page($search, $include, $post_types, $limit, 1)['items'];
    }

    /**
     * Search one page of content and report whether another page is available.
     *
     * Empty searches are ordered newest-first so focusing an untouched picker
     * presents useful recent content. Title searches stay alphabetical. One
     * extra record is requested to determine whether the picker can load more.
     *
     * @param string $search Free text matched against the title.
     * @param array<int, mixed> $include Specific post IDs to resolve.
     * @param array<int, mixed> $post_types Post types to search within.
     * @param int $limit Maximum number of results.
     * @param int $page One-based result page.
     * @return array{items: array<int, array<string, mixed>>, has_more: bool}
     */
    public static function search_content_page(string $search, array $include, array $post_types, int $limit, int $page): array {
        $allowed_post_types = \ElzoForms\Utilities\Admin::get_content_post_types();

        $requested_post_types = array_filter(array_map(function ($post_type) {
            return is_scalar($post_type) ? sanitize_key((string) $post_type) : '';
        }, $post_types));
        $requested_post_types = array_values(array_intersect($requested_post_types, $allowed_post_types));

        $include = array_values(array_filter(array_map('absint', array_filter($include, 'is_scalar'))));
        $include = array_slice(array_unique($include), 0, self::CONTENT_SEARCH_MAX_INCLUDE);
        $limit = max(1, min(self::CONTENT_SEARCH_MAX_LIMIT, $limit));
        $page = max(1, $page);
        $is_browsing_recent = !$include && $search === '';

        $query_args = [
            'post_type' => $requested_post_types ? $requested_post_types : $allowed_post_types,
            'post_status' => ['publish', 'private', 'draft', 'pending', 'future'],
            'posts_per_page' => $include ? count($include) : $limit + 1,
            'orderby' => $is_browsing_recent ? 'date' : 'title',
            'order' => $is_browsing_recent ? 'DESC' : 'ASC',
            'fields' => 'ids',
            'suppress_filters' => false,
        ];

        if ($include) {
            $query_args['post__in'] = $include;
        } elseif ($search !== '') {
            $query_args['s'] = $search;
        }

        if (!$include) {
            // Use the requested page size rather than the limit+1 probe size so
            // the extra record becomes the first item on the following page.
            $query_args['offset'] = ($page - 1) * $limit;
        }

        /**
         * Filters the query behind admin content pickers.
         *
         * Developers can use ordinary WP_Query arguments such as post__not_in,
         * meta_query, or tax_query to remove content that should not be offered.
         * Use elzo_forms_content_post_types to control the available post types.
         * Non-public results still pass the per-post edit capability check below.
         *
         * @filter elzo_forms_content_search_query_args
         * @param array<string, mixed> $query_args WP_Query arguments.
         * @param array<string, mixed> $context Search request context.
         */
        $filtered_query_args = apply_filters('elzo_forms_content_search_query_args', $query_args, [
            'search' => $search,
            'include' => $include,
            'post_types' => $requested_post_types ? $requested_post_types : $allowed_post_types,
            'limit' => $limit,
            'page' => $page,
        ]);
        if (is_array($filtered_query_args)) {
            $query_args = $filtered_query_args;
        }

        $items = [];
        $post_ids = (array) get_posts($query_args);
        $has_more = !$include && count($post_ids) > $limit;
        $visible_post_ids = $include ? $post_ids : array_slice($post_ids, 0, $limit);

        foreach ($visible_post_ids as $post_id) {
            $post_id = absint($post_id);
            if ($post_id <= 0 || !self::can_select_content($post_id)) {
                continue;
            }

            $title = get_the_title($post_id);
            if (!is_string($title) || $title === '') {
                /* translators: %d: Post ID. */
                $title = sprintf(__('#%d (no title)', 'elzo-forms'), $post_id);
            }

            $post_type = sanitize_key((string) get_post_type($post_id));
            $post_type_object = get_post_type_object($post_type);
            $post_type_label = isset($post_type_object->labels->singular_name) && is_scalar($post_type_object->labels->singular_name)
                ? sanitize_text_field((string) $post_type_object->labels->singular_name)
                : $post_type;

            $items[] = [
                'id' => $post_id,
                'title' => sanitize_text_field($title),
                'type' => $post_type,
                'type_label' => $post_type_label,
            ];
        }

        return [
            'items' => $items,
            'has_more' => $has_more,
        ];
    }

    /**
     * Whether content may be exposed in an admin picker.
     *
     * Anyone allowed into the form editor may reference published content from
     * a public post type. Drafts, private posts, and other non-public records
     * still require permission to edit that specific post.
     */
    private static function can_select_content(int $post_id): bool {
        $post = get_post($post_id);

        if ($post instanceof \WP_Post && $post->post_status === 'publish') {
            $post_type_object = get_post_type_object((string) $post->post_type);
            if ($post_type_object && !empty($post_type_object->public)) {
                return true;
            }
        }

        return current_user_can('edit_post', $post_id);
    }

    /**
     * Handle field settings AJAX request.
     *
     * Returns HTML for field-specific settings when field type changes in admin.
     */
    public static function handle_field_settings_request(): void {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'code' => 'unauthorized',
                'message' => __('Unauthorized', 'elzo-forms'),
            ], 403);
        }

        // Keep the existing JSON error contract while using WordPress's AJAX nonce verifier.
        if (!check_ajax_referer('elzo_forms_admin', 'nonce', false)) {
            wp_send_json_error([
                'code' => 'invalid_nonce',
                'message' => __('Invalid nonce', 'elzo-forms'),
            ], 403);
        }

        $posted_type = isset($_POST['field_type']) && is_scalar($_POST['field_type'])
            ? sanitize_text_field(wp_unslash((string) $_POST['field_type']))
            : '';

        // Validate field type
        if ($posted_type === '') {
            wp_send_json_error([
                'message' => __('Empty field type', 'elzo-forms'),
            ]);
        }

        // The complete type arrives in one request ("text:email"), so the
        // field is rebuilt once. Only registered types and variants are
        // rendered: the subtype part is never used without that check.
        if (!\ElzoForms\Field\Field_Type::is_registered($posted_type)) {
            wp_send_json_error([
                'message' => __('Unknown field type', 'elzo-forms'),
            ]);
        }

        $field_type = \ElzoForms\Field\Field_Type::normalize($posted_type);

        $step_index = isset($_POST['step_index']) && is_scalar($_POST['step_index'])
            ? absint(wp_unslash((string) $_POST['step_index']))
            : 0;
        $field_index = isset($_POST['field_index']) && is_scalar($_POST['field_index'])
            ? absint(wp_unslash((string) $_POST['field_index']))
            : 0;
        $posted_field_id = isset($_POST['field_id']) && is_scalar($_POST['field_id'])
            ? sanitize_text_field(wp_unslash((string) $_POST['field_id']))
            : '';

        /*
         * Field IDs are only ever echoed into markup, and forms created outside
         * the admin UI may use readable string IDs, so keep the posted ID when it
         * is a plain machine name. The re-rendered settings markup has to keep
         * using the same ID as the field already in the DOM, otherwise its input
         * IDs, labels and conditional-logic references stop matching the field.
         */
        $field_id = preg_match('/^[A-Za-z0-9_-]{1,64}$/', $posted_field_id) === 1
            ? $posted_field_id
            : \ElzoForms\Field\Field::generate_id();

        // Create field object
        $field_data = [
            'type' => $field_type,
            'id' => $field_id,
            'step_index' => $step_index,
            'index' => $field_index,
        ];

        try {
            $field_object = \ElzoForms\Field\Field::from($field_data);

            // Generate HTML for each settings tab
            $response = [
                'message' => __('Field settings loaded', 'elzo-forms'),
                'field' => $field_object->get_admin_field_data(),
                'html' => [
                    'general' => self::get_settings_html($field_object, 'general'),
                    'view' => self::get_settings_html($field_object, 'view'),
                ],
            ];

        } catch (\Exception $e) {
            /* translators: %s: Error message. */
            $message = sprintf(__('Error loading field settings: %s', 'elzo-forms'), $e->getMessage());

            wp_send_json_error([
                'message' => $message,
            ]);
        }

        wp_send_json_success($response);
    }

    /**
     * Get field settings HTML for a specific tab.
     *
     * @param \ElzoForms\Field\Field $field_object Field instance
     * @param string $tab Settings tab name
     * @return string HTML output
     */
    protected static function get_settings_html($field_object, string $tab): string {
        ob_start();
        $field_object->render_field_settings($tab);
        return ob_get_clean();
    }

}
