<?php
/**
 * The template for displaying text input field
 *
 * This template can be overridden by copying it to yourtheme/elzo-forms/field-types/field-text.php.
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
 * Available variables (extracted from $field_data):
 * @var string $id Field ID
 * @var string $name Field name
 * @var string $class Field CSS classes
 * @var string $field_subtype Field subtype (text, email, tel, etc.)
 * @var string $placeholder Placeholder text
 * @var string $value Field value
 * @var bool $required Whether field is required
 * @var array $logic_rules Logic rules if any
 * @var string $input_prepend Prepend text
 * @var string $input_append Append text
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

?>
<?php if($input_prepend || $input_append){ ?>
    <div class="elzo-forms-field-input-group">
<?php } ?>
<?php if($input_prepend){ ?>
    <label for="<?php echo esc_attr($id); ?>" class="elzo-forms-field-prepend"><?php echo esc_html($input_prepend); ?></label>
<?php } ?>
<input 
    type="<?php echo esc_attr($field_subtype); ?>" 
    name="<?php echo esc_attr($name); ?>" 
    class="<?php echo esc_attr($class); ?>" 
    id="<?php echo esc_attr($id); ?>" 
    placeholder="<?php echo esc_attr($placeholder); ?>" 
    <?php if (!empty($described_by)) { ?>aria-describedby="<?php echo esc_attr($described_by); ?>"<?php } ?>
    <?php if(!empty($autocomplete)){ ?>autocomplete="<?php echo esc_attr($autocomplete); ?>"<?php } ?>
    <?php if($required && !$logic_rules){ ?>required<?php } ?> 
    value="<?php echo esc_attr($value); ?>"
>
<?php if($input_prepend || $input_append){ ?>
    <div class="elzo-forms-input-group-pseudo-border"></div>
<?php } ?>
<?php if($input_append){ ?>
    <label for="<?php echo esc_attr($id); ?>" class="elzo-forms-field-append"><?php echo esc_html($input_append); ?></label>
<?php } ?>
<?php if($input_prepend || $input_append){ ?>
    </div>
<?php } ?>
