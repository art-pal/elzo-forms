<?php
/**
 * Rendered form instance.
 *
 * Every render of a form is a separate instance with its own runtime ID, and
 * that ID is the namespace of every HTML ID the instance generates: the form,
 * its nonce, step alerts, and each field's control, wrapper, label,
 * descriptions and options. Two different forms that share field IDs, a
 * duplicated form next to its original, and one form embedded twice can
 * therefore share a page without sharing an HTML ID.
 *
 * Stored field IDs, input names and data-ef-field-id never change; only the
 * HTML IDs of the rendered document do.
 *
 * All IDs of one request are recorded in a document registry. An ID an author
 * chose in the builder (a form or field custom ID) is used as long as nothing
 * in the document holds it yet; a later instance falls back to its generated
 * ID. The registry also backs up the naming scheme itself: should a generated
 * ID already be taken, it receives a numeric suffix instead of repeating.
 *
 * @package ElzoForms\Form
 */

namespace ElzoForms\Form;

// Exit if accessed directly
defined('ABSPATH') || exit;

final class Form_Instance {

    /** Prefix of instance IDs, which also keeps the form element's historical ID. */
    private const PREFIX = 'elzo-forms-form-';

    /** @var array<string, true> HTML IDs used in the current document. */
    private static $document_ids = [];

    /** @var array<int, int> Instances rendered so far, by form ID. */
    private static $instance_counts = [];

    /** @var string */
    private $id;

    /** @var int */
    private $form_id;

    /** @var int */
    private $number;

    /** @var array<string, string> HTML IDs of this instance, by element key. */
    private $element_ids = [];

    /**
     * @param string $id Instance ID.
     * @param int $form_id Form ID.
     * @param int $number Position among the renders of the same form, from 1.
     */
    private function __construct(string $id, int $form_id, int $number) {
        $this->id = $id;
        $this->form_id = $form_id;
        $this->number = $number;
    }

    /**
     * Start a new instance of a form in the current document.
     *
     * The first render of a form is "elzo-forms-form-{form ID}", later ones
     * add "-2", "-3" and so on.
     *
     * @param int $form_id Form ID.
     * @return self
     */
    public static function create(int $form_id): self {
        $form_id = max(0, $form_id);
        $number = self::$instance_counts[$form_id] ?? 0;

        do {
            $number++;
            $id = self::PREFIX . $form_id . ($number > 1 ? '-' . $number : '');
        } while (isset(self::$document_ids[$id]));

        self::$instance_counts[$form_id] = $number;
        self::$document_ids[$id] = true;

        return new self($id, $form_id, $number);
    }

    /**
     * Forget every ID of the current document.
     *
     * A request renders one document, so this is only needed where several
     * independent documents are built in one process, such as tests.
     *
     * @return void
     */
    public static function reset_document(): void {
        self::$document_ids = [];
        self::$instance_counts = [];
    }

    /**
     * Get the instance ID, the namespace of the instance's HTML IDs.
     *
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Get the ID of the rendered form.
     *
     * @return int
     */
    public function get_form_id(): int {
        return $this->form_id;
    }

    /**
     * Get the position of this instance among the renders of its form.
     *
     * @return int 1 for the first render.
     */
    public function get_number(): int {
        return $this->number;
    }

    /**
     * Get the suffix the templates of Elzo Forms 1.1 appended to repeated IDs.
     *
     * Kept for template overrides written for that version.
     *
     * @return string Empty for the first instance, "-{number}" otherwise.
     */
    public function get_legacy_suffix(): string {
        return $this->number > 1 ? '-' . $this->number : '';
    }

    /**
     * Get the HTML ID of the form element.
     *
     * @param string $custom_id Form custom ID from the form settings.
     * @return string
     */
    public function form_element_id(string $custom_id = ''): string {
        return $this->element('form', $this->id, $custom_id);
    }

    /**
     * Get the HTML ID of an element that belongs to the form itself.
     *
     * @param string $name Element name, such as "nonce".
     * @return string "{instance}-{name}".
     */
    public function element_id(string $name): string {
        $name = self::token($name);

        return $this->element('element:' . $name, $this->id . '-' . $name);
    }

    /**
     * Get the HTML ID of a step's alert region.
     *
     * @param int $step_index Step index.
     * @return string
     */
    public function step_alert_id(int $step_index): string {
        return $this->element('step:' . $step_index . ':alert', $this->id . '-step-' . $step_index . '-alert');
    }

    /**
     * Get the HTML ID of a field element.
     *
     * The field control is "{instance}-field-{field}", related elements append
     * their part: "-wrapper", "-label", "-description", "-help",
     * "-option-{n}" and so on.
     *
     * @param mixed $field_id Stored field ID.
     * @param string $part Related element, or an empty string for the control.
     * @param string $custom_id ID the author chose for this element, if any.
     * @return string
     */
    public function field_element_id($field_id, string $part = '', string $custom_id = ''): string {
        $field_id = is_scalar($field_id) ? (string) $field_id : '';
        $part = $part !== '' ? self::token($part) : '';
        $generated = $this->id . '-field-' . self::token($field_id) . ($part !== '' ? '-' . $part : '');

        return $this->element('field:' . $field_id . ':' . $part, $generated, $custom_id);
    }

    /**
     * Resolve the HTML ID of an element once per instance.
     *
     * @param string $key Element key within the instance.
     * @param string $generated Generated ID.
     * @param string $custom_id Author-chosen ID, used while it is valid and free.
     * @return string
     */
    private function element(string $key, string $generated, string $custom_id = ''): string {
        if (isset($this->element_ids[$key])) {
            return $this->element_ids[$key];
        }

        $custom_id = trim($custom_id);
        if (self::is_valid_id($custom_id) && !isset(self::$document_ids[$custom_id])) {
            $id = $custom_id;
        } elseif ($key === 'form') {
            // The instance ID was reserved for the form element when the
            // instance was created.
            $id = $generated;
        } else {
            $id = self::unique($generated);
        }

        self::$document_ids[$id] = true;
        $this->element_ids[$key] = $id;

        return $id;
    }

    /**
     * Get an ID nothing in the document uses yet.
     *
     * @param string $id Requested ID.
     * @return string
     */
    private static function unique(string $id): string {
        $candidate = $id;
        $suffix = 2;

        while (isset(self::$document_ids[$candidate])) {
            $candidate = $id . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Whether a value can be used as an HTML ID.
     *
     * @param string $id Candidate.
     * @return bool
     */
    private static function is_valid_id(string $id): bool {
        return $id !== '' && !preg_match('/\s/', $id);
    }

    /**
     * Turn a stored identifier into an ID segment.
     *
     * Field IDs are free text. Letters, digits, hyphens and underscores are
     * kept, so the ID stays readable; other characters become hyphens. Two
     * identifiers that map to the same segment still get different IDs
     * through the document registry.
     *
     * @param string $value Identifier.
     * @return string
     */
    private static function token(string $value): string {
        $token = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $value), '-');

        return $token !== '' ? $token : 'x' . substr(md5($value), 0, 8);
    }
}
