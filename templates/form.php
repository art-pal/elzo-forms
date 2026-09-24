<?php
/**
 * The template for displaying form wrapper
 *
 * This template can be overridden by copying it to yourtheme/elzo-forms/form.php.
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
 * @var \ElzoForms\Form\Form_Instance $form_instance Rendered instance; namespaces every HTML ID.
 * @var string $form_instance_id Instance ID.
 * @var string $form_attr_id HTML ID of the form element.
 * @var string $form_nonce_id HTML ID of the nonce input.
 * @var string $form_instance_suffix Deprecated: ID suffix of Elzo Forms 1.1 for repeated forms.
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is loaded via Template_Loader::load_template() (function scope), so variables here are function-scoped, not globals.

?>
<section class="elzo-forms-wrapper" <?php echo !empty($form_wrapper_styles) ? 'style="'.esc_attr($form_wrapper_styles).'"' : ''; ?>>
    <?php if(current_user_can('edit_post', $form_id)): ?>
        <div class="elzo-forms-admin-bar">
            <a href="<?php echo esc_url(get_edit_post_link($form_id)); ?>" class="elzo-forms-edit-form"><?php esc_html_e('Edit form', 'elzo-forms'); ?></a>
        </div>
        <?php if($form_post->post_status !== 'publish') { ?>
            <div class="elzo-forms-alert">
                <?php /* translators: %s: Link text to publish the form. */ ?>
                <span class="elzo-forms-draft-notice"><?php echo wp_kses_post(sprintf(__('This form is not published. Only users with permission to edit this form can view it. To make it public, please %s the form.', 'elzo-forms'), '<a href="'.esc_url(get_edit_post_link($form_id)).'" target="_blank">'.esc_html__('publish', 'elzo-forms').'</a>')); ?></span>
            </div>
        <?php } ?>
    <?php endif; ?>
    <form action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" method="post" class="<?php echo esc_attr($form_attr_class); ?>" id="<?php echo esc_attr($form_attr_id); ?>" data-ef-instance="<?php echo esc_attr($form_instance_id); ?>"<?php echo wp_kses($form_data_attrs_string, []); ?> novalidate>
        <input type="hidden" id="<?php echo esc_attr($form_nonce_id); ?>" name="elzo_forms_nonce" value="<?php echo esc_attr(wp_create_nonce('elzo_forms_action')); ?>">
        <?php wp_referer_field(); ?>
        
        <input type="hidden" name="action" value="elzo_forms_submit">
        <input type="hidden" name="elzo_form_id" value="<?php echo esc_attr((string) ($form_id ?? '')); ?>">
        <input type="hidden" name="elzo_form_time" value="<?php echo esc_attr(time()); ?>">
        <input type="hidden" name="elzo_form_settings" class="elzo-forms-settings" value='<?php echo esc_attr(wp_json_encode($form_settings, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>'>

        <div class="elzo-forms-steps">
            <?php if ($steps) { ?>
                <?php foreach ($steps as $step) {
                    $step_index = $step->get_index();
                    // Load step template
                    \ElzoForms\Utilities\Template_Loader::load_template('step.php', compact(
                        'step', 'step_index', 'steps_total', 'form_id', 'form_settings', 'texts_settings',
                        'form_instance', 'form_instance_suffix'
                    ));
                } ?>
            <?php } ?>
        </div>
    </form>
</section>
