<?php
/**
 * Import / Export package.
 *
 * A package is a small JSON envelope around portable form and submission
 * items. It never carries WordPress database rows: items describe forms by
 * key and submissions by a package-local form reference, so nothing in it
 * depends on the numeric IDs of the site that produced it.
 *
 * The envelope declares a schema version. It changes only for an
 * incompatible format change; new optional keys do not change it, and readers
 * ignore keys they do not know. Every readable schema version is read into
 * the same normalized package, which is all the importers work with, so a
 * later schema adds a reader here rather than changes to the importers.
 *
 * @package ElzoForms\ImportExport
 */

namespace ElzoForms\ImportExport;

use ElzoForms\Form\Compatibility\MinimumVersion;

// Exit if accessed directly
defined('ABSPATH') || exit;

final class Package {

    /** Format identifier written into every package. */
    public const FORMAT = 'elzo-forms';

    /** Schema version written by this plugin version. */
    public const SCHEMA_VERSION = 1;

    /**
     * Schema versions this plugin reads.
     *
     * A version stays in this list for as long as practical, so existing
     * backups keep importing without being exported again.
     */
    private const READABLE_SCHEMA_VERSIONS = [1];

    /** Maximum nesting accepted when decoding an uploaded file. */
    private const MAX_DEPTH = 64;

    /** Maximum length of a package-local reference. */
    private const MAX_REF_LENGTH = 191;

    /** Rough ratio between the memory of decoded data and the size of its JSON. */
    private const DECODE_MEMORY_FACTOR = 10;

    /** @var array<int, array> */
    private $forms = [];

    /** @var array<int, array> */
    private $submissions = [];

    /** @var array<string, array{ref: string, key: string, title: string}> */
    private $form_references = [];

    /** @var string */
    private $plugin_version = '';

    /** @var int */
    private $schema_version = self::SCHEMA_VERSION;

    /** @var string */
    private $exported_at = '';

    /** @var bool */
    private $includes_sensitive = false;

    /** @var bool */
    private $legacy = false;

    private function __construct() {
    }

    /**
     * Build the header of a new package.
     *
     * @param bool $includes_sensitive Whether the package carries sensitive settings.
     * @return array
     */
    public static function header(bool $includes_sensitive = false): array {
        return [
            'format' => self::FORMAT,
            'schema_version' => self::SCHEMA_VERSION,
            'plugin_version' => self::installed_version(),
            'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'includes_sensitive' => $includes_sensitive,
        ];
    }

    /**
     * Get the installed plugin version.
     *
     * @return string
     */
    public static function installed_version(): string {
        if (function_exists('elzo_forms_version')) {
            return (string) elzo_forms_version();
        }

        return defined('ELZO_FORMS_VERSION') ? (string) ELZO_FORMS_VERSION : '0.0.0';
    }

    /**
     * Parse an uploaded file.
     *
     * Accepts an Elzo Forms package of any readable schema version and, for
     * compatibility, the single-form JSON written for developer-managed JSON
     * sources.
     *
     * @param string $json File contents.
     * @return self|\WP_Error
     */
    public static function parse(string $json) {
        // A byte order mark is valid in a UTF-8 file but not in JSON.
        if (strncmp($json, "\xEF\xBB\xBF", 3) === 0) {
            $json = substr($json, 3);
        }

        if (trim($json) === '') {
            return new \WP_Error('elzo_forms_import_empty', __('The file is empty.', 'elzo-forms'));
        }

        $size_error = self::check_size(strlen($json));
        if ($size_error) {
            return $size_error;
        }

        try {
            $decoded = json_decode($json, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $exception) {
            unset($exception);
            return new \WP_Error('elzo_forms_import_invalid_json', __('The file is not valid JSON.', 'elzo-forms'));
        }

        if (!is_array($decoded)) {
            return new \WP_Error('elzo_forms_import_invalid_format', __('The file is not an Elzo Forms export.', 'elzo-forms'));
        }

        if (array_key_exists('format', $decoded)) {
            if ($decoded['format'] !== self::FORMAT) {
                return new \WP_Error('elzo_forms_import_invalid_format', __('The file is not an Elzo Forms export.', 'elzo-forms'));
            }

            $schema_version = self::declared_schema_version($decoded);
            if ($schema_version === 0) {
                return new \WP_Error('elzo_forms_import_invalid_format', __('The file declares a format version that is not valid.', 'elzo-forms'));
            }

            if (!in_array($schema_version, self::READABLE_SCHEMA_VERSIONS, true)) {
                return new \WP_Error('elzo_forms_import_unsupported_schema', $schema_version > self::SCHEMA_VERSION
                    /* translators: %d: Format version of the file. */
                    ? sprintf(__('This file uses format version %d, which needs a newer version of Elzo Forms. Update Elzo Forms, then import the file again.', 'elzo-forms'), $schema_version)
                    /* translators: %d: Format version of the file. */
                    : sprintf(__('Format version %d is no longer supported.', 'elzo-forms'), $schema_version));
            }

            return self::read_schema_1($decoded, $schema_version);
        }

        if (self::is_legacy_form($decoded)) {
            return self::from_legacy_form($decoded);
        }

        return new \WP_Error('elzo_forms_import_invalid_format', __('The file is not an Elzo Forms export.', 'elzo-forms'));
    }

    /**
     * Get the schema version a package declares.
     *
     * A package with the format identifier but without a version is read as
     * schema 1, the first published schema.
     *
     * @param array $decoded Decoded file.
     * @return int The version, or 0 when the declared value is not a positive whole number.
     */
    private static function declared_schema_version(array $decoded): int {
        if (!array_key_exists('schema_version', $decoded)) {
            return 1;
        }

        $value = $decoded['schema_version'];
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        } elseif (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        return is_int($value) && $value > 0 ? $value : 0;
    }

    /**
     * Check that a file of this size can be decoded within the memory limit.
     *
     * Callers raise the memory limit and check before they read a file, so a
     * file that cannot fit is refused before it is loaded at all.
     *
     * @param int $bytes File size.
     * @return \WP_Error|null An error when the file is too large, otherwise null.
     */
    public static function check_size(int $bytes): ?\WP_Error {
        if (self::fits_in_memory($bytes)) {
            return null;
        }

        return new \WP_Error('elzo_forms_import_too_large', __('This file is too large to import with the memory available to this site. Export fewer forms or submissions per file, for example one form or date range at a time.', 'elzo-forms'));
    }

    /**
     * Whether decoding a file of this size fits into the memory limit.
     *
     * @param int $bytes File size.
     * @return bool
     */
    private static function fits_in_memory(int $bytes): bool {
        if (!function_exists('wp_convert_hr_to_bytes')) {
            return true;
        }

        $limit = wp_convert_hr_to_bytes((string) ini_get('memory_limit'));
        if ($limit <= 0) {
            return true;
        }

        return memory_get_usage(true) + $bytes * self::DECODE_MEMORY_FACTOR <= $limit;
    }

    /**
     * Read a schema 1 package.
     *
     * @param array $decoded Decoded file.
     * @param int $schema_version Declared schema version.
     * @return self
     */
    private static function read_schema_1(array $decoded, int $schema_version): self {
        $package = new self();
        $package->schema_version = $schema_version;
        $package->plugin_version = MinimumVersion::is_valid($decoded['plugin_version'] ?? null)
            ? MinimumVersion::normalize($decoded['plugin_version'])
            : '';
        $package->exported_at = isset($decoded['exported_at']) && is_scalar($decoded['exported_at'])
            ? sanitize_text_field((string) $decoded['exported_at'])
            : '';
        $package->includes_sensitive = !empty($decoded['includes_sensitive']);

        $references = isset($decoded['references']['forms']) && is_array($decoded['references']['forms'])
            ? $decoded['references']['forms']
            : [];
        foreach ($references as $reference) {
            if (!is_array($reference)) {
                continue;
            }

            $ref = self::sanitize_ref($reference['ref'] ?? '');
            if ($ref === '' || isset($package->form_references[$ref])) {
                continue;
            }

            $package->form_references[$ref] = [
                'ref' => $ref,
                'key' => self::sanitize_key_value($reference['key'] ?? ''),
                'title' => self::sanitize_title_value($reference['title'] ?? ''),
            ];
        }

        $forms = isset($decoded['forms']) && is_array($decoded['forms']) ? array_values($decoded['forms']) : [];
        foreach ($forms as $index => $item) {
            if (is_array($item)) {
                $package->add_form($item, $index);
            }
        }

        // Submission items are kept as decoded and never modified here, so a
        // large file is not copied item by item.
        $submissions = isset($decoded['submissions']) && is_array($decoded['submissions']) ? $decoded['submissions'] : [];
        $package->submissions = array_values(array_filter($submissions, 'is_array'));

        foreach ($package->submissions as $item) {
            $ref = self::submission_form_ref($item);
            if ($ref !== '' && !isset($package->form_references[$ref])) {
                $package->form_references[$ref] = [
                    'ref' => $ref,
                    'key' => '',
                    'title' => '',
                ];
            }
        }

        return $package;
    }

    /**
     * Whether a decoded file is the single-form JSON of a developer JSON source.
     *
     * @param array $decoded Decoded file.
     * @return bool
     */
    private static function is_legacy_form(array $decoded): bool {
        return isset($decoded['key'], $decoded['title'])
            && is_scalar($decoded['key'])
            && is_scalar($decoded['title'])
            && trim((string) $decoded['key']) !== ''
            && (!array_key_exists('data', $decoded) || is_array($decoded['data']));
    }

    /**
     * Build a package from a developer JSON source form.
     *
     * @param array $decoded Decoded file.
     * @return self
     */
    private static function from_legacy_form(array $decoded): self {
        $package = new self();
        $package->legacy = true;
        $package->add_form([
            'key' => $decoded['key'],
            'title' => $decoded['title'],
            'status' => $decoded['status'] ?? 'publish',
            'data' => isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : [],
        ], 0);

        return $package;
    }

    /**
     * Add a form item with normalized descriptive properties.
     *
     * The form data itself stays raw here: it is validated and sanitized by
     * the importer, which also reports what it had to drop.
     *
     * @param array $item Form item.
     * @param int $index Position of the item in the file.
     * @return void
     */
    private function add_form(array $item, int $index): void {
        $key = self::sanitize_key_value($item['key'] ?? '');
        $ref = self::sanitize_ref($item['ref'] ?? '');
        if ($ref === '') {
            $ref = $key !== '' ? $key : 'form-' . ($index + 1);
        }

        $redacted = [];
        foreach ((isset($item['redacted']) && is_array($item['redacted']) ? $item['redacted'] : []) as $path) {
            if (is_scalar($path) && (string) $path !== '') {
                $redacted[] = sanitize_text_field((string) $path);
            }
        }

        $form = [
            'index' => count($this->forms),
            'ref' => $ref,
            'key' => $key,
            'title' => self::sanitize_title_value($item['title'] ?? ''),
            'status' => isset($item['status']) && is_scalar($item['status']) ? sanitize_key((string) $item['status']) : '',
            'data' => isset($item['data']) && is_array($item['data']) ? $item['data'] : null,
            'redacted' => array_values(array_unique($redacted)),
        ];

        $this->forms[] = $form;

        // The first form claims a reference; later duplicates stay importable
        // as forms but cannot be targeted by submissions.
        if (!isset($this->form_references[$ref]) || empty($this->form_references[$ref]['has_form'])) {
            $this->form_references[$ref] = [
                'ref' => $ref,
                'key' => $key !== '' ? $key : ($this->form_references[$ref]['key'] ?? ''),
                'title' => $form['title'] !== '' ? $form['title'] : ($this->form_references[$ref]['title'] ?? ''),
                'has_form' => true,
                'form_index' => $form['index'],
            ];
        }
    }

    /**
     * Get the form reference of a submission item.
     *
     * @param array $item Submission item.
     * @return string
     */
    public static function submission_form_ref(array $item): string {
        return self::sanitize_ref($item['form_ref'] ?? '');
    }

    /**
     * Sanitize a package-local reference.
     *
     * @param mixed $value Raw reference.
     * @return string
     */
    private static function sanitize_ref($value): string {
        if (!is_scalar($value)) {
            return '';
        }

        $ref = preg_replace('/[^A-Za-z0-9_.:\-]/', '', (string) $value) ?? '';

        return substr($ref, 0, self::MAX_REF_LENGTH);
    }

    /**
     * Sanitize a form key.
     *
     * @param mixed $value Raw key.
     * @return string
     */
    private static function sanitize_key_value($value): string {
        return is_scalar($value) ? sanitize_title((string) $value) : '';
    }

    /**
     * Sanitize a form title.
     *
     * @param mixed $value Raw title.
     * @return string
     */
    private static function sanitize_title_value($value): string {
        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }

    /**
     * Get the form items, in file order.
     *
     * Each item has index, ref, key, title, status, data (array, or null when
     * the item carries none) and redacted (paths left out of the export).
     *
     * @return array<int, array>
     */
    public function forms(): array {
        return $this->forms;
    }

    /**
     * Get one form item.
     *
     * @param int $index Item index.
     * @return array|null
     */
    public function form(int $index): ?array {
        return $this->forms[$index] ?? null;
    }

    /**
     * Get the raw submission items, in file order.
     *
     * Read an item's form reference with submission_form_ref().
     *
     * @return array<int, array>
     */
    public function submissions(): array {
        return $this->submissions;
    }

    /**
     * Get every form reference the package defines or uses.
     *
     * @return array<string, array>
     */
    public function form_references(): array {
        return $this->form_references;
    }

    /**
     * Get the plugin version that wrote the package, when it declares one.
     *
     * @return string
     */
    public function plugin_version(): string {
        return $this->plugin_version;
    }

    /**
     * Get the schema version the package was read as.
     *
     * @return int
     */
    public function schema_version(): int {
        return $this->schema_version;
    }

    /**
     * Get the export timestamp as written in the package.
     *
     * @return string
     */
    public function exported_at(): string {
        return $this->exported_at;
    }

    /**
     * Whether the package declares that it carries sensitive settings.
     *
     * @return bool
     */
    public function includes_sensitive(): bool {
        return $this->includes_sensitive;
    }

    /**
     * Whether the package was read from a developer JSON source form.
     *
     * @return bool
     */
    public function is_legacy(): bool {
        return $this->legacy;
    }
}
