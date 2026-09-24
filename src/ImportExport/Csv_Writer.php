<?php
/**
 * CSV writer for spreadsheet exports.
 *
 * @package ElzoForms\ImportExport
 */

namespace ElzoForms\ImportExport;

// Exit if accessed directly
defined('ABSPATH') || exit;

final class Csv_Writer {

    /** @var resource */
    private $handle;

    /**
     * @param resource $handle Writable stream.
     */
    public function __construct($handle) {
        $this->handle = $handle;
    }

    /**
     * Write the UTF-8 byte order mark spreadsheet applications use to detect the encoding.
     *
     * @return void
     */
    public function write_bom(): void {
        fwrite($this->handle, "\xEF\xBB\xBF"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writes to the download stream opened by the caller.
    }

    /**
     * Write one row.
     *
     * @param array $cells Cell values.
     * @return void
     */
    public function write_row(array $cells): void {
        fputcsv($this->handle, array_map([self::class, 'cell'], array_values($cells)), ',', '"', '');
    }

    /**
     * Convert a value to a safe cell.
     *
     * @param mixed $value Cell value.
     * @return string
     */
    public static function cell($value): string {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (!is_scalar($value)) {
            return '';
        }

        return self::protect_formula((string) $value);
    }

    /**
     * Keep a cell from being evaluated as a formula.
     *
     * Spreadsheet applications evaluate cells that start with =, +, -, @ or
     * a control character, which lets submitted text run formulas or external
     * commands when the file is opened. Such cells are prefixed with an
     * apostrophe; plain numbers, including negative ones, stay as they are.
     *
     * @param string $value Cell text.
     * @return string
     */
    public static function protect_formula(string $value): string {
        if ($value === '' || preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            return $value;
        }

        $first = ltrim($value, " \t\r\n")[0] ?? '';

        if (in_array($first, ['=', '+', '-', '@'], true) || in_array($value[0], ["\t", "\r", "\n"], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
