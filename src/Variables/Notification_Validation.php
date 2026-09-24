<?php
/**
 * Design-time validation for the fixed notification variable destinations.
 *
 * @package ElzoForms\Variables
 */
namespace ElzoForms\Variables;

use ElzoForms\Field\Field_Type;

defined('ABSPATH') || exit;

final class Notification_Validation {
    public static function validate(array $templates, ?array $fields = null): array {
        $inspector = new ReferenceTemplateInspector();
        $definitions = array_column(Catalog::items(), null, 'path');
        $field_paths = [];
        if ($fields !== null) {
            foreach ($fields as $field) {
                if (in_array(Field_Type::get_base_type((string) ($field['type'] ?? '')), ['button', 'content'], true)) {
                    continue;
                }
                foreach (['field_key' => 'by_key', 'id' => 'by_id'] as $key => $index) {
                    $path = 'fields.' . $index . '.' . (string) ($field[$key] ?? '');
                    if ($inspector->is_valid_path($path)) {
                        $field_paths[$path][] = Catalog::field_type($field);
                    }
                }
            }
        }
        $errors = [];
        $labels = [
            'email_notification_recipients' => __('Email notification recipients', 'elzo-forms'),
            'email_notification_reply_to' => __('Email notification Reply-To', 'elzo-forms'),
            'email_notification_subject' => __('Email notification subject', 'elzo-forms'),
            'email_notification_message' => __('Email notification message', 'elzo-forms'),
        ];
        foreach ($labels as $key => $label) {
            if (!array_key_exists($key, $templates)) {
                continue;
            }
            $value = (string) $templates[$key];
            $inspection = $inspector->inspect($value, $key);
            foreach ($inspection->get_syntax_errors() as $error) {
                /* translators: %s: notification setting label. */
                $errors[] = self::error($key, 'invalid_reference_syntax', sprintf(__('The variable syntax in "%s" is invalid.', 'elzo-forms'), $label));
            }
            if ($inspection->has_syntax_errors()) {
                continue;
            }
            foreach ($inspection->get_references() as $reference) {
                $path = $reference['path'];
                $field_reference = preg_match('/^fields\.(by_key|by_id)\.[A-Za-z0-9_-]+$/', $path) === 1;
                $type = $definitions[$path]['type'] ?? null;
                if ($field_reference) {
                    if ($fields === null) {
                        // Global templates acquire their field schema on a form save.
                        $type = 'string';
                    } elseif (count($field_paths[$path] ?? []) === 1) {
                        $type = $field_paths[$path][0];
                    }
                }
                if ($type === null) {
                    /* translators: 1: variable path, 2: notification setting label. */
                    $errors[] = self::error($key, 'unknown_notification_variable', sprintf(__('Variable "%1$s" in "%2$s" is unavailable or its field key is not unique.', 'elzo-forms'), $path, $label));
                } elseif (self::is_address_setting($key) && $type !== 'string') {
                    /* translators: %s: variable path. */
                    $errors[] = self::error($key, 'invalid_recipient_variable_type', sprintf(__('Variable "%s" cannot provide a single email address.', 'elzo-forms'), $path));
                }
            }
            if (self::is_address_setting($key)) {
                $slots = Recipient_Templates::split($value);
                // Reply-To addresses one person, so it holds a single slot.
                if ($key === 'email_notification_reply_to' && count($slots) > 1) {
                    $errors[] = self::error($key, 'too_many_notification_reply_to', __('Configure one Reply-To address.', 'elzo-forms'));
                } elseif ($key === 'email_notification_recipients' && count($slots) > Recipient_Templates::MAX_RECIPIENTS) {
                    /* translators: %d: maximum number of configured recipients. */
                    $errors[] = self::error($key, 'too_many_notification_recipients', sprintf(__('Configure no more than %d notification recipients.', 'elzo-forms'), Recipient_Templates::MAX_RECIPIENTS));
                }
                foreach ($slots as $slot) {
                    if (!$inspector->contains_reference($slot) && !is_email($slot)) {
                        $errors[] = $key === 'email_notification_reply_to'
                            ? self::error($key, 'invalid_notification_reply_to', __('The Reply-To address must be one email address or a variable template.', 'elzo-forms'))
                            : self::error($key, 'invalid_notification_recipients', __('Each notification recipient must be one email address or a variable template.', 'elzo-forms'));
                    }
                }
            }
        }
        return $errors;
    }

    private static function is_address_setting(string $key): bool {
        return in_array($key, ['email_notification_recipients', 'email_notification_reply_to'], true);
    }

    private static function error(string $path, string $code, string $message): array {
        return ['path' => $path, 'code' => $code, 'message' => $message];
    }
}
