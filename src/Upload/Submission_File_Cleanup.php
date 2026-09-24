<?php
/**
 * Submission file cleanup.
 *
 * Deletes the permanent uploads a submission owns once that submission is
 * gone, and discards the ones a failed submission left behind.
 *
 * @package ElzoForms\Upload
 */

namespace ElzoForms\Upload;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Submission_File_Cleanup {

    /**
     * Post meta marking a submission whose file values are references only.
     *
     * A submission restored from an export keeps the file URLs it was stored
     * with as historical values, but it does not own the files behind them:
     * they may belong to another submission on this site or to another site.
     */
    public const FILES_NOT_OWNED_META = '_elzo_forms_files_not_owned';

    /**
     * Register cleanup handlers.
     */
    public static function init(): void {
        add_action('before_delete_post', [__CLASS__, 'handle_submission_deletion'], 10, 2);
    }

    /**
     * Delete the files of a submission that is being permanently deleted.
     *
     * Trashing a submission is reversible and keeps its files. This runs on
     * the permanent delete, which is where the files stop having an owner.
     *
     * @param int          $post_id Post being deleted.
     * @param \WP_Post|null $post   Post object, when WordPress passes one.
     */
    public static function handle_submission_deletion($post_id, $post = null): void {
        $post = $post instanceof \WP_Post ? $post : get_post((int) $post_id);
        if (!$post instanceof \WP_Post || $post->post_type !== 'elzo_submission') {
            return;
        }

        if (get_post_meta((int) $post->ID, self::FILES_NOT_OWNED_META, true)) {
            return;
        }

        $decoded = $post->post_content ? json_decode($post->post_content, true) : null;
        if (!is_array($decoded) || empty($decoded['fields']) || !is_array($decoded['fields'])) {
            return;
        }

        self::discard_files($decoded['fields']);
    }

    /**
     * Delete every permanent upload stored in a set of submission fields.
     *
     * @param array $fields Submission field entries.
     * @return int Number of deleted files.
     */
    public static function discard_files(array $fields): int {
        $file_urls = self::collect_file_urls($fields);

        return empty($file_urls) ? 0 : File_Upload_Handler::delete_submitted_files($file_urls);
    }

    /**
     * Collect the stored file URLs of a set of submission fields.
     *
     * Legacy submissions do not record the field type, so cleanup retains
     * its conservative URL candidate collection: a file field always stores a list of URL
     * strings. That shape alone is not proof, which is why the caller resolves
     * every URL against the permanent upload root before deleting anything.
     *
     * @param array $fields Submission field entries.
     * @return array Candidate file URLs.
     */
    public static function collect_file_urls(array $fields): array {
        $file_urls = [];

        foreach ($fields as $field) {
            $value = is_array($field) && isset($field['value']) ? $field['value'] : null;
            if (!is_array($value) || empty($value)) {
                continue;
            }

            $candidates = [];
            foreach ($value as $item) {
                if (!is_string($item) || $item === '') {
                    continue 2;
                }

                $candidates[] = $item;
            }

            $file_urls = array_merge($file_urls, $candidates);
        }

        return array_values(array_unique($file_urls));
    }
}
