<?php
/**
 * Shared parser for variable reference templates.
 *
 * @package ElzoForms\Variables
 */

namespace ElzoForms\Variables;

defined('ABSPATH') || exit;

class ReferenceTemplateInspector {
    public const MAX_PATH_LENGTH = 2048;
    public const MAX_SEGMENTS = 64;
    public const MAX_REFERENCES = 100;

    public function inspect(string $value, string $path = 'settings'): ReferenceTemplateInspectionResult {
        $references = [];
        $errors = [];
        $length = strlen($value);
        $offset = 0;

        while ($offset < $length) {
            $start = strpos($value, '{{', $offset);
            if ($start === false) {
                break;
            }

            if ($start > 0 && $value[$start - 1] === '\\') {
                $offset = $start + 2;
                continue;
            }

            $end = strpos($value, '}}', $start + 2);
            if ($end === false) {
                $errors[] = $this->make_error($path, 'invalid_reference_syntax', __('The reference syntax is invalid.', 'elzo-forms'));
                break;
            }

            $raw_path = trim(substr($value, $start + 2, $end - ($start + 2)));
            if (!$this->is_valid_path($raw_path)) {
                $code = strlen($raw_path) > self::MAX_PATH_LENGTH ? 'reference_path_too_long' : 'invalid_reference_syntax';
                $errors[] = $this->make_error($path, $code, __('The reference syntax is invalid.', 'elzo-forms'), $raw_path);
            }

            $references[] = [
                'start' => $start,
                'length' => ($end + 2) - $start,
                'path' => $raw_path,
            ];

            $offset = $end + 2;
        }

        if (count($references) > self::MAX_REFERENCES) {
            $errors[] = $this->make_error($path, 'too_many_references', __('The value contains too many references.', 'elzo-forms'));
        }

        $exact = $this->is_exact_reference($value);

        return new ReferenceTemplateInspectionResult(
            $references,
            $errors,
            $exact,
            !empty($references) && !$exact
        );
    }

    public function contains_reference(string $value): bool {
        return (bool) preg_match('/(?<!\\\\)\{\{/', $value);
    }

    public function is_exact_reference(string $value): bool {
        return preg_match('/^\s*(?<!\\\\)\{\{\s*([^{}]+?)\s*\}\}\s*$/', $value) === 1;
    }

    public function is_valid_path(string $path): bool {
        if ($path === '' || strlen($path) > self::MAX_PATH_LENGTH) {
            return false;
        }

        if (strpos($path, '.') === 0 || substr($path, -1) === '.' || strpos($path, '..') !== false) {
            return false;
        }

        $segments = explode('.', $path);
        if (count($segments) > self::MAX_SEGMENTS) {
            return false;
        }

        foreach ($segments as $segment) {
            if ($segment === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $segment)) {
                return false;
            }
        }

        return true;
    }

    private function make_error(string $path, string $code, string $message, string $reference = ''): array {
        $error = [
            'path' => $path,
            'code' => sanitize_key($code),
            'message' => sanitize_text_field($message),
        ];

        if ($reference !== '') {
            $error['reference'] = $reference;
        }

        return $error;
    }
}
