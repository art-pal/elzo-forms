<?php
/**
 * Field type identifiers.
 *
 * @package ElzoForms\Field
 */

namespace ElzoForms\Field;

use ElzoForms\Utilities\Admin;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Parses and normalizes field type identifiers.
 *
 * A field's "type" is its only public type identifier. A type family with
 * variants writes the variant into the type as "{base type}:{subtype}", for
 * example "text:email": the base type "text" selects the field class, and
 * "email" is the subtype that class implements. The default variant of a
 * family is written as the bare base type ("text", never "text:text").
 *
 * ":" is reserved for this notation, so neither base types nor subtypes
 * contain it. Variants are registered per base type through
 * elzo_forms_field_type_variants; the Text variants come from
 * elzo_forms_field_text_subtypes.
 *
 * Type identifiers are split and composed only here.
 */
final class Field_Type {

    /** Separator between the base type and the subtype. */
    public const SEPARATOR = ':';

    /**
     * Split a type identifier into base type and subtype, without validation.
     *
     * @param string $type Field type identifier.
     * @return array{type: string, subtype: string}
     */
    public static function parse(string $type): array {
        $parts = explode(self::SEPARATOR, trim($type), 2);

        return [
            'type' => $parts[0],
            'subtype' => $parts[1] ?? '',
        ];
    }

    /**
     * Get the base type, which selects the field class.
     *
     * @param string $type Field type identifier.
     */
    public static function get_base_type(string $type): string {
        return self::parse($type)['type'];
    }

    /**
     * Get the validated subtype of a type identifier.
     *
     * A type of a family with variants always resolves to one of its
     * registered variants: the default one when the type names none or an
     * unknown one. A type without variants has no subtype.
     *
     * @param string $type Field type identifier.
     */
    public static function get_subtype(string $type): string {
        return self::resolve($type)['subtype'];
    }

    /**
     * Resolve a type identifier into its validated base type and subtype.
     *
     * @param mixed $type Field type identifier.
     * @param mixed $legacy_subtype Subtype stored separately by a legacy field.
     * @return array{type: string, subtype: string}
     */
    public static function resolve($type, $legacy_subtype = ''): array {
        $resolved = self::parse(self::normalize($type, $legacy_subtype));

        if ($resolved['subtype'] === '') {
            $resolved['subtype'] = self::get_default_subtype($resolved['type']);
        }

        return $resolved;
    }

    /**
     * Get the canonical identifier of a field type.
     *
     * Forms saved before composite types store the Text variant separately
     * ("type": "text", "subtype": "email"); passing that subtype here reads
     * it as "text:email". When the type itself names a subtype, the type is
     * authoritative and the separate subtype is ignored, so the two can never
     * disagree.
     *
     * A subtype that is not a registered variant of its base type is dropped
     * and the base type remains, so an arbitrary value never reaches rendering.
     *
     * @param mixed $type Field type identifier.
     * @param mixed $legacy_subtype Subtype stored separately by a legacy field.
     * @return string Canonical type, or an empty string when no valid base type is left.
     */
    public static function normalize($type, $legacy_subtype = ''): string {
        $type = is_scalar($type) ? (string) $type : '';
        $parsed = self::parse($type);
        $base_type = self::sanitize_part($parsed['type']);

        if ($base_type === '') {
            return '';
        }

        $subtype = strpos($type, self::SEPARATOR) !== false
            ? $parsed['subtype']
            : (is_scalar($legacy_subtype) ? (string) $legacy_subtype : '');

        return self::compose($base_type, self::sanitize_part($subtype));
    }

    /**
     * Build the canonical identifier of a base type and subtype.
     *
     * @param string $base_type Base field type.
     * @param string $subtype Subtype; empty, default or unknown subtypes leave the bare base type.
     */
    public static function compose(string $base_type, string $subtype = ''): string {
        $variants = self::get_variants($base_type);

        if ($subtype === '' || !isset($variants[$subtype]) || $subtype === self::find_default_subtype($base_type, $variants)) {
            return $base_type;
        }

        return $base_type . self::SEPARATOR . $subtype;
    }

    /**
     * Validate a historical type identifier without requiring its extension.
     *
     * Unlike normalize(), this preserves unknown variants in imported snapshots
     * so installing their field extension later can restore presentation.
     *
     * @param mixed $type Untrusted type identifier.
     * @return string Valid identifier, or empty for invalid input.
     */
    public static function sanitize_identifier($type): string {
        if (!is_string($type)) {
            return '';
        }

        $type = trim($type);

        return preg_match('/\A[A-Za-z0-9_-]+(?::[A-Za-z0-9_-]+)?\z/', $type) ? $type : '';
    }

    /**
     * Normalize the type of a field definition.
     *
     * Writes the canonical type and removes a legacy separate "subtype", so a
     * field saved again is stored in the composite format only.
     *
     * @param array $field Field definition.
     * @param string $default_type Type used when the definition has no valid one.
     * @return array
     */
    public static function normalize_field(array $field, string $default_type = 'text'): array {
        $type = self::normalize($field['type'] ?? '', $field['subtype'] ?? '');

        $field['type'] = $type !== '' ? $type : $default_type;
        unset($field['subtype']);

        return $field;
    }

    /**
     * Check that a string can serve as a base type or subtype key.
     *
     * Keys contain only letters, digits, "_" and "-"; ":" is reserved as the
     * separator of composite types.
     *
     * @param string $key Base type or subtype key.
     */
    public static function is_valid_key(string $key): bool {
        return $key !== '' && $key === self::sanitize_part($key);
    }

    /**
     * Check that an identifier names a registered field type and, when it has
     * a subtype, a registered variant of that type.
     *
     * @param string $type Field type identifier.
     */
    public static function is_registered(string $type): bool {
        $parsed = self::parse($type);

        if (!self::is_valid_key($parsed['type'])) {
            return false;
        }

        $field_types = Admin::get_field_types();
        if (!is_array($field_types) || !isset($field_types[$parsed['type']])) {
            return false;
        }

        return $parsed['subtype'] === '' || isset(self::get_variants($parsed['type'])[$parsed['subtype']]);
    }

    /**
     * Get the registered variants of a base type.
     *
     * @param string $base_type Base field type.
     * @return array<string, string> Subtype => label.
     */
    public static function get_variants(string $base_type): array {
        $variants = $base_type === 'text' ? Admin::get_field_text_subtypes() : [];

        /**
         * Filters the variants of a field type family.
         *
         * A variant is stored as "{base type}:{subtype}" in the field type.
         * The variant keyed like the base type, or else the first one, is the
         * default and is stored as the bare base type. Subtype keys may only
         * contain letters, digits, "_" and "-".
         *
         * @filter elzo_forms_field_type_variants
         * @param array<string, string> $variants Subtype => label.
         * @param string $base_type Base field type.
         */
        $variants = apply_filters('elzo_forms_field_type_variants', is_array($variants) ? $variants : [], $base_type);

        $valid = [];
        foreach (is_array($variants) ? $variants : [] as $subtype => $label) {
            $subtype = (string) $subtype;

            if (self::is_valid_key($subtype) && is_scalar($label)) {
                $valid[$subtype] = (string) $label;
            }
        }

        return $valid;
    }

    /**
     * Get the default variant of a base type, or an empty string without variants.
     *
     * @param string $base_type Base field type.
     */
    public static function get_default_subtype(string $base_type): string {
        return self::find_default_subtype($base_type, self::get_variants($base_type));
    }

    /**
     * Get every type the builder can create, in registry order.
     *
     * A family with variants contributes one type per variant instead of its
     * bare base type.
     *
     * @return array<string, string> Canonical type => registry label.
     */
    public static function get_types(): array {
        $field_types = Admin::get_field_types();
        $types = [];

        foreach (is_array($field_types) ? $field_types : [] as $base_type => $definition) {
            $base_type = (string) $base_type;
            $variants = self::get_variants($base_type);

            if (!$variants) {
                $label = is_array($definition) && isset($definition['label']) && is_scalar($definition['label'])
                    ? (string) $definition['label']
                    : '';
                $types[$base_type] = $label !== '' ? $label : $base_type;
                continue;
            }

            foreach ($variants as $subtype => $label) {
                $types[self::compose($base_type, (string) $subtype)] = $label;
            }
        }

        return $types;
    }

    /**
     * @param string $base_type Base field type.
     * @param array<string, string> $variants Registered variants of the base type.
     */
    private static function find_default_subtype(string $base_type, array $variants): string {
        if (!$variants) {
            return '';
        }

        return isset($variants[$base_type]) ? $base_type : (string) array_key_first($variants);
    }

    /**
     * Keep only the characters a type key may contain.
     *
     * @param string $part Base type or subtype.
     */
    private static function sanitize_part(string $part): string {
        return (string) preg_replace('/[^A-Za-z0-9_-]/', '', $part);
    }
}
