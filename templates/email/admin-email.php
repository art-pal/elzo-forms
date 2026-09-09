<?php
/**
 * The template for displaying admin notification email
 *
 * This template can be overridden by copying it to yourtheme/elzo-forms/email/admin-email.php.
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
 * @var array $submission_data Submission data including fields
 * @var string $email_message Custom email message
 * @var array $email_buttons Action buttons rendered under the submission table
 * @var int $form_id ID of the submitted form
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is loaded via Template_Loader::load_template() (function scope), so variables here are function-scoped, not globals.

?>
<div style="background-color:#f5f5f5;padding:30px 20px">
    <div style="max-width:600px;margin:0 auto;padding:25px;background-color:#fff">
        <div style="border-bottom:1px solid #ddd;padding-bottom:20px">
            <strong style="font-size:1.5em"><?php echo esc_html(get_bloginfo('name')); ?></strong>
        </div>
        <?php if(!empty($email_message)){ ?>
            <div style="margin-top:20px"><?php echo wp_kses_post($email_message); ?></div>
        <?php } ?>
        <?php if(!empty($submission_data['fields'])){ ?>
            <div style="margin-top:20px;">
                <table style="width:100%;border-collapse:collapse;">
                    <?php foreach($submission_data['fields'] as $field){
                        // Apply filter to each field
                        $field = apply_filters('elzo_forms_admin_email_field', $field);

                        $admin_label = !empty($field['admin_label']) ? $field['admin_label'] : '';
                        $label = !empty($field['label']) ? $field['label'] : '';
                        $value = !empty($field['value']) ? is_array($field['value']) ? implode(', ', $field['value']) : $field['value'] : '-';
                    ?>
                        <tr>
                            <td style="padding:10px;border:1px solid #ddd;width:100px;padding-right:15px"><?php echo esc_html($admin_label ? $admin_label : $label); ?></td>
                            <td style="padding:10px;border:1px solid #ddd;"><?php echo esc_html($value); ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        <?php } ?>
        <?php if(!empty($email_buttons)){ ?>
            <div style="margin-top:20px">
                <?php foreach($email_buttons as $button){
                    $button_title = !empty($button['title']) ? $button['title'] : '';
                    $button_url = !empty($button['url']) ? $button['url'] : '';
                ?>
                    <a href="<?php echo esc_url($button_url); ?>" style="display:inline-block;padding:10px 20px;background-color:#0073aa;color:#fff;text-decoration:none;"><?php echo esc_html($button_title); ?></a>
                <?php } ?>
            </div>
        <?php } ?>
        <div style="margin-top:25px;padding-top:15px;border-top:1px solid #ddd">
            <?php
            /* translators: %s: Submission duration in seconds. */
            $submitted_in_text = sprintf(__('%s s', 'elzo-forms'), $submission_data['submitted_in']);
            ?>
            <?php esc_html_e('Submitted in', 'elzo-forms'); ?>: <?php echo esc_html($submitted_in_text); ?><br>
            <?php esc_html_e('User IP', 'elzo-forms'); ?>: <?php echo esc_html($submission_data['user']['ip']); ?><br>
            <?php if($submission_data['user']['user_id']){ ?>
                <?php esc_html_e('User ID', 'elzo-forms'); ?>: <a href="<?php echo esc_url(get_edit_user_link($submission_data['user']['user_id'])); ?>"><?php echo esc_html($submission_data['user']['user_id']); ?> &rarr;</a><br>
            <?php } ?>
            <?php esc_html_e('Form', 'elzo-forms'); ?>: <a href="<?php echo esc_url(get_edit_post_link($form_id)); ?>"><?php echo esc_html(get_the_title($form_id)); ?> &rarr;</a>
        </div>
    </div>
</div>