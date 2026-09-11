<?php
/**
 * Field type picker.
 *
 * @package ElzoForms\Admin
 */

namespace ElzoForms\Admin;

use ElzoForms\Field\Field_Type;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Presentation of the field types in the builder's type picker.
 *
 * The same picker adds fields and changes the type of existing ones. Which
 * types exist, and their variants, is defined by the field registry through
 * Field_Type::get_types(); this class only adds how each type is presented:
 * label, category, icon, search keywords and order. Every creatable type is
 * offered, with fallbacks when no presentation is defined, and an item can
 * never name a type the builder cannot create.
 */
class Field_Picker {

    /** Category for items whose category is not registered. */
    public const FALLBACK_CATEGORY = 'other';

    /** Icon for items without a known icon. */
    public const FALLBACK_ICON = 'field';

    /**
     * Icon artwork on a 24-unit box.
     *
     * Drawn on the grid of the Elzo Forms icon font with the same stroke
     * weight. Email, URL and Hidden reuse the font's own mail, link and hide
     * glyphs, mirrored from font coordinates into the box.
     */
    private const ICONS = [
        'text' => '<path d="M5 8V5h14v3M12 5v14M8.5 19h7"/>',
        'email' => '<path fill="currentColor" stroke="none" transform="matrix(.0234375 0 0 -.0234375 0 22.5)" d="M896 149.333v576h-746.667v-576h746.667zM300.459 640h444.416l-222.208-206.895-222.208 206.895zM234.667 584.625l288-268.105 288 268.105v-349.958h-576v349.958z"/>',
        'phone' => '<path d="M7 4h10v16H7zM11 16.5h2"/>',
        'number' => '<path d="M10.5 4 8.5 20M16.5 4l-2 16M5 9h15M4 15h15"/>',
        'url' => '<path fill="currentColor" stroke="none" transform="matrix(.0234375 0 0 -.0234375 0 22.5)" d="M276.576 217.297c-41.655 41.655-41.651 109.195 0 150.85l123.27 123.273c41.658 41.655 109.195 41.655 150.85 0l21.331-21.331 60.339 60.339-21.331 21.331c-74.98 74.98-196.548 74.98-271.529 0l-123.273-123.273c-74.975-74.98-74.978-196.548 0-271.529 74.98-74.978 196.548-74.976 271.531 0l10.016 10.018-60.339 60.339-10.018-10.018c-41.655-41.651-109.195-41.653-150.848 0zM742.701 683.424c41.655-41.653 41.651-109.193 0-150.85l-123.273-123.27c-41.655-41.658-109.193-41.658-150.85 0l-21.329 21.329-60.341-60.339 21.331-21.331c74.98-74.98 196.548-74.98 271.529 0l123.273 123.273c74.976 74.982 74.98 196.55 0 271.529-74.978 74.98-196.548 74.977-271.529 0l-10.018-10.016 60.341-60.341 10.016 10.018c41.658 41.651 109.197 41.655 150.85 0z"/>',
        'date' => '<path d="M4.5 6.5h15v13h-15zM4.5 10.5h15M8.5 4v4.5M15.5 4v4.5"/>',
        'time' => '<circle cx="12" cy="12" r="8"/><path d="M12 7.5V12h3.5"/>',
        'password' => '<path d="M5.5 10h13v9.5h-13zM9 10V7.5a3 3 0 0 1 6 0V10M12 13.5v3"/>',
        'textarea' => '<path d="M4 5h16v14H4zM7.5 9.5h9M7.5 13.5h5.5"/>',
        'select' => '<path d="M4 7h16v10H4zM13 11l2.5 2.5L18 11"/>',
        'checkbox' => '<path d="M4.5 4.5h15v15h-15zM8 12.5l2.75 2.75L16 10"/>',
        'radio' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.5" fill="currentColor" stroke="none"/>',
        'range' => '<path d="M4 12h3M14 12h6"/><circle cx="10.5" cy="12" r="3"/>',
        'file' => '<path d="M12 15V4.5M7.5 9 12 4.5 16.5 9M4.5 14v5.5h15V14"/>',
        'hidden' => '<path fill="currentColor" stroke="none" transform="matrix(.0234375 0 0 -.0234375 0 22.5)" d="M185.896 511.249c-17.397-19.153-31.542-37.338-42.375-52.582 16.295-22.931 40.043-52.555 71.167-82.042 65.862-62.398 160.585-120.625 286.645-120.625-102.479 0-187.153 76.066-200.73 174.812l242.98-170.353c-13.632-2.891-27.759-4.459-42.251-4.459 15.456 0 30.441 0.89 44.958 2.541l95.334-66.833c-42.432-13.131-89.169-21.041-140.292-21.041-309.333 0-458.667 288-458.667 288s24.061 46.377 72.854 101.916l70.375-49.333zM501.333 746.667c309.333 0 458.667-288 458.667-288s-28.516-54.991-86.438-116.917l-70.729 49.585c23.904 24.467 42.703 48.211 56.292 67.332-16.297 22.931-40.028 52.559-71.147 82.042-65.862 62.398-160.585 120.625-286.645 120.625 111.255 0 201.519-89.647 202.605-200.646l-269.835 189.188c21.041 7.398 43.659 11.458 67.23 11.458-25.877 0-50.43-2.477-73.687-6.916l-90.208 63.228c48.51 17.873 103.108 29.022 163.895 29.022zM995.166 152.271l-48.998-69.875-928.001 650.667 49 69.875 927.999-650.667z"/>',
        'content' => '<path d="M4 7h16M4 12h16M4 17h10"/>',
        'button' => '<rect x="4" y="7" width="16" height="10" rx="2.5"/><path d="M8.5 12h7"/>',
        'field' => '<path d="M4 7h16v10H4zM7.5 10v4"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/>',
        'auth' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0M15 9h5M18 6l2.5 3L18 12"/>',
        'page' => '<path d="M6 3.5h8l4 4V20.5H6zM14 3.5v4h4M9 12h6M9 15.5h6"/>',
        'cookie' => '<path d="M19.5 12a7.5 7.5 0 1 1-7.5-7.5c0 2.2 1.8 4 4 4 0 1.9 1.6 3.5 3.5 3.5z"/><circle cx="9" cy="10" r=".75" fill="currentColor" stroke="none"/><circle cx="12" cy="15" r=".75" fill="currentColor" stroke="none"/><circle cx="7.5" cy="15.5" r=".75" fill="currentColor" stroke="none"/>',
        'logic' => '<path d="M5 5v4a3 3 0 0 0 3 3h8M5 19v-4a3 3 0 0 1 3-3M16 8l4 4-4 4"/><circle cx="5" cy="5" r="2"/><circle cx="5" cy="19" r="2"/>',
    ];

    /**
     * Get picker categories in display order.
     *
     * Every item carries one of these keys, so the picker can later filter by
     * category without changing the item data.
     *
     * @return array<string, string> Category key => label.
     */
    public static function get_categories(): array {
        return [
            'basic' => __('Basic', 'elzo-forms'),
            'choice' => __('Choice', 'elzo-forms'),
            'special' => __('Date & other inputs', 'elzo-forms'),
            'layout' => __('Layout & advanced', 'elzo-forms'),
            self::FALLBACK_CATEGORY => __('Other', 'elzo-forms'),
        ];
    }

    /**
     * Get picker items in display order.
     *
     * Items are grouped by category; within a category they keep the order of
     * the presentation map, followed by types without presentation in
     * registry order.
     *
     * @return array<int, array{id: string, type: string, label: string, category: string, icon: string, keywords: array<int, string>}>
     */
    public static function get_items(): array {
        $types = Field_Type::get_types();
        $presentation = self::get_presentation();
        $items = [];

        foreach ($presentation as $type => $item_presentation) {
            if (isset($types[$type])) {
                $items[] = ['type' => (string) $type] + $item_presentation;
            }
        }

        foreach (array_keys($types) as $type) {
            if (!isset($presentation[$type])) {
                $items[] = ['type' => (string) $type];
            }
        }

        /**
         * Filters the items offered by the builder's field type picker.
         *
         * "type" must be a field type the builder can create: a registered
         * type, or a registered variant written as "{base type}:{subtype}"
         * (see Field_Type). Items naming anything else are dropped. Optional
         * keys: "label", "category" (a key of Field_Picker::get_categories(),
         * otherwise the "other" category), "icon" (a key of
         * Field_Picker::get_icon_keys(), otherwise a generic icon) and
         * "keywords" (an array, or a comma-separated string, of extra search
         * terms). Array order is the display order within a category.
         *
         * @filter elzo_forms_field_picker_items
         * @param array<int, array<string, mixed>> $items Picker items.
         */
        $items = apply_filters('elzo_forms_field_picker_items', $items);

        return self::normalize_items(is_array($items) ? $items : [], $types);
    }

    /**
     * Get picker items grouped by category, leaving out empty categories.
     *
     * @return array<int, array{key: string, label: string, items: array<int, array<string, mixed>>}>
     */
    public static function get_item_groups(): array {
        $categories = self::get_categories();
        $groups = [];

        foreach (self::get_items() as $item) {
            $category = $item['category'];

            if (!isset($groups[$category])) {
                $groups[$category] = [
                    'key' => $category,
                    'label' => $categories[$category],
                    'items' => [],
                ];
            }

            $groups[$category]['items'][] = $item;
        }

        return array_values($groups);
    }

    /**
     * Get the picker item presenting a field type.
     *
     * Types the picker does not offer, such as the type of an inactive add-on,
     * get a fallback item named after the registry label or the type itself.
     *
     * @param string $type Field type identifier.
     * @return array{id: string, type: string, label: string, category: string, icon: string, keywords: array<int, string>}
     */
    public static function get_item(string $type): array {
        $normalized = Field_Type::normalize($type);

        foreach (self::get_items() as $item) {
            if ($item['type'] === $normalized) {
                return $item;
            }
        }

        $types = Field_Type::get_types();

        return [
            'id' => '',
            'type' => $normalized,
            'label' => $types[$normalized] ?? ($normalized !== '' ? $normalized : $type),
            'category' => self::FALLBACK_CATEGORY,
            'icon' => self::FALLBACK_ICON,
            'keywords' => [],
        ];
    }

    /**
     * Get the keys of the available icons.
     *
     * @return array<int, string>
     */
    public static function get_icon_keys(): array {
        return array_keys(self::ICONS);
    }

    /**
     * Get the SVG markup of an icon.
     *
     * The markup is fixed artwork from this class; unknown keys get the
     * generic field icon.
     *
     * @param string $icon Icon key.
     */
    public static function get_icon_svg(string $icon): string {
        $artwork = self::ICONS[$icon] ?? self::ICONS[self::FALLBACK_ICON];

        return '<svg class="elzo-forms-field-type-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">' . $artwork . '</svg>';
    }

    /**
     * Presentation of the built-in field types.
     *
     * Keyed by field type. The order of this map is the default order inside
     * each category. A label is only set where the picker name differs from
     * the registry name; the registry name then remains searchable.
     *
     * @return array<string, array<string, string>>
     */
    private static function get_presentation(): array {
        return [
            'text' => [
                'category' => 'basic',
                'icon' => 'text',
                'keywords' => _x('single line, short answer, input, name', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'text:email' => [
                'category' => 'basic',
                'icon' => 'email',
                'keywords' => _x('mail, e-mail', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'text:tel' => [
                'label' => __('Phone', 'elzo-forms'),
                'category' => 'basic',
                'icon' => 'phone',
                'keywords' => _x('telephone, mobile, cell', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'text:number' => [
                'category' => 'basic',
                'icon' => 'number',
                'keywords' => _x('numeric, digits, integer, amount, quantity', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'textarea' => [
                'category' => 'basic',
                'icon' => 'textarea',
                'keywords' => _x('paragraph, multiline, multi-line, long text, message, comment', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'select' => [
                'category' => 'choice',
                'icon' => 'select',
                'keywords' => _x('dropdown, drop-down, choice, options, list', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'checkbox' => [
                'category' => 'choice',
                'icon' => 'checkbox',
                'keywords' => _x('checkboxes, check, choice, options, multiple choice, consent, agree, toggle', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'radio' => [
                'category' => 'choice',
                'icon' => 'radio',
                'keywords' => _x('radio buttons, choice, options, single choice', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'range' => [
                'category' => 'choice',
                'icon' => 'range',
                'keywords' => _x('slider, scale', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'text:date' => [
                'category' => 'special',
                'icon' => 'date',
                'keywords' => _x('calendar, day, birthday', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'text:time' => [
                'category' => 'special',
                'icon' => 'time',
                'keywords' => _x('clock, hour', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'text:url' => [
                'category' => 'special',
                'icon' => 'url',
                'keywords' => _x('link, website, web address', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'text:password' => [
                'category' => 'special',
                'icon' => 'password',
                'keywords' => _x('secret', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'file' => [
                'label' => __('File Upload', 'elzo-forms'),
                'category' => 'special',
                'icon' => 'file',
                'keywords' => _x('upload, attachment, document, image, photo', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'hidden' => [
                'category' => 'layout',
                'icon' => 'hidden',
                'keywords' => _x('invisible, tracking', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'content' => [
                'category' => 'layout',
                'icon' => 'content',
                'keywords' => _x('html, text block, heading, description, rich text', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
            'button' => [
                'category' => 'layout',
                'icon' => 'button',
                'keywords' => _x('submit, send, next', 'Add Field picker search keywords, comma-separated', 'elzo-forms'),
            ],
        ];
    }

    /**
     * Validate filtered items against the creatable types and fill defaults.
     *
     * @param array<int|string, mixed> $items Filtered items.
     * @param array<string, string> $types Creatable types => registry labels.
     * @return array<int, array<string, mixed>>
     */
    private static function normalize_items(array $items, array $types): array {
        $categories = self::get_categories();
        $category_positions = array_flip(array_keys($categories));
        $normalized = [];

        foreach (array_values($items) as $position => $item) {
            if (!is_array($item)) {
                continue;
            }

            $requested_type = self::to_string($item['type'] ?? '');
            if (!Field_Type::is_registered($requested_type)) {
                continue;
            }

            $type = Field_Type::normalize($requested_type);
            if (!isset($types[$type]) || isset($normalized[$type])) {
                continue;
            }

            $parsed = Field_Type::parse($type);
            $label = trim(self::to_string($item['label'] ?? ''));
            $category = self::to_string($item['category'] ?? '');
            $icon = self::to_string($item['icon'] ?? '');

            // Registry names always find the item, whatever the presentation says.
            $keywords = self::parse_keywords($item['keywords'] ?? []);
            $keywords[] = $types[$type];
            $keywords[] = $parsed['subtype'] !== '' ? $parsed['subtype'] : $parsed['type'];

            $normalized[$type] = [
                'type' => $type,
                'label' => $label !== '' ? $label : $types[$type],
                'category' => isset($categories[$category]) ? $category : self::FALLBACK_CATEGORY,
                'icon' => isset(self::ICONS[$icon]) ? $icon : self::FALLBACK_ICON,
                'keywords' => array_values(array_unique(array_filter($keywords, static function (string $keyword): bool {
                    return $keyword !== '';
                }))),
                'position' => $position,
            ];
        }

        $normalized = array_values($normalized);

        usort($normalized, static function (array $a, array $b) use ($category_positions): int {
            return [$category_positions[$a['category']], $a['position']] <=> [$category_positions[$b['category']], $b['position']];
        });

        foreach ($normalized as $index => $item) {
            unset($item['position']);
            $normalized[$index] = ['id' => 'elzo-forms-field-picker-option-' . ($index + 1)] + $item;
        }

        return $normalized;
    }

    /**
     * Split keywords given as an array or a comma-separated string.
     *
     * "|" separates keywords in the picker markup, so it cannot be part of one.
     *
     * @param mixed $keywords Keywords.
     * @return array<int, string>
     */
    private static function parse_keywords($keywords): array {
        if (is_scalar($keywords)) {
            $keywords = explode(',', (string) $keywords);
        }

        if (!is_array($keywords)) {
            return [];
        }

        $parsed = [];
        foreach ($keywords as $keyword) {
            $keyword = trim(str_replace('|', ' ', self::to_string($keyword)));
            if ($keyword !== '') {
                $parsed[] = $keyword;
            }
        }

        return $parsed;
    }

    /**
     * Cast a scalar to string; anything else becomes an empty string.
     *
     * @param mixed $value Value.
     */
    private static function to_string($value): string {
        return is_scalar($value) ? (string) $value : '';
    }
}
