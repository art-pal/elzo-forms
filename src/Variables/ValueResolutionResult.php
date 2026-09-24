<?php
/**
 * Variable value resolution result.
 *
 * @package ElzoForms\Variables
 */

namespace ElzoForms\Variables;

defined('ABSPATH') || exit;

class ValueResolutionResult {
    /** @var mixed */
    private $value;

    /** @var array<int, array<string, mixed>> */
    private $errors;

    /** @var array<int, array<string, mixed>> */
    private $warnings;

    /**
     * @param mixed                            $value
     * @param array<int, array<string, mixed>> $errors
     * @param array<int, array<string, mixed>> $warnings
     */
    public function __construct($value, array $errors = [], array $warnings = []) {
        $this->value = $value;
        $this->errors = array_values($errors);
        $this->warnings = array_values($warnings);
    }

    /**
     * @return mixed
     */
    public function get_value() {
        return $this->value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_errors(): array {
        return $this->errors;
    }

    public function is_valid(): bool {
        return empty($this->errors);
    }

    public function get_warnings(): array {
        return $this->warnings;
    }

    public function to_array(): array {
        return [
            'value' => $this->value,
            'errors' => $this->errors,
            'valid' => $this->is_valid(),
        ];
    }
}
