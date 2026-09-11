<?php
/**
 * Conditional logic type picker presentation.
 *
 * @package ElzoForms\Admin
 */

namespace ElzoForms\Admin;

use ElzoForms\Utilities\Conditional_Logic;

defined('ABSPATH') || exit;

/**
 * Adds picker metadata to condition types owned by Conditional_Logic.
 */
class Condition_Type_Picker {
    public const FALLBACK_ICON = 'field';

    /**
     * Get picker categories in display order.
     *
     * @return array<string, string>
     */
    public static function get_categories(): array {
        return [
            'available' => __('Conditions', 'elzo-forms'),
            'pro' => __('Elzo Forms PRO', 'elzo-forms'),
        ];
    }

    /**
     * Get normalized picker items for field visibility conditions.
     *
     * Registered runtime types are selectable. Known PRO types are appended as
     * discoverable locked entries only while they are not registered.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_items(): array {
        $registered = Conditional_Logic::get_field_condition_types();
        $presentation = self::get_presentation();
        $items = [];

        foreach ($registered as $type => $label) {
            $metadata = $presentation[$type] ?? [];
            $items[] = self::normalize_item((string) $type, (string) $label, $metadata, true);
        }

        foreach (['url', 'user', 'cookie', 'date_time'] as $type) {
            if (!isset($registered[$type])) {
                $metadata = $presentation[$type];
                $items[] = self::normalize_item($type, (string) $metadata['label'], $metadata, false);
            }
        }

        /**
         * Filters conditional type picker presentation items.
         *
         * This filter changes presentation only. An item is selectable only
         * when its type is present in Conditional_Logic's runtime registry.
         *
         * @filter elzo_forms_condition_type_picker_items
         * @param array<int, array<string, mixed>> $items Picker items.
         * @param array<string, string> $registered Registered condition types.
         */
        $items = apply_filters('elzo_forms_condition_type_picker_items', $items, $registered);

        $categories = self::get_categories();
        $normalized = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = sanitize_key((string) ($item['type'] ?? ''));
            if ($type === '' || isset($normalized[$type])) {
                continue;
            }

            $is_available = isset($registered[$type]);
            if (!$is_available && !isset($categories['pro'])) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ($registered[$type] ?? $type)));
            $keywords = self::parse_keywords($item['keywords'] ?? []);
            $icon = (string) ($item['icon'] ?? self::FALLBACK_ICON);

            $normalized[$type] = [
                'type' => $type,
                'label' => $label !== '' ? $label : $type,
                'category' => $is_available ? 'available' : 'pro',
                'icon' => in_array($icon, Field_Picker::get_icon_keys(), true) ? $icon : self::FALLBACK_ICON,
                'keywords' => $keywords,
                'available' => $is_available,
                'pro' => !$is_available,
            ];
        }

        $items = array_values($normalized);
        foreach ($items as $index => $item) {
            $items[$index] = ['id' => 'elzo-forms-condition-type-picker-option-' . ($index + 1)] + $item;
        }

        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    public static function get_item_groups(): array {
        $categories = self::get_categories();
        $groups = [];

        foreach (self::get_items() as $item) {
            $key = $item['category'];
            if (!isset($groups[$key])) {
                $groups[$key] = ['key' => $key, 'label' => $categories[$key], 'items' => []];
            }
            $groups[$key]['items'][] = $item;
        }

        return array_values($groups);
    }

    /**
     * Get the presentation for a saved type without changing its value.
     *
     * @return array<string, mixed>
     */
    public static function get_item(string $type): array {
        foreach (self::get_items() as $item) {
            if ($item['type'] === $type) {
                return $item;
            }
        }

        $registered = Conditional_Logic::get_field_condition_types();
        return [
            'id' => '',
            'type' => $type,
            'label' => $registered[$type] ?? ($type !== '' ? ucwords(str_replace('_', ' ', $type)) : __('Select condition type', 'elzo-forms')),
            'category' => 'available',
            'icon' => self::FALLBACK_ICON,
            'keywords' => [],
            'available' => isset($registered[$type]),
            'pro' => false,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private static function get_presentation(): array {
        return [
            'field' => ['label' => __('Field value', 'elzo-forms'), 'icon' => 'field', 'keywords' => _x('field, input, value', 'Condition picker search keywords, comma-separated', 'elzo-forms')],
            'auth' => ['label' => __('Authentication', 'elzo-forms'), 'icon' => 'auth', 'keywords' => _x('login, logged in, guest', 'Condition picker search keywords, comma-separated', 'elzo-forms')],
            'page' => ['label' => __('Page', 'elzo-forms'), 'icon' => 'page', 'keywords' => _x('page, post, post type, content', 'Condition picker search keywords, comma-separated', 'elzo-forms')],
            'url' => ['label' => __('URL', 'elzo-forms'), 'icon' => 'url', 'keywords' => _x('link, address, location', 'Condition picker search keywords, comma-separated', 'elzo-forms')],
            'user' => ['label' => __('User', 'elzo-forms'), 'icon' => 'user', 'keywords' => _x('role, ID, logged in, account', 'Condition picker search keywords, comma-separated', 'elzo-forms')],
            'cookie' => ['label' => __('Cookie', 'elzo-forms'), 'icon' => 'cookie', 'keywords' => _x('browser, cookie', 'Condition picker search keywords, comma-separated', 'elzo-forms')],
            'date_time' => ['label' => __('Date and Time', 'elzo-forms'), 'icon' => 'date', 'keywords' => _x('date, time, schedule, calendar, clock', 'Condition picker search keywords, comma-separated', 'elzo-forms')],
        ];
    }

    /** @return array<string, mixed> */
    private static function normalize_item(string $type, string $label, array $metadata, bool $available): array {
        return [
            'type' => $type,
            'label' => (string) ($metadata['label'] ?? $label),
            'icon' => (string) ($metadata['icon'] ?? self::FALLBACK_ICON),
            'keywords' => $metadata['keywords'] ?? [],
            'category' => $available ? 'available' : 'pro',
            'available' => $available,
            'pro' => !$available,
        ];
    }

    /** @return array<int, string> */
    private static function parse_keywords($keywords): array {
        $keywords = is_scalar($keywords) ? explode(',', (string) $keywords) : $keywords;
        if (!is_array($keywords)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($keyword): string {
            return trim(str_replace('|', ' ', is_scalar($keyword) ? (string) $keyword : ''));
        }, $keywords)));
    }
}
