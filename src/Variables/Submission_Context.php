<?php
/**
 * Explicit submission variables; never reads the current viewer or request.
 *
 * @package ElzoForms\Variables
 */
namespace ElzoForms\Variables;

use ElzoForms\Form\Form;
use ElzoForms\Submission\Submission;
use ElzoForms\Field\Field_Type;

defined('ABSPATH') || exit;

final class Submission_Context {
    public static function build(Form $form, Submission $submission): array {
        $items = $submission->get_fields();
        $definitions = $form->get_fields();
        $counts = [];
        $by_key = [];
        $by_id = [];
        // Count against the schema, including omitted fields, to avoid resolving
        // an ambiguous key merely because only one duplicate was submitted.
        foreach ($definitions as $field) {
            if (!is_array($field) || in_array(Field_Type::get_base_type((string) ($field['type'] ?? '')), ['button', 'content'], true)) {
                continue;
            }
            $key = (string) ($field['field_key'] ?? '');
            if (preg_match('/^[A-Za-z0-9_-]+$/', $key)) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
            $by_id[(string) ($field['id'] ?? '')] = '';
        }
        foreach ($counts as $key => $count) {
            if ($count === 1) {
                $by_key[$key] = '';
            }
        }
        foreach ($items as $field) {
            $id = (string) ($field['id'] ?? '');
            $key = (string) ($field['field_key'] ?? '');
            $value = $field['value'] ?? '';
            $by_id[$id] = $value;
            if (($counts[$key] ?? 0) === 1) {
                $by_key[$key] = $value;
            }
        }
        $user_data = $submission->get_user_data();
        $user_id = (int) ($user_data['user_id'] ?? 0);
        $user = $user_id > 0 ? get_userdata($user_id) : null;
        $post = $submission->get_post();
        return [
            'fields' => ['by_id' => $by_id, 'by_key' => $by_key, 'items' => $items],
            'form' => ['id' => $form->get_id(), 'title' => $form->get_title()],
            'submission' => [
                'id' => $submission->get_id(),
                'date' => $submission->get_date(),
                'datetime' => $post ? (string) ($post->post_date ?? '') : '',
                'user' => ['ip' => $submission->get_user_ip()],
            ],
            'page' => ['url' => $submission->get_page_url()],
            'user' => [
                'id' => $user ? (int) $user->ID : 0,
                'email' => $user ? (string) $user->user_email : '',
                'display_name' => $user ? (string) $user->display_name : '',
                'first_name' => $user ? (string) ($user->first_name ?? '') : '',
                'last_name' => $user ? (string) ($user->last_name ?? '') : '',
                'roles' => $user ? array_values((array) $user->roles) : [],
                'logged_in' => (bool) $user,
            ],
        ];
    }
}
