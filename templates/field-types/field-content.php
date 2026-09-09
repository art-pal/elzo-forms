<?php
/**
 * The template for displaying content field
 *
 * This template can be overridden by copying it to yourtheme/elzo-forms/field-types/field-content.php.
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
 * @var string $content  Content to display (supports shortcodes)
 */

?>
<?php if($content){ ?>
    <div class="elzo-forms-field-content"><?php echo wp_kses_post(do_shortcode($content)); ?></div>
<?php } ?>