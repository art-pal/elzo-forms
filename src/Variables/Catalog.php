<?php
/**
 * Common variables available in submission notification templates.
 *
 * @package ElzoForms\Variables
 */
namespace ElzoForms\Variables;

defined('ABSPATH') || exit;

final class Catalog {
    public static function field_type(array $field): string {
        $base = \ElzoForms\Field\Field_Type::get_base_type((string) ($field['type'] ?? ''));
        if (in_array($base, ['checkbox', 'file'], true) || ($base === 'select' && !empty($field['multiple']))) {
            return 'array';
        }
        // Submitted scalar field values are sanitized strings, including numbers.
        return 'string';
    }
    public static function items(): array {
        $groups = [
            'fields' => __('Fields', 'elzo-forms'),
            'form' => __('Form', 'elzo-forms'),
            'submission' => __('Submission', 'elzo-forms'),
            'page' => __('Page', 'elzo-forms'),
            'user' => __('User', 'elzo-forms'),
        ];
        $definitions = [
            ['fields.items', __('All submitted fields', 'elzo-forms'), 'fields', 'array'],
            ['form.id', __('Form ID', 'elzo-forms'), 'form', 'integer'],
            ['form.title', __('Form title', 'elzo-forms'), 'form', 'string'],
            ['submission.id', __('Submission ID', 'elzo-forms'), 'submission', 'integer'],
            ['submission.date', __('Submission date', 'elzo-forms'), 'submission', 'string'],
            ['submission.datetime', __('Submission date and time', 'elzo-forms'), 'submission', 'string'],
            ['page.url', __('Page URL', 'elzo-forms'), 'page', 'string'],
            ['submission.user.ip', __('User IP', 'elzo-forms'), 'submission', 'string'],
            ['user.id', __('User ID', 'elzo-forms'), 'user', 'integer'],
            ['user.email', __('Logged-in user email', 'elzo-forms'), 'user', 'string'],
            ['user.display_name', __('Logged-in user display name', 'elzo-forms'), 'user', 'string'],
            ['user.first_name', __('Logged-in user first name', 'elzo-forms'), 'user', 'string'],
            ['user.last_name', __('Logged-in user last name', 'elzo-forms'), 'user', 'string'],
            ['user.roles', __('Logged-in user roles', 'elzo-forms'), 'user', 'array'],
            ['user.logged_in', __('User logged in', 'elzo-forms'), 'user', 'boolean'],
        ];
        $items = [];
        foreach ($definitions as $definition) {
            $items[] = ['path' => $definition[0], 'label' => $definition[1], 'provider' => $definition[2], 'group' => $groups[$definition[2]], 'type' => $definition[3]];
        }
        foreach ($items as &$item) {
            $item['format'] = $item['path'] === 'user.email' ? 'email' : ($item['path'] === 'page.url' ? 'url' : 'text');
        }
        unset($item);
        return $items;
    }
}
