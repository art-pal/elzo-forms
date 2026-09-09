<?php
/**
 * Immutable form compatibility inspection result.
 *
 * @package ElzoForms\Form\Compatibility
 */

namespace ElzoForms\Form\Compatibility;

defined('ABSPATH') || exit;

final class FormCompatibilityResult {
    /** @var bool */
    private $compatible;

    /** @var string */
    private $minimum_version;

    /** @var string */
    private $installed_version;

    /** @var array<int, array<string, string>> */
    private $warnings;

    /**
     * @param array<int, array<string, string>> $warnings
     */
    public function __construct(
        bool $compatible,
        string $minimum_version,
        string $installed_version,
        array $warnings = []
    ) {
        $this->compatible = $compatible;
        $this->minimum_version = $minimum_version;
        $this->installed_version = $installed_version;
        $this->warnings = array_values(array_filter($warnings, 'is_array'));
    }

    public function is_compatible(): bool {
        return $this->compatible;
    }

    public function has_warnings(): bool {
        return !empty($this->warnings);
    }

    public function get_minimum_version(): string {
        return $this->minimum_version;
    }

    public function get_installed_version(): string {
        return $this->installed_version;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function get_warnings(): array {
        return $this->warnings;
    }

    public function to_array(): array {
        return [
            'compatible' => $this->compatible,
            'minimum_version' => $this->minimum_version,
            'installed_version' => $this->installed_version,
            'warnings' => $this->warnings,
        ];
    }
}
