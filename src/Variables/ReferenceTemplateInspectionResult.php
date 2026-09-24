<?php
/**
 * Reference template inspection result.
 *
 * @package ElzoForms\Variables
 */

namespace ElzoForms\Variables;

defined('ABSPATH') || exit;

class ReferenceTemplateInspectionResult {
    /** @var array<int, array{start:int,length:int,path:string}> */
    private $references;

    /** @var array<int, array<string, mixed>> */
    private $syntax_errors;

    /** @var bool */
    private $exact_reference;

    /** @var bool */
    private $interpolation;

    /**
     * @param array<int, array{start:int,length:int,path:string}> $references
     * @param array<int, array<string, mixed>>                    $syntax_errors
     */
    public function __construct(
        array $references,
        array $syntax_errors,
        bool $exact_reference,
        bool $interpolation
    ) {
        $this->references = array_values(array_filter($references, 'is_array'));
        $this->syntax_errors = array_values(array_filter($syntax_errors, 'is_array'));
        $this->exact_reference = $exact_reference;
        $this->interpolation = $interpolation;
    }

    /**
     * @return array<int, array{start:int,length:int,path:string}>
     */
    public function get_references(): array {
        return $this->references;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_syntax_errors(): array {
        return $this->syntax_errors;
    }

    public function has_syntax_errors(): bool {
        return !empty($this->syntax_errors);
    }

    public function is_exact_reference(): bool {
        return $this->exact_reference;
    }

    public function is_interpolation(): bool {
        return $this->interpolation;
    }
}
