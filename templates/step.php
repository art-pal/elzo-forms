<?php
/**
 * The template for displaying a single form step
 *
 * This template can be overridden by copying it to yourtheme/elzo-forms/step.php.
 *
 * HOWEVER, on occasion Elzo Forms will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         Plugin documentation
 * @package     ElzoForms\Templates
 * @version     1.1.0
 *
 * @var ElzoForms\Form\Step $step Step object
 * @var int $steps_total Total number of steps
 * @var array $texts_settings Text settings from form
 * @var string $form_instance_suffix DOM ID suffix for repeated form instances
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is loaded via Template_Loader::load_template() (function scope), so variables here are function-scoped, not globals.

$step_index = $step->get_index();

// Get step data as individual variables
$step_data = $step->get_data();
$fields = $step_data['fields'] ?? [];
$field_layout = $step_data['field_layout'] ?? [];

?>
<div class="elzo-forms-step <?php echo $step->is_first() ? 'active' : ''; ?>" <?php if (!$step->is_first()) { ?>style="display:none"<?php } ?>>
    <?php if ($steps_total > 1) { ?>
        <div class="elzo-forms-step-title-wrapper">
            <h3 class="elzo-forms-step-title"><?php echo esc_html($step->get_label()); ?></h3>
            <div class="elzo-forms-step-progress-text">
                <?php
                /* translators: 1: Current step number, 2: Total number of steps. */
                echo sprintf(esc_html__('Step %1$d of %2$d', 'elzo-forms'), esc_html($step_index + 1), esc_html($steps_total));
                ?>
            </div>
        </div>
    <?php } ?>
    
    <div class="elzo-forms-fields">
        <?php
        // Render fields using pre-calculated layout
        foreach ($field_layout as $layout_item) {
            $field = $layout_item['field'];
            $field_index = $layout_item['field_index'];
            $opens_row = $layout_item['opens_row'];
            $closes_row = $layout_item['closes_row'];
            $width_class = $layout_item['width_class'];
            $logic_rules = $field->get_logic_rules();

            // Open row if needed
            if ($opens_row) {
                echo '<div class="elzo-forms-row">';
            }

            // Open column if field has width
            if ($width_class) {
                echo '<div class="elzo-forms-column ' . esc_attr($width_class) . '" ' . ($logic_rules ? 'style="display:none"' : '') . '>';
            }

            // Load field template (supports theme override)
            \ElzoForms\Utilities\Template_Loader::load_template('field.php', $field->get_data([
                'form_settings' => $form_settings ?? [],
                'texts_settings' => $texts_settings ?? [],
                'form_id' => $form_id ?? null,
                'form_instance_suffix' => $form_instance_suffix ?? '',
            ]));

            // Close column if field has width
            if ($width_class) {
                echo '</div>';
            }

            // Close row if needed
            if ($closes_row) {
                echo '</div>';
            }
        }
        ?>
    </div>
    
    <div class="elzo-forms-step-alert-wrapper"></div>
    
    <div class="elzo-forms-step-footer elzo-forms-row">
        <?php if (!$step->is_first()): ?>
            <div class="elzo-forms-column elzo-forms-column-auto">
                <button type="button" class="elzo-forms-button elzo-forms-previous-step-button" title="<?php echo esc_attr($texts_settings['previous_step_button_title']); ?>">&larr;</button>
            </div>
        <?php endif; ?>
        <div class="elzo-forms-column elzo-forms-flex-1">
            <?php if ($steps_total === $step_index + 1): ?>
                <?php if(!$step->has_submit_button()){ ?>
                    <button type="submit" class="elzo-forms-button elzo-forms-submit-button elzo-forms-w-100"><?php echo esc_html($texts_settings['submit_button_text']); ?></button>
                <?php } ?>
            <?php else: ?>
                <button type="button" class="elzo-forms-button elzo-forms-next-step-button elzo-forms-w-100"><?php echo esc_html($texts_settings['next_step_button_text']); ?></button>
            <?php endif; ?>
        </div>
    </div>
</div>
