<?php
/**
 * The template for displaying textarea field
 *
 * This template can be overridden by copying it to yourtheme/elzo-forms/field-types/field-textarea.php.
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
 * @var string $name        Field name
 * @var string $class       CSS classes
 * @var string $id          Field ID
 * @var string $placeholder Placeholder text
 * @var bool   $required    Whether the field is required
 * @var array  $logic_rules Conditional logic rules
 * @var int    $rows_amount Number of textarea rows
 * @var mixed  $value       Field value
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

?>
<textarea name="<?php echo esc_attr($name); ?>" class="<?php echo esc_attr($class); ?>" id="<?php echo esc_attr($id); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" <?php echo $required && !$logic_rules ? 'required' : ''; ?><?php echo !empty($max_length) ? ' maxlength="' . esc_attr((string) $max_length) . '"' : ''; ?> rows="<?php echo esc_attr($rows_amount); ?>"><?php echo esc_textarea($value); ?></textarea>
