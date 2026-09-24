<?php
/**
 * Field-aware presentation of historical submission snapshots.
 *
 * @package ElzoForms\Submission
 */

namespace ElzoForms\Submission;

use ElzoForms\Field\Field;
use ElzoForms\Field\Field_Text;
use ElzoForms\Field\Field_Type;
use ElzoForms\Form\Form;

defined('ABSPATH') || exit;

class Submission_Field_Presenter {

    /** @var array Presentation context; optionally contains a Form object. */
    private array $context;

    /** @var array|null Current field definitions, loaded only for legacy snapshots. */
    private ?array $form_fields = null;

    /**
     * @param array $context Channel (admin/email), form/submission IDs and optionally a current form.
     */
    public function __construct(array $context = []) {
        $this->context = array_merge(['channel' => 'admin'], $context);
    }

    /**
     * Render safe HTML. Field implementations own output escaping.
     *
     * A stored type is authoritative even if its extension is unavailable;
     * only snapshots without a type can consult the current form.
     *
     * @param array $snapshot Stored submission field.
     * @return string Safe HTML ready for output.
     */
    public function render(array $snapshot): string {
        $type = Field_Type::sanitize_identifier($snapshot['type'] ?? null);
        if (!isset($snapshot['type']) || $snapshot['type'] === '') {
            $type = $this->resolve_legacy_type($snapshot);
        }

        $field = $type !== '' && Field_Type::is_registered($type)
            ? Field::from(array_merge($snapshot, ['type' => $type]))
            : new Field_Text(['type' => 'text']);

        return $field->render_submission_value($snapshot['value'] ?? null, array_merge($this->context, ['submission_field' => $snapshot]));
    }

    /**
     * Resolve legacy fields by stable ID first, then by an unambiguous key.
     *
     * @param array $snapshot Legacy snapshot.
     * @return string Canonical type or empty when unresolved.
     */
    private function resolve_legacy_type(array $snapshot): string {
        if ($this->form_fields === null) {
            $form = $this->context['form'] ?? null;
            if (!$form instanceof Form) {
                $form_id = $this->context['form_id'] ?? 0;
                $form = new Form(is_numeric($form_id) ? (int) $form_id : 0);
            }
            $this->form_fields = $form->get_fields();
        }

        foreach (['id', 'field_key'] as $identity) {
            $value = $snapshot[$identity] ?? '';
            if (!is_scalar($value) || (string) $value === '') {
                continue;
            }
            $matches = array_filter($this->form_fields, static function ($field) use ($identity, $value): bool {
                return is_array($field) && isset($field[$identity]) && is_scalar($field[$identity]) && (string) $field[$identity] === (string) $value;
            });
            if (count($matches) === 1) {
                return Field::from(reset($matches))->get_type();
            }
        }

        return '';
    }
}
