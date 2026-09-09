<?php
/**
 * The template for displaying button field
 *
 * This template can be overridden by copying it to yourtheme/elzo-forms/field-types/field-button.php.
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
 * @var string $button_type      Button type (button, submit, link)
 * @var string $button_title     Button title/text
 * @var string $button_url       URL for link type buttons
 * @var string $button_target    Link target (_self, _blank)
 * @var string $field_name       Field name for submit buttons
 */

if ($button_type === 'link') {
    // Render as link
    ?>
    <a href="<?php echo esc_url($button_url); ?>" 
       target="<?php echo esc_attr($button_target); ?>" 
       class="elzo-forms-button elzo-forms-button-link"
       <?php if ($button_target === '_blank') { ?>rel="noopener noreferrer"<?php } ?>>
        <?php echo esc_html($button_title); ?>
    </a>
    <?php
} else {
    // Render as button or submit
    ?>
    <button type="<?php echo esc_attr($button_type); ?>" 
            class="elzo-forms-button elzo-forms-button-<?php echo esc_attr($button_type); ?>"
            <?php if ($button_type === 'submit') { ?>name="<?php echo esc_attr($name); ?>"<?php } ?>>
        <?php echo esc_html($button_title); ?>
    </button>
    <?php
}
