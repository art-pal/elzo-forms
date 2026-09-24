<?php
/**
 * Typed, single-pass template resolver shared by all destinations.
 *
 * @package ElzoForms\Variables
 */

namespace ElzoForms\Variables;

defined('ABSPATH') || exit;

class ValueResolver {
    private const MAX_PATH_LENGTH = 2048;
    private const MAX_REFERENCES = 100;
    private const MAX_DEPTH = 20;

    /** @var ReferenceTemplateInspector */
    private $template_inspector;

    public function __construct(?ReferenceTemplateInspector $template_inspector = null) {
        $this->template_inspector = $template_inspector ?: new ReferenceTemplateInspector();
    }

    /**
     * @return mixed
     */
    public function get_path(array $state, string $path, $default = null) {
        $data = $state;

        if (!$this->is_valid_path($path)) {
            return $default;
        }

        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (!is_array($current)) {
                return $default;
            }

            $key = array_key_exists($segment, $current) ? $segment : (ctype_digit($segment) ? (int) $segment : $segment);
            if (!array_key_exists($key, $current)) {
                return $default;
            }

            $current = $current[$key];
        }

        return $current;
    }

    public function has_path(array $state, string $path): bool {
        $data = $state;

        if (!$this->is_valid_path($path)) {
            return false;
        }

        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (!is_array($current)) {
                return false;
            }

            $key = array_key_exists($segment, $current) ? $segment : (ctype_digit($segment) ? (int) $segment : $segment);
            if (!array_key_exists($key, $current)) {
                return false;
            }

            $current = $current[$key];
        }

        return true;
    }

    /**
     * @param mixed $value
     */
    public function resolve($value, array $state): ValueResolutionResult {
        $errors = [];
        $resolved = $this->resolve_value($value, $state, 'settings', 0, $errors);

        return new ValueResolutionResult($resolved, $errors);
    }

    public function contains_reference(string $value): bool {
        return $this->template_inspector->contains_reference($value);
    }

    public function is_exact_reference(string $value): bool {
        return $this->template_inspector->is_exact_reference($value);
    }

    public function validate_reference_template(string $value, string $path = 'settings'): ValueResolutionResult {
        $errors = $this->template_inspector->inspect($value, $path)->get_syntax_errors();

        return new ValueResolutionResult($value, $errors);
    }

    public function is_valid_path(string $path): bool {
        return $this->template_inspector->is_valid_path($path);
    }

    /**
     * @param mixed                            $value
     * @param array<int, array<string, mixed>> $errors
     *
     * @return mixed
     */
    private function resolve_value($value, array $state, string $path, int $depth, array &$errors) {
        if ($depth > self::MAX_DEPTH) {
            $errors[] = $this->make_error($path, 'reference_depth_exceeded', __('The referenced value is too deeply nested.', 'elzo-forms'));
            return null;
        }

        if (is_array($value)) {
            $resolved = [];
            foreach ($value as $key => $item) {
                $resolved[$key] = $this->resolve_value($item, $state, $path . '.' . $this->safe_path_segment((string) $key), $depth + 1, $errors);
            }

            return $resolved;
        }

        if (!is_string($value)) {
            return $value;
        }

        return $this->resolve_string($value, $state, $path, $errors);
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     *
     * @return mixed
     */
    private function resolve_string(string $value, array $state, string $path, array &$errors) {
        if (!$this->contains_reference($value)) {
            return $this->unescape_references($value);
        }

        $inspection = $this->template_inspector->inspect($value, $path);
        $references = $inspection->get_references();
        if ($inspection->has_syntax_errors()) {
            foreach ($inspection->get_syntax_errors() as $error) {
                $errors[] = $error;
            }

            return $this->unescape_references($value);
        }

        if (count($references) > self::MAX_REFERENCES) {
            $errors[] = $this->make_error($path, 'too_many_references', __('The value contains too many references.', 'elzo-forms'));
            return $this->unescape_references($value);
        }

        if ($inspection->is_exact_reference()) {
            $reference = $references[0]['path'] ?? '';
            return $this->resolve_reference_path($state, $reference, $path, $errors);
        }

        $result = '';
        $offset = 0;
        foreach ($references as $reference) {
            $start = (int) $reference['start'];
            $length = (int) $reference['length'];
            $result .= $this->unescape_references(substr($value, $offset, $start - $offset));

            $resolved = $this->resolve_reference_path($state, (string) $reference['path'], $path, $errors);
            $string_value = $this->value_to_template_string($resolved, $path, $errors, (string) $reference['path']);
            $result .= $string_value;
            $offset = $start + $length;
        }

        $result .= $this->unescape_references(substr($value, $offset));

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     *
     * @return mixed
     */
    private function resolve_reference_path(array $state, string $reference, string $path, array &$errors) {
        if (strlen($reference) > self::MAX_PATH_LENGTH) {
            $errors[] = $this->make_error($path, 'reference_path_too_long', __('The referenced path is too long.', 'elzo-forms'), $reference);
            return null;
        }

        if (!$this->is_valid_path($reference)) {
            $errors[] = $this->make_error($path, 'invalid_reference_syntax', __('The reference syntax is invalid.', 'elzo-forms'), $reference);
            return null;
        }

        $path_status = $this->path_status($state, $reference);
        if ($path_status === 'not_accessible') {
            $errors[] = $this->make_error($path, 'path_not_accessible', __('The referenced value is not accessible.', 'elzo-forms'), $reference);
            return null;
        }

        if ($path_status !== 'found') {
            $errors[] = $this->make_error($path, 'path_not_found', __('The referenced value could not be found.', 'elzo-forms'), $reference);
            return null;
        }

        return $this->get_path($state, $reference);
    }

    private function path_status(array $state, string $path): string {
        $current = $state;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($current)) {
                return 'not_accessible';
            }

            $key = array_key_exists($segment, $current) ? $segment : (ctype_digit($segment) ? (int) $segment : $segment);
            if (!array_key_exists($key, $current)) {
                return 'not_found';
            }

            $current = $current[$key];
        }

        return 'found';
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     */
    private function value_to_template_string($value, string $path, array &$errors, string $reference): string {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            $json = wp_json_encode($value);
            if (!is_string($json) || $json === '') {
                $errors[] = $this->make_error($path, 'json_encode_failed', __('The referenced value could not be converted to JSON.', 'elzo-forms'), $reference);
                return '';
            }

            return $json;
        }

        $errors[] = $this->make_error($path, 'template_requires_string', __('The referenced value cannot be used in text.', 'elzo-forms'), $reference);

        return '';
    }

    private function unescape_references(string $value): string {
        return str_replace('\\{{', '{{', $value);
    }

    private function safe_path_segment(string $segment): string {
        $segment = preg_replace('/[^A-Za-z0-9_-]+/', '_', $segment);
        $segment = trim((string) $segment, '_');

        return $segment !== '' ? $segment : 'value';
    }

    private function make_error(string $path, string $code, string $message, string $reference = ''): array {
        $error = [
            'path' => $path,
            'code' => $code,
            'message' => $message,
        ];

        if ($reference !== '') {
            $error['reference'] = $reference;
        }

        return $error;
    }
}
