<?php
/**
 * The template for displaying radio field
 *
 * This template can be overridden by copying it to yourtheme/elzo-forms/field-types/field-radio.php.
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
 * @var array $d Field data prepared for rendering
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file is loaded via Field::render() → Template_Loader::load_template() (function scope). Variables here are not in global scope.

/**
 * Available variables (passed directly):
 * @var array  $options  Array of radio options
 * @var string $layout   Layout style (vertical, horizontal)
 * @var string $id       Field ID
 * @var string $name     Field name
 * @var string $class    CSS classes
 * @var mixed  $value    Current field value
 */

?>
<?php if($options){ ?>
    <div class="elzo-forms-radio-list-wrapper elzo-forms-field-options-layout-<?php echo esc_attr($layout); ?>" role="radiogroup"<?php if($label){ ?> aria-labelledby="<?php echo esc_attr($id); ?>-label"<?php } ?>>
        <?php $option_index = 0; foreach($options as $option): $option_index++;
            $option_id = $id . '-' . $option_index;
            $option_label = !empty($option['label']) ? $option['label'] : $option['value'];
            $option_description = !empty($option['description']) ? $option['description'] : '';
        ?>
            <div class="elzo-forms-radio-item-wrapper">
                <input type="radio" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($option['value']); ?>" id="<?php echo esc_attr($option_id); ?>" class="<?php echo esc_attr($class); ?>" <?php checked($value, $option['value'], true); ?> >
                <label for="<?php echo esc_attr($option_id); ?>">
                    <span class="elzo-forms-pseudo-radio-wrapper">
                        <span class="elzo-forms-pseudo-radio"></span>
                    </span>
                    <?php if($option_description){ ?>
                        <span class="elzo-forms-label-text-wrapper">
                            <span class="elzo-forms-label-heading"><?php echo wp_kses_post($option_label); ?></span>
                            <span class="elzo-forms-label-description"><?php echo wp_kses_post($option_description); ?></span>
                        </span>
                    <?php } else { ?>
                        <span class="elzo-forms-label-text"><?php echo wp_kses_post($option_label); ?></span>
                    <?php } ?>
                </label>
            </div>
        <?php endforeach; ?>
    </div>
<?php } ?>
