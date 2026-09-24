<?php
/**
 * Content Field class (non-input field for display only).
 *
 * @package ElzoForms\Field
 */

namespace ElzoForms\Field;

use ElzoForms\Form\Form_Data_Normalizer;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Field_Content extends Field {

    /**
     * Get field defaults.
     */
    protected function get_defaults(): array {
        return array_merge(parent::get_defaults(), [
            'type' => 'content',
            'required' => false, // Content fields are never required
            'content' => '',
        ]);
    }

    /**
     * Validate field value.
     *
     * @param mixed $value Field value
     * @return true
     */
    public function validate($value) {
        // Content fields don't have values to validate
        return true;
    }

    /**
     * Sanitize field value.
     *
     * @param mixed $value Field value
     * @return string
     */
    public function sanitize($value) {
        // Content fields don't accept user input
        return '';
    }

    /**
     * Check if field is required.
     * Content fields are never required.
     */
    public function is_required(): bool {
        return false;
    }

    /**
     * Check if field is read-only.
     * Content fields don't accept user input.
     */
    public function is_read_only(): bool {
        return true;
    }

    /**
     * Content fields are not comparable in conditional logic.
     *
     * @return array|null
     */
    public function get_logic_operators(): ?array {
        return [];
    }

    /**
     * Get the content as HTML, turned back from its stored representation.
     *
     * @return string
     */
    public function get_content_html(): string {
        $content = $this->get('content', '');

        return is_scalar($content) ? Form_Data_Normalizer::decode_content((string) $content) : '';
    }

    /**
     * Prepare all data for template rendering.
     *
     * @param array $context Additional context (form_settings, etc.)
     * @return array
     */
    public function get_data(array $context = []): array {
        $data = parent::get_data($context);

        // Add content field-specific data
        $data['content'] = $this->get_content_html();
        $data['is_html'] = $this->get('is_html', false);

        return $data;
    }

    /**
     * Render field-specific settings in admin for a specific tab.
     *
     * @param string $tab The settings tab (general, view, logic, admin, etc.)
     */
    public function render_field_settings(string $tab): void {
        $field = $this->to_array();
        $field_id = $this->get_id();
        $step_index = $this->get('step_index', 0);
        $field_index = $this->get('index', 0);

        if ($tab === 'general') {
            $content = $this->get_content_html();
            $is_html = $this->get('is_html', false);
            ?>
            <div class="elzo-forms-field-control-group">
                <div class="elzo-forms-row elzo-forms-row-auto-cols elzo-forms-justify-content-between">
                    <div class="elzo-forms-column">
                        <label for="elzo-forms-field-content-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Content', 'elzo-forms'); ?></label>
                    </div>
                    <div class="elzo-forms-column">
                        <label for="elzo-forms-field-is-html-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label">
                            <input type="checkbox" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][is_html]" id="elzo-forms-field-is-html-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-check elzo-forms-textarea-is-html-check" <?php checked($is_html); ?>>
                            <?php esc_html_e('Edit as HTML', 'elzo-forms'); ?>
                        </label>
                    </div>
                </div>
                <textarea name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][content]" id="elzo-forms-field-content-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control <?php echo $is_html ? '' : 'elzo-forms-tinymce-field-control'; ?>" rows="10"><?php echo esc_textarea($content); ?></textarea>
            </div>
            <?php
        }

        // Call parent to trigger action hooks for third-party extensions
        parent::render_field_settings($tab);
    }
}
