<?php
// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included via admin settings/meta-box callback chains, so variables here are function-scoped, not globals.

$title_url = \ElzoForms\Utilities\Admin::get_pro_url('settings_automations_title');
$learn_more_url = \ElzoForms\Utilities\Admin::get_pro_url('settings_automations_button');
?>
<div class="elzo-forms-admin-search-section">
    <?php /* translators: %s is the plugin name. */ ?>
    <h2><?php printf(esc_html__('Automations are available in %s', 'elzo-forms'), '<a href="' . esc_url($title_url) . '" target="_blank" rel="noopener noreferrer">Elzo Forms PRO</a>'); ?></h2>
    <p>
        <?php esc_html_e('Build post-submission workflows that run only when your conditions match.', 'elzo-forms'); ?>
    </p>
    <ul class="ul-disc">
        <li><?php esc_html_e('Send targeted emails or adjust notification recipients for a specific submission.', 'elzo-forms'); ?></li>
        <li><?php esc_html_e('Send form data to external services with webhooks and custom field mapping.', 'elzo-forms'); ?></li>
        <li><?php esc_html_e('Redirect users, update submitted field values, or set and delete cookies.', 'elzo-forms'); ?></li>
        <li><?php esc_html_e('Combine field, page, user, cookie, URL, and date/time rules for precise workflows.', 'elzo-forms'); ?></li>
    </ul>
    <p>
        <a class="button button-primary" href="<?php echo esc_url($learn_more_url); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('Learn more', 'elzo-forms'); ?>
        </a>
    </p>
</div>
