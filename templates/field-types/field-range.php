<?php
/**
 * The template for displaying range slider field
 *
 * This template can be overridden by copying it to yourtheme/elzo-forms/field-types/field-range.php.
 *
 * HOWEVER, on occasion Elzo Forms will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         Plugin documentation
 * @package     ElzoForms\Templates
 * @version     1.2.0
 *
 * @var array $d Field data prepared for rendering
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Available variables (passed directly):
 * @var string $range_type             Range type (point, range_1, range_2)
 * @var int    $min_value              Minimum value
 * @var int    $max_value              Maximum value
 * @var string $point_1                Label for point 1
 * @var string $point_2                Label for point 2
 * @var string $point_3                Label for point 3
 * @var int    $range_progress_left    Progress bar left position (%)
 * @var int    $range_progress_right   Progress bar right position (%)
 * @var int    $value_min              Minimum value (for range_2)
 * @var int    $value_max              Maximum value
 * @var int    $step                   Step increment
 * @var string $id                     Field ID
 * @var string $min_input_id           HTML ID of the minimum number input
 * @var string $max_input_id           HTML ID of the maximum number input
 * @var string $value_input_id         HTML ID of the hidden input that submits a range_2 value
 * @var string $label_id               HTML ID of the field label
 * @var string $described_by           IDs of the texts that describe the field
 * @var string $name                   Field name
 * @var bool   $hide_text_inputs       Whether to hide text inputs
 * @var string $input_prepend          Text to prepend to input
 * @var string $input_append           Text to append to input
 * @var string $class                  CSS classes
 * @var bool   $required               Whether the field is required
 * @var array  $logic_rules            Conditional logic rules
 */

?>
<div class="elzo-forms-range-slider-wrapper elzo-forms-range-type-<?php echo esc_attr($range_type); ?>">
    <div class="elzo-forms-range-slider-header elzo-forms-d-flex elzo-forms-justify-content-between">
        <span><?php echo esc_html($min_value); ?></span>
        <span><?php echo esc_html($point_1); ?></span>
        <span><?php echo esc_html($point_2); ?></span>
        <span><?php echo esc_html($point_3); ?></span>
        <span><?php echo esc_html($max_value); ?></span>
    </div>
    <div class="elzo-forms-range-slider">
        <?php if($range_type !== 'point'){ ?>
            <div class="elzo-forms-range-slider-progress" style="left:<?php echo esc_attr($range_progress_left); ?>%;right:<?php echo esc_attr($range_progress_right); ?>%;"></div>
        <?php } ?>
        <?php if($range_type === 'range_2'){ ?>
            <?php // The label names the first slider; the value goes out through the hidden input below. ?>
            <input type="range" id="<?php echo esc_attr($id); ?>" class="elzo-forms-range-slider-min" <?php if (!empty($described_by)) { ?>aria-describedby="<?php echo esc_attr($described_by); ?>"<?php } ?> value="<?php echo esc_attr($value_min); ?>" step="<?php echo esc_attr($step); ?>" min="<?php echo esc_attr($min_value); ?>" max="<?php echo esc_attr($max_value); ?>">
        <?php } ?>
        <input type="range" <?php echo $range_type !== 'range_2' ? 'id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . (!empty($described_by) ? ' aria-describedby="' . esc_attr($described_by) . '"' : '') : ($label ? 'aria-labelledby="' . esc_attr($label_id) . '"' : ''); ?> class="elzo-forms-range-slider-max" value="<?php echo esc_attr($value_max); ?>" step="<?php echo esc_attr($step); ?>" min="<?php echo esc_attr($min_value); ?>" max="<?php echo esc_attr($max_value); ?>">
    </div>
    <?php if(!$hide_text_inputs){ ?>
        <div class="elzo-forms-range-slider-footer elzo-forms-row elzo-forms-row-auto-cols elzo-forms-justify-content-between">
            <?php if($range_type === 'range_2'){ ?>
                <div class="elzo-forms-column">
                    <?php if($input_prepend || $input_append){ ?>
                        <div class="elzo-forms-field-input-group">
                        <?php if($input_prepend){ ?>
                            <label for="<?php echo esc_attr($min_input_id); ?>" class="elzo-forms-field-prepend"><?php echo esc_html($input_prepend); ?></label>
                        <?php } ?>
                    <?php } ?>
                    <input type="number" id="<?php echo esc_attr($min_input_id); ?>" <?php if (!empty($described_by)) { ?>aria-describedby="<?php echo esc_attr($described_by); ?>"<?php } ?> class="elzo-forms-field-control elzo-forms-range-slider-min-input<?php echo $input_prepend || $input_append ? ' elzo-forms-field-input-group-input' : ''; ?>" value="<?php echo esc_attr($value_min); ?>" placeholder="<?php echo esc_attr($value_min); ?>" step="<?php echo esc_attr($step); ?>" min="<?php echo esc_attr($min_value); ?>" max="<?php echo esc_attr($max_value); ?>">
                    <?php if($input_prepend || $input_append){ ?>
                        <div class="elzo-forms-input-group-pseudo-border"></div>
                        <?php if($input_append){ ?>
                            <label for="<?php echo esc_attr($min_input_id); ?>" class="elzo-forms-field-append"><?php echo esc_html($input_append); ?></label>
                        <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
            <div class="elzo-forms-column">
                <?php if($input_prepend || $input_append){ ?>
                    <div class="elzo-forms-field-input-group">
                    <?php if($input_prepend){ ?>
                        <label for="<?php echo esc_attr($max_input_id); ?>" class="elzo-forms-field-prepend"><?php echo esc_html($input_prepend); ?></label>
                    <?php } ?>
                <?php } ?>
                <input type="number" id="<?php echo esc_attr($max_input_id); ?>" <?php if (!empty($described_by)) { ?>aria-describedby="<?php echo esc_attr($described_by); ?>"<?php } ?> class="elzo-forms-field-control elzo-forms-range-slider-max-input<?php echo $input_prepend || $input_append ? ' elzo-forms-field-input-group-input' : ''; ?>" value="<?php echo esc_attr($value_max); ?>" placeholder="<?php echo esc_attr($value_max); ?>" step="<?php echo esc_attr($step); ?>" min="<?php echo esc_attr($min_value); ?>" max="<?php echo esc_attr($max_value); ?>">
                <?php if($input_prepend || $input_append){ ?>
                    <div class="elzo-forms-input-group-pseudo-border"></div>
                    <?php if($input_append){ ?>
                        <label for="<?php echo esc_attr($max_input_id); ?>" class="elzo-forms-field-append"><?php echo esc_html($input_append); ?></label>
                    <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
    <?php if($range_type === 'range_2'){ ?>
        <input type="hidden" name="<?php echo esc_attr($name); ?>" class="<?php echo esc_attr($class); ?> elzo-forms-range-slider-real-input" id="<?php echo esc_attr($value_input_id); ?>" <?php echo $required && !$logic_rules ? 'required' : ''; ?> value="<?php echo esc_attr($range_type === 'range_2' ? $value_min . ' - ' . $value_max : $value_max); ?>" step="<?php echo esc_attr($step); ?>" min="<?php echo esc_attr($min_value); ?>" max="<?php echo esc_attr($max_value); ?>">
    <?php } ?>
</div>
