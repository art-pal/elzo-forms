<?php
/**
 * Fixed recipient slots: submitted values cannot create additional recipients.
 *
 * @package ElzoForms\Variables
 */
namespace ElzoForms\Variables;

defined('ABSPATH') || exit;

final class Recipient_Templates {
    public const MAX_RECIPIENTS = 20;

    public static function split(string $templates): array {
        return array_values(array_filter(array_map('trim', (array) preg_split('/[,\r\n]+/', $templates)), static function ($slot) {
            return $slot !== '';
        }));
    }

    public static function normalize(string $templates): string {
        return implode(', ', array_unique(self::split(sanitize_textarea_field($templates))));
    }

    public static function resolve(string $templates, array $context): ValueResolutionResult {
        $slots = self::split($templates);
        $errors = [];
        $emails = [];
        if (count($slots) > self::MAX_RECIPIENTS) {
            return new ValueResolutionResult([], [['code' => 'too_many_notification_recipients']]);
        }
        $resolver = new ValueResolver();
        foreach ($slots as $slot) {
            $result = $resolver->resolve($slot, $context);
            $errors = array_merge($errors, $result->get_errors());
            $email = $result->get_value();
            if (!is_string($email) || preg_match('/[,\r\n]/', $email) || !is_email(trim($email))) {
                $errors[] = ['code' => 'invalid_notification_recipients'];
                continue;
            }
            $email = trim($email);
            $emails[$email] = $email;
        }
        if (!$emails) {
            $errors[] = ['code' => 'empty_notification_recipients'];
        }
        return new ValueResolutionResult(array_values($emails), $errors);
    }
}
