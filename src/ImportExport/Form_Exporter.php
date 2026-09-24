<?php
/**
 * Form exporter.
 *
 * @package ElzoForms\ImportExport
 */

namespace ElzoForms\ImportExport;

use ElzoForms\Form\Form;

// Exit if accessed directly
defined('ABSPATH') || exit;

final class Form_Exporter {

    /** Form statuses that can be exported; Trash is never exported. */
    public const STATUSES = ['publish', 'draft', 'pending', 'private', 'future'];

    /**
     * Get the forms to export.
     *
     * @param int[] $form_ids Selected form IDs, or an empty list for every form.
     * @param string[] $statuses Statuses to include.
     * @return \WP_Post[]
     */
    public function get_forms(array $form_ids = [], array $statuses = self::STATUSES): array {
        $statuses = array_values(array_intersect(array_map('strval', $statuses), self::STATUSES));
        if (!$statuses) {
            return [];
        }

        $args = [
            'post_type' => 'elzo_form',
            'post_status' => $statuses,
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => true,
            'update_post_term_cache' => false,
        ];

        if ($form_ids) {
            $args['post__in'] = array_values(array_unique(array_filter(array_map('absint', $form_ids))));
            if (!$args['post__in']) {
                return [];
            }
        }

        return array_values(array_filter(get_posts($args), static function ($post): bool {
            return $post instanceof \WP_Post && $post->post_type === 'elzo_form' && $post->post_status !== 'trash';
        }));
    }

    /**
     * Build a forms package.
     *
     * @param \WP_Post[] $posts Forms to export.
     * @param bool $include_sensitive Whether to keep sensitive settings.
     * @return array
     */
    public function build_package(array $posts, bool $include_sensitive = false): array {
        $package = Package::header($include_sensitive);
        $package['forms'] = [];
        $used_refs = [];

        foreach ($posts as $post) {
            if ($post instanceof \WP_Post) {
                $package['forms'][] = $this->export_form($post, self::reference_for($post, $used_refs), $include_sensitive);
            }
        }

        return $package;
    }

    /**
     * Build the portable item of one form.
     *
     * The form data is read through the regular loader, so a form served from
     * a developer JSON source exports the definition that actually renders.
     * Only the core schema is exported; extensions add their own data through
     * the elzo_forms/import_export/export_form filter.
     *
     * @param \WP_Post $post Form post.
     * @param string $ref Package-local reference.
     * @param bool $include_sensitive Whether to keep sensitive settings.
     * @return array
     */
    public function export_form(\WP_Post $post, string $ref, bool $include_sensitive = false): array {
        $source = (new Form($post))->to_array();

        $data = [
            'steps' => [],
            'settings' => [],
            'texts' => [],
            'modules' => [],
        ];
        foreach (Form_Data_Sanitizer::CORE_KEYS as $key) {
            if (array_key_exists($key, $source)) {
                $data[$key] = $source[$key];
            }
        }

        $redacted = [];
        if (!$include_sensitive) {
            [$data, $redacted] = Sensitive_Settings::strip($data);
        }

        $item = [
            'ref' => $ref,
            'key' => self::form_key($post),
            'title' => (string) $post->post_title,
            'status' => (string) $post->post_status,
            'data' => $data,
        ];

        if ($redacted) {
            $item['redacted'] = $redacted;
        }

        /**
         * Filters the exported item of a form.
         *
         * Add extension data under $item['data'], read from $context['source'].
         * When $context['include_sensitive'] is false, leave secrets out and
         * list the removed paths in $item['redacted'].
         *
         * @filter elzo_forms/import_export/export_form
         * @param array $item Exported item: ref, key, title, status, data and optionally redacted.
         * @param array $context {
         *     @type \WP_Post $post              Form post.
         *     @type array    $source            Complete stored form data.
         *     @type bool     $include_sensitive Whether sensitive settings were requested.
         * }
         */
        $filtered = apply_filters('elzo_forms/import_export/export_form', $item, [
            'post' => $post,
            'source' => $source,
            'include_sensitive' => $include_sensitive,
        ]);

        return is_array($filtered) && isset($filtered['data']) && is_array($filtered['data']) ? $filtered : $item;
    }

    /**
     * Get the portable key of a form.
     *
     * @param \WP_Post $post Form post.
     * @return string
     */
    public static function form_key(\WP_Post $post): string {
        $key = is_scalar($post->post_name) ? (string) $post->post_name : '';

        return $key !== '' ? $key : sanitize_title((string) $post->post_title);
    }

    /**
     * Get a package-local reference for a form, unique within one package.
     *
     * @param \WP_Post $post Form post.
     * @param array<string, bool> $used_refs References already used, passed by reference.
     * @return string
     */
    public static function reference_for(\WP_Post $post, array &$used_refs): string {
        $base = self::form_key($post);
        if ($base === '') {
            $base = 'form';
        }

        $ref = $base;
        $suffix = 2;
        while (isset($used_refs[$ref])) {
            $ref = $base . '-' . $suffix;
            $suffix++;
        }

        $used_refs[$ref] = true;

        return $ref;
    }
}
