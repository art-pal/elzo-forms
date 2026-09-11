<?php
/**
 * Free-plugin presentation of Elzo Forms Automations.
 */

defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Include-template scope variables, not plugin globals.
$automation_upsell_context = isset($automation_upsell_context) && $automation_upsell_context === 'global' ? 'global' : 'form';
$automation_upsell_title_url = isset($automation_upsell_title_url) ? (string) $automation_upsell_title_url : '';
$automation_upsell_learn_more_url = isset($automation_upsell_learn_more_url) ? (string) $automation_upsell_learn_more_url : '';
$automation_upsell_description = $automation_upsell_context === 'global'
    ? __('Create reusable workflows across forms: define when a submission matches, then choose one or more actions to run. Global automations run before form-level workflows.', 'elzo-forms')
    : __('For this form, define when a saved, valid submission matches, then choose one or more actions to run.', 'elzo-forms');
?>
<section class="elzo-forms-automation-upsell" aria-labelledby="elzo-forms-automation-upsell-title-<?php echo esc_attr($automation_upsell_context); ?>">
    <header class="elzo-forms-automation-upsell-header">
        <span class="elzo-forms-automation-upsell-mark">
            <?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('logic'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?>
        </span>
        <div class="elzo-forms-automation-upsell-heading">
            <a class="elzo-forms-automation-upsell-eyebrow" href="<?php echo esc_url($automation_upsell_title_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Elzo Forms PRO', 'elzo-forms'); ?></a>
            <?php if ($automation_upsell_context === 'form'): ?>
                <h3 id="elzo-forms-automation-upsell-title-form" class="elzo-forms-automation-upsell-title"><?php esc_html_e('Build workflows with a simple IF–THEN flow', 'elzo-forms'); ?></h3>
            <?php else: ?>
                <h2 id="elzo-forms-automation-upsell-title-global" class="elzo-forms-automation-upsell-title"><?php esc_html_e('Build workflows with a simple IF–THEN flow', 'elzo-forms'); ?></h2>
            <?php endif; ?>
            <p class="elzo-forms-automation-upsell-description"><?php echo esc_html($automation_upsell_description); ?></p>
        </div>
    </header>

    <div class="elzo-forms-automation-upsell-flow" role="group" aria-label="<?php echo esc_attr__('IF–THEN automation flow', 'elzo-forms'); ?>">
        <section class="elzo-forms-automation-upsell-stage is-if">
            <header class="elzo-forms-automation-upsell-stage-header">
                <span class="elzo-forms-automation-upsell-stage-keyword"><?php esc_html_e('IF', 'elzo-forms'); ?></span>
                <span><strong><?php esc_html_e('This submission matches…', 'elzo-forms'); ?></strong><small><?php esc_html_e('Combine conditions with AND or OR.', 'elzo-forms'); ?></small></span>
            </header>
            <ul class="elzo-forms-automation-upsell-stage-list">
                <li><span class="elzo-forms-automation-upsell-stage-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('field'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span><?php esc_html_e('Compare submitted field values', 'elzo-forms'); ?></li>
                <li><span class="elzo-forms-automation-upsell-stage-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('page'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span><?php esc_html_e('Match the page or current URL', 'elzo-forms'); ?></li>
                <li><span class="elzo-forms-automation-upsell-stage-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('user'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span><?php esc_html_e('Check authentication or user', 'elzo-forms'); ?></li>
                <li><span class="elzo-forms-automation-upsell-stage-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('date'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span><?php esc_html_e('Check a cookie, date, or time', 'elzo-forms'); ?></li>
                <li><span class="elzo-forms-automation-upsell-stage-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('logic'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span><?php esc_html_e('Use earlier action results in later conditions', 'elzo-forms'); ?></li>
            </ul>
        </section>

        <div class="elzo-forms-automation-upsell-flow-connector" aria-hidden="true">
            <span class="elzo-forms-automation-upsell-flow-line"></span>
            <span class="elzo-forms-automation-upsell-flow-arrow">→</span>
        </div>

        <section class="elzo-forms-automation-upsell-stage is-then">
            <header class="elzo-forms-automation-upsell-stage-header">
                <span class="elzo-forms-automation-upsell-stage-keyword"><?php esc_html_e('THEN', 'elzo-forms'); ?></span>
                <span><strong><?php esc_html_e('Run these actions…', 'elzo-forms'); ?></strong><small><?php esc_html_e('Add multiple actions and control their order.', 'elzo-forms'); ?></small></span>
            </header>
            <ul class="elzo-forms-automation-upsell-stage-list">
                <li><span class="elzo-forms-automation-upsell-stage-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('email'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span><?php esc_html_e('Send an email or adjust recipients', 'elzo-forms'); ?></li>
                <li><span class="elzo-forms-automation-upsell-stage-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('url'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span><?php esc_html_e('Call a webhook with mapped data', 'elzo-forms'); ?></li>
                <li><span class="elzo-forms-automation-upsell-stage-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('button'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span><?php esc_html_e('Redirect the visitor', 'elzo-forms'); ?></li>
                <li><span class="elzo-forms-automation-upsell-stage-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('cookie'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span><?php esc_html_e('Update submitted values or cookies', 'elzo-forms'); ?></li>
                <li><span class="elzo-forms-automation-upsell-stage-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('content'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span><?php esc_html_e('Find, create, or update website content', 'elzo-forms'); ?></li>
            </ul>
        </section>
    </div>

    <div class="elzo-forms-automation-upsell-assurance">
        <span class="elzo-forms-automation-upsell-assurance-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('logic'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span>
        <p><strong><?php esc_html_e('Build multi-step chains.', 'elzo-forms'); ?></strong> <?php esc_html_e('Each action can have its own conditions, and later actions can use the output of earlier ones in their settings or conditions.', 'elzo-forms'); ?></p>
    </div>

    <footer class="elzo-forms-automation-upsell-footer">
        <a class="button button-primary" href="<?php echo esc_url($automation_upsell_learn_more_url); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('Explore Automations in Elzo Forms PRO', 'elzo-forms'); ?>
        </a>
        <span><?php esc_html_e('Nothing runs until you create and enable an automation.', 'elzo-forms'); ?></span>
    </footer>
</section>
