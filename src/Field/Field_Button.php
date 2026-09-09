<?php
/**
 * Button Field class.
 *
 * @package ElzoForms\Field
 */

namespace ElzoForms\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Field_Button extends Field {

    /**
     * Get button types with translated labels.
     */
    protected function get_button_types(): array {
        return [
            'submit' => __('Submit', 'elzo-forms'),
            'button' => __('Button', 'elzo-forms'),
            'link'   => __('Link', 'elzo-forms'),
        ];
    }

    /**
     * Get field defaults.
     */
    protected function get_defaults(): array {
        return array_merge(parent::get_defaults(), [
            'type' => 'button',
            'button_type' => 'submit',
            'button_title' => '',
            'button_url' => '',
            'button_target' => '_self',
        ]);
    }

    /**
     * Check if field is read-only.
     * Buttons don't accept user input.
     */
    public function is_read_only(): bool {
        return true;
    }

    /**
     * Validate field value.
     *
     * @param mixed $value Field value
     * @return true
     */
    public function validate($value) {
        // Buttons don't have values to validate
        return true;
    }

    /**
     * Sanitize field value.
     *
     * @param mixed $value Field value
     * @return string
     */
    public function sanitize($value) {
        // Buttons don't accept user input
        return '';
    }

    /**
     * Check if field is required.
     * Buttons are never required.
     */
    public function is_required(): bool {
        return false;
    }

    /**
     * Buttons are not comparable in conditional logic.
     *
     * @return array|null
     */
    public function get_logic_operators(): ?array {
        return [];
    }

    /**
     * Get all data for template rendering.
     *
     * @param array $context Additional context (form_settings, etc.)
     * @return array
     */
    public function get_data(array $context = []): array {
        $data = parent::get_data($context);

        // Add button-specific data
        $data['button_type'] = $this->get('button_type', 'button');
        $data['button_title'] = $this->get('button_title', '');
        $data['button_url'] = $this->get('button_url', '');
        $data['button_target'] = $this->get('button_target', '_self');

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
            $button_type = $this->get('button_type', 'button');
            $button_title = $this->get('button_title', '');
            $button_url = $this->get('button_url', '');
            $button_target = $this->get('button_target', '_self');
            ?>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-button-type-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Button Type', 'elzo-forms'); ?></label>
                <select name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][button_type]" id="elzo-forms-field-button-type-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control elzo-forms-field-button-type-select">
                    <?php foreach($this->get_button_types() as $type => $label): ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($button_type, $type); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="elzo-forms-field-control-group">
                <label for="elzo-forms-field-button-title-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Button Title', 'elzo-forms'); ?></label>
                <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][button_title]" value="<?php echo esc_attr($button_title); ?>" id="elzo-forms-field-button-title-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
            </div>
            <div class="elzo-forms-field-control-group elzo-forms-field-button-url-group" data-ef-logic='[[{"type":"input","operator":"==","settings":{"id":"elzo-forms-field-button-type-<?php echo esc_attr($field_id); ?>","value":"link"}}]]' <?php echo $button_type === 'link' ? '' : 'style="display:none;"'; ?>>
                <label for="elzo-forms-field-button-url-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('URL', 'elzo-forms'); ?></label>
                <input type="url" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][button_url]" value="<?php echo esc_attr($button_url); ?>" id="elzo-forms-field-button-url-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" placeholder="https://">
            </div>
            <div class="elzo-forms-field-control-group elzo-forms-field-button-target-group" data-ef-logic='[[{"type":"input","operator":"==","settings":{"id":"elzo-forms-field-button-type-<?php echo esc_attr($field_id); ?>","value":"link"}}]]' <?php echo $button_type === 'link' ? '' : 'style="display:none;"'; ?>>
                <label for="elzo-forms-field-button-target-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Target', 'elzo-forms'); ?></label>
                <select name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][button_target]" id="elzo-forms-field-button-target-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control">
                    <option value="_self" <?php selected($button_target, '_self'); ?>><?php esc_html_e('Same window', 'elzo-forms'); ?></option>
                    <option value="_blank" <?php selected($button_target, '_blank'); ?>><?php esc_html_e('New window', 'elzo-forms'); ?></option>
                </select>
            </div>
            <?php
        }

        parent::render_field_settings($tab);
    }
}
