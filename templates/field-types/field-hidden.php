<?php
/**
 * The template for displaying hidden field
 *
 * This template can be overridden by copying it to yourtheme/elzo-forms/field-types/field-hidden.php.
 *
 * HOWEVER, on occasion Elzo Forms will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         Plugin documentation
 * @package     ElzoForms\Templates
 * @version     1.0.0
 *
 * @var array $d Field data prepared for rendering
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Available variables (passed directly):
 * @var string $name        Field name
 * @var string $class       CSS classes
 * @var string $id          Field ID
 * @var bool   $required    Whether the field is required
 * @var array  $logic_rules Conditional logic rules
 * @var mixed  $value       Field value
 */

?>
<input type="hidden" name="<?php echo esc_attr($name); ?>" class="<?php echo esc_attr($class); ?>" id="<?php echo esc_attr($id); ?>" <?php echo $required && !$logic_rules ? 'required' : ''; ?> value="<?php echo esc_attr($value); ?>">