<?php
/**
 * Form minimum version compatibility helpers.
 *
 * @package ElzoForms\Form\Compatibility
 */

namespace ElzoForms\Form\Compatibility;

defined('ABSPATH') || exit;

final class MinimumVersion {
    public const KEY = 'min_version';
    public const MAX_LENGTH = 32;

    private function __construct() {
    }

    /**
     * @param mixed $value
     */
    public static function normalize($value): string {
        if (!is_scalar($value)) {
            return '';
        }

        return substr(trim((string) $value), 0, self::MAX_LENGTH);
    }

    /**
     * @param mixed $value
     */
    public static function is_valid($value): bool {
        if (!is_scalar($value)) {
            return false;
        }

        $value = trim((string) $value);
        if ($value === '' || strlen($value) > self::MAX_LENGTH) {
            return false;
        }

        return (bool) preg_match('/^\d+(?:\.\d+){0,3}(?:-[0-9A-Za-z][0-9A-Za-z.-]*)?$/', $value);
    }

    public static function requires_newer_version(
        string $minimum,
        string $installed
    ): bool {
        if (!self::is_valid($minimum) || !self::is_valid($installed)) {
            return false;
        }

        return version_compare(self::normalize($installed), self::normalize($minimum), '<');
    }
}
