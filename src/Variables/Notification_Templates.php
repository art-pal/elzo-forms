<?php
/**
 * Notification destination formatting, separate from typed resolution.
 *
 * @package ElzoForms\Variables
 */
namespace ElzoForms\Variables;

use ElzoForms\Form\Form;
use ElzoForms\Submission\Submission;
use ElzoForms\Submission\Submission_Field_Presenter;
use ElzoForms\Utilities\Template_Loader;

defined('ABSPATH') || exit;

final class Notification_Templates {
    public static function prepare(Form $form, Submission $submission, string $recipients, string $subject, string $message, string $reply_to = ''): ValueResolutionResult {
        $context = Submission_Context::build($form, $submission);
        $resolver = new ValueResolver();
        $to = Recipient_Templates::resolve($recipients, $context);
        $title = $resolver->resolve($subject, self::text_context($context));
        $body = self::resolve_message($message, $context, $form, $submission);
        // A Reply-To that resolves to nothing usable only costs the header: the
        // notification itself must still reach its recipients.
        $reply = self::resolve_reply_to($reply_to, $context);
        $errors = $to->get_errors();
        $warnings = $reply->get_errors();
        foreach (array_merge($title->get_errors(), $body->get_errors()) as $error) {
            if (in_array($error['code'] ?? '', ['path_not_found', 'path_not_accessible'], true)) {
                $warnings[] = $error;
            } else {
                $errors[] = $error;
            }
        }
        return new ValueResolutionResult([
            'recipients' => $to->get_value(),
            'subject' => sanitize_text_field(self::text_value($title->get_value())),
            'message' => $body->get_value(),
            'fields_in_message' => self::has_fields_table($message),
            'reply_to' => $reply->get_value(),
        ], $errors, $warnings);
    }

    private static function resolve_reply_to(string $reply_to, array $context): ValueResolutionResult {
        if (trim($reply_to) === '') {
            return new ValueResolutionResult('');
        }
        $resolved = Recipient_Templates::resolve($reply_to, $context);
        $addresses = (array) $resolved->get_value();
        return new ValueResolutionResult((string) ($addresses[0] ?? ''), $resolved->get_errors());
    }

    public static function has_fields_table(string $message): bool {
        foreach ((new ReferenceTemplateInspector())->inspect($message)->get_references() as $reference) {
            if ($reference['path'] === 'fields.items') {
                return true;
            }
        }
        return false;
    }

    private static function text_context(array $context): array {
        foreach (['by_key', 'by_id'] as $index) {
            foreach ($context['fields'][$index] as $key => $value) {
                $context['fields'][$index][$key] = self::text_value($value);
            }
        }
        $context['fields']['items'] = implode("\n", array_map(static function ($field) {
            return (string) (($field['admin_label'] ?? '') ?: ($field['label'] ?? '')) . ': ' . self::text_value($field['value'] ?? '');
        }, $context['fields']['items']));
        $context['user']['roles'] = self::text_value($context['user']['roles']);
        return $context;
    }

    private static function text_value($value): string {
        if (is_array($value)) {
            return implode(', ', array_map([self::class, 'text_value'], $value));
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return is_scalar($value) ? (string) $value : '';
    }

    private static function resolve_message(string $message, array $context, Form $form, Submission $submission): ValueResolutionResult {
        $inspection = (new ReferenceTemplateInspector())->inspect($message);
        $errors = $inspection->get_syntax_errors();
        if ($errors) {
            return new ValueResolutionResult($message, $errors);
        }
        $presenter = new Submission_Field_Presenter([
            'channel' => 'email', 'form' => $form, 'form_id' => $form->get_id(), 'submission_id' => $submission->get_id(),
        ]);
        $resolver = new ValueResolver();
        $result = '';
        $offset = 0;
        foreach ($inspection->get_references() as $reference) {
            $result .= str_replace('\\{{', '{{', substr($message, $offset, $reference['start'] - $offset));
            $path = $reference['path'];
            $resolution = $resolver->resolve('{{ ' . $path . ' }}', $context);
            $errors = array_merge($errors, $resolution->get_errors());
            if ($path === 'fields.items') {
                $result .= Template_Loader::get_template('email/submission-fields.php', [
                    'submission_data' => $submission->to_array(), 'field_presenter' => $presenter,
                ]);
            } else {
                $result .= esc_html(self::text_value($resolution->get_value()));
            }
            $offset = $reference['start'] + $reference['length'];
        }
        $result .= str_replace('\\{{', '{{', substr($message, $offset));
        return new ValueResolutionResult(wp_kses_post($result), $errors);
    }
}
