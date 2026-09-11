<?php
/**
 * General utility helpers.
 *
 * @package ElzoForms\Utilities
 */

namespace ElzoForms\Utilities;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Helpers {
    protected const FIELD_KEY_MAX_LENGTH = 64;

    /** @var \Transliterator|false|null */
    protected static $icu_transliterator = false;

    /**
     * Encode line breaks to custom marker.
     */
    public static function encode_line_breaks(string $content): string {
        return str_replace(["\r\n", "\r", "\n"], '<apfbr/>', $content);
    }

    /**
     * Decode custom line break markers to PHP_EOL.
     */
    public static function decode_line_breaks(string $content): string {
        return str_replace('<apfbr/>', PHP_EOL, $content);
    }

    /**
     * Build a slug-like key suitable for field_key values.
     */
    public static function slugify_field_key(string $value, string $fallback = 'field'): string {
        $value = trim($value);
        $original_value = $value;
        $fallback = (string) preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($fallback)));
        $fallback = trim($fallback, '-');
        if ($fallback === '') {
            $fallback = 'field';
        }

        if ($value === '') {
            return $fallback;
        }

        // Fast path for Cyrillic and close alphabets before generic transliteration.
        $value = strtr($value, self::get_transliteration_map());
        $value = self::transliterate_to_ascii($value);

        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        }

        if (function_exists('iconv')) {
            $iconv_value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($iconv_value) && $iconv_value !== '') {
                $value = $iconv_value;
            }
        }

        $value = strtolower($value);
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');

        if ($value !== '') {
            return self::truncate_field_key($value, self::FIELD_KEY_MAX_LENGTH);
        }

        return self::build_non_ascii_fallback_slug($fallback, $original_value);
    }

    /**
     * Ensure key uniqueness using -N suffixes (-1, -2, ...).
     *
     * @param array<string,bool> $used_keys
     */
    public static function unique_field_key(string $base_key, array &$used_keys): string {
        $base_key = self::truncate_field_key(self::slugify_field_key($base_key), self::FIELD_KEY_MAX_LENGTH);
        $candidate = $base_key;
        $suffix = 1;

        while (isset($used_keys[$candidate])) {
            $suffix_part = '-' . $suffix;
            $available_base_length = max(1, self::FIELD_KEY_MAX_LENGTH - strlen($suffix_part));
            $candidate_base = self::truncate_field_key($base_key, $available_base_length);
            $candidate = $candidate_base . $suffix_part;
            ++$suffix;
        }

        $used_keys[$candidate] = true;

        return $candidate;
    }

    /**
     * Transliteration map used as a fast path when ICU transliteration is unavailable.
     *
     * @return array<string,string>
     */
    protected static function get_transliteration_map(): array {
        return [
            'А' => 'A', 'а' => 'a', 'Б' => 'B', 'б' => 'b', 'В' => 'V', 'в' => 'v',
            'Г' => 'G', 'г' => 'g', 'Ґ' => 'G', 'ґ' => 'g', 'Д' => 'D', 'д' => 'd',
            'Е' => 'E', 'е' => 'e', 'Є' => 'Ye', 'є' => 'ie', 'Ё' => 'E', 'ё' => 'e',
            'Ж' => 'Zh', 'ж' => 'zh', 'З' => 'Z', 'з' => 'z', 'И' => 'I', 'и' => 'i',
            'І' => 'I', 'і' => 'i', 'Ї' => 'Yi', 'ї' => 'i', 'Й' => 'Y', 'й' => 'i',
            'К' => 'K', 'к' => 'k', 'Л' => 'L', 'л' => 'l', 'М' => 'M', 'м' => 'm',
            'Н' => 'N', 'н' => 'n', 'О' => 'O', 'о' => 'o', 'П' => 'P', 'п' => 'p',
            'Р' => 'R', 'р' => 'r', 'С' => 'S', 'с' => 's', 'Т' => 'T', 'т' => 't',
            'У' => 'U', 'у' => 'u', 'Ф' => 'F', 'ф' => 'f', 'Х' => 'Kh', 'х' => 'kh',
            'Ц' => 'Ts', 'ц' => 'ts', 'Ч' => 'Ch', 'ч' => 'ch', 'Ш' => 'Sh', 'ш' => 'sh',
            'Щ' => 'Shch', 'щ' => 'shch', 'Ъ' => '', 'ъ' => '', 'Ы' => 'Y', 'ы' => 'y',
            'Ь' => '', 'ь' => '', 'Э' => 'E', 'э' => 'e', 'Ю' => 'Yu', 'ю' => 'yu',
            'Я' => 'Ya', 'я' => 'ya',
        ];
    }

    protected static function transliterate_to_ascii(string $value): string {
        if (self::$icu_transliterator === false) {
            if (class_exists('\Transliterator')) {
                $created = \Transliterator::create('Any-Latin; Latin-ASCII;');
                self::$icu_transliterator = $created instanceof \Transliterator ? $created : null;
            } else {
                self::$icu_transliterator = null;
            }
        }

        if (self::$icu_transliterator instanceof \Transliterator) {
            $transliterated = self::$icu_transliterator->transliterate($value);
            if (is_string($transliterated) && $transliterated !== '') {
                return $transliterated;
            }
        }

        return $value;
    }

    protected static function build_non_ascii_fallback_slug(string $fallback, string $original_value): string {
        if (!preg_match('/[^\x00-\x7F]/', $original_value)) {
            return $fallback;
        }

        $hex = strtolower(bin2hex($original_value));
        if ($hex === '') {
            return $fallback;
        }

        $value = $fallback . '-' . substr($hex, 0, 24);

        return self::truncate_field_key($value, self::FIELD_KEY_MAX_LENGTH);
    }

    protected static function truncate_field_key(string $value, int $max_length): string {
        if ($max_length < 1) {
            return 'field';
        }

        if (strlen($value) <= $max_length) {
            return $value;
        }

        $value = substr($value, 0, $max_length);
        $value = rtrim($value, '-');

        return $value !== '' ? $value : 'field';
    }

    /**
     * Get available file upload styles.
     */
    public static function get_field_file_styles(?string $key = null) {
        $styles = [
            'default' => __('Default', 'elzo-forms'),
            'drag-and-drop' => __('Drag and drop', 'elzo-forms'),
        ];

        return $key && isset($styles[$key]) ? $styles[$key] : $styles;
    }

    /**
     * Get available range field types.
     */
    public static function get_field_range_types(?string $type = null) {
        $types = [
            'range_2' => __('Two handles range', 'elzo-forms'),
            'range_1' => __('One handle range', 'elzo-forms'),
            'point' => __('Single point', 'elzo-forms'),
        ];

        return $type && !empty($types[$type]) ? $types[$type] : $types;
    }

    /**
     * Get responsive keypoints.
     */
    public static function get_responsive_keypoints(?string $key = null) {
        $keypoints = [
            'xs' => __('Extra small (xs)', 'elzo-forms'),
            'sm' => __('Small (sm)', 'elzo-forms'),
            'md' => __('Medium (md)', 'elzo-forms'),
            'lg' => __('Large (lg)', 'elzo-forms'),
            'xl' => __('Extra large (xl)', 'elzo-forms'),
            'xxl' => __('Extra extra large (xxl)', 'elzo-forms'),
        ];

        return $key && isset($keypoints[$key]) ? $keypoints[$key] : $keypoints;
    }

    /**
     * Get frontend JavaScript texts.
     */
    public static function get_frontend_texts(): array {
        return [
            'loading' => __('Loading', 'elzo-forms'),
            'notice' => __('Notice', 'elzo-forms'),
            'submitting' => __('Submitting', 'elzo-forms'),
            'errorOccurred' => __('An error occurred', 'elzo-forms'),
            'tryAgain' => __('Please try again', 'elzo-forms'),
            'fillInRequiredFields' => __('Please fill in all required fields', 'elzo-forms'),
            /* translators: %s: Maximum allowed file count. */
            'maximumFilesReached' => __('You can not upload more than %s files', 'elzo-forms'),
            /* translators: %s: Maximum allowed file size in megabytes. */
            'fileSizeExceeded' => __('You can not upload files larger than %s MB', 'elzo-forms'),
            /* translators: %s: Name of the file being uploaded. */
            'uploadProgress' => __('Upload progress: %s', 'elzo-forms'),
        ];
    }

    /**
     * Get column width class from width array.
     *
     * @param array|null $width Width configuration array
     * @return string CSS class string
     */
    public static function get_column_width_class($width = null): string {
        // Return empty string if width is not set
        if (empty($width) || !is_array($width)) {
            return '';
        }

        // Filter array to remove empty values
        $width = array_filter($width);

        // Return empty string if width is still empty after filtering
        if (empty($width)) {
            return '';
        }

        // Initialize an empty array for width classes
        $width_classes = [];

        // Loop through the width array and convert to class format
        foreach ($width as $keypoint => $label) {
            $column_size_array = array_map('intval', explode('/', $label));
            $column_size = round(12 * ($column_size_array[0] / $column_size_array[1]));
            $width_classes[] = 'elzo-forms-column-' . ($keypoint == 'xs' ? $column_size : $keypoint . '-' . $column_size);
        }

        return implode(' ', $width_classes);
    }
}
