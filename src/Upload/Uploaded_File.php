<?php
/**
 * Small previews of trusted permanent uploads.
 *
 * @package ElzoForms\Upload
 */

namespace ElzoForms\Upload;

defined('ABSPATH') || exit;

class Uploaded_File {

    /** Preview name is tied to the original so cleanup needs no stored metadata. */
    private const PREVIEW_SUFFIX = '.elzo-preview-100.png';
    private const SQUARE_PREVIEW_SUFFIX = '.elzo-preview-100-square.png';

    /**
     * Create/cache a 100px raster preview using the WordPress image editor.
     *
     * @param string $url Untrusted upload URL.
     * @param bool $square Crop to a square, for email clients without object-fit.
     * @return string Preview URL, or empty if unavailable/not a local raster image.
     */
    public static function preview_url(string $url, bool $square = false): string {
        $file = File_Upload_Handler::resolve_permanent_file_url($url);
        if ($file === null || !is_readable($file['path'])) {
            return '';
        }

        $mime = wp_get_image_mime($file['path']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'], true)) {
            return '';
        }

        $suffix = $square ? self::SQUARE_PREVIEW_SUFFIX : self::PREVIEW_SUFFIX;
        $preview_path = $file['path'] . $suffix;
        $preview_url = $file['url'] . $suffix;
        // Never follow a pre-existing link when reading or writing the cache.
        if (is_link($preview_path)) {
            return '';
        }
        $cached = File_Upload_Handler::resolve_permanent_file_url($preview_url);
        if ($cached !== null && filemtime($preview_path) >= filemtime($file['path'])) {
            $size = wp_getimagesize($preview_path);
            if ($size && $size[0] <= 100 && $size[1] <= 100 && (!$square || $size[0] === $size[1]) && ($size['mime'] ?? '') === 'image/png') {
                return $preview_url;
            }
        }

        $editor = wp_get_image_editor($file['path'], ['mime_type' => $mime]);
        if (is_wp_error($editor)) {
            return '';
        }
        $size = $editor->get_size();
        // Crop small images to their shortest side, without upscaling.
        $edge = $square ? min(100, $size['width'], $size['height']) : 100;
        $needs_resize = $square
            ? $size['width'] !== $edge || $size['height'] !== $edge
            : $size['width'] > 100 || $size['height'] > 100;
        if ($needs_resize && is_wp_error($editor->resize($edge, $edge, $square))) {
            return '';
        }
        $saved = $editor->save($preview_path, 'image/png');

        return !is_wp_error($saved) && File_Upload_Handler::resolve_permanent_file_url($preview_url) !== null ? $preview_url : '';
    }

    /**
     * Remove derived previews with their original permanent upload.
     *
     * @param array $file Trusted reference from resolve_permanent_file_url().
     */
    public static function delete_preview(array $file): void {
        foreach ([self::PREVIEW_SUFFIX, self::SQUARE_PREVIEW_SUFFIX] as $suffix) {
            $preview = File_Upload_Handler::resolve_permanent_file_url($file['url'] . $suffix);
            if ($preview !== null) {
                wp_delete_file($preview['path']);
            }
        }
    }
}
