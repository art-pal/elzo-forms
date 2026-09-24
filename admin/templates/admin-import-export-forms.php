<?php
/**
 * Import / Export screen: Forms tab.
 *
 * @var array $view Prepared by \ElzoForms\ImportExport\Admin_Controller::render_page().
 */

use ElzoForms\ImportExport\Admin_Controller;

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included inside Admin_Controller::render_page(), so its variables are function-scoped, not globals.

$admin_post_url = admin_url('admin-post.php');
$pending = $view['pending'];
?>
<div class="elzo-forms-ie-panels">
    <?php if ($view['can']['export_forms']) : ?>
        <div class="elzo-forms-ie-panel" id="<?php echo esc_attr(Admin_Controller::EXPORT_ANCHOR); ?>">
            <h2 class="elzo-forms-ie-panel-title"><?php esc_html_e('Export forms', 'elzo-forms'); ?></h2>
            <p class="description"><?php esc_html_e('Download forms as a JSON file you can import on this or another site. Global plugin settings are not included.', 'elzo-forms'); ?></p>

            <?php if (!$view['forms']) : ?>
                <p><?php esc_html_e('There are no forms to export.', 'elzo-forms'); ?></p>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url($admin_post_url); ?>" class="elzo-forms-ie-form">
                    <input type="hidden" name="action" value="elzo_forms_export_forms">
                    <input type="hidden" name="statuses_submitted" value="1">
                    <?php wp_nonce_field('elzo_forms_export_forms'); ?>

                    <?php
                    $form_choices = [];
                    $form_choice_notes = [];
                    foreach ($view['forms'] as $form_post) {
                        $form_choices[(int) $form_post->ID] = Admin_Controller::form_label($form_post);
                        $form_choice_notes[(int) $form_post->ID] = Admin_Controller::status_label((string) $form_post->post_status);
                    }
                    include __DIR__ . '/admin-import-export-form-choices.php';
                    ?>

                    <fieldset class="elzo-forms-ie-fieldset">
                        <legend class="elzo-forms-ie-legend"><?php esc_html_e('Statuses', 'elzo-forms'); ?></legend>
                        <?php foreach ($view['statuses'] as $status_key => $status_label) : ?>
                            <label class="elzo-forms-ie-option elzo-forms-ie-option-inline">
                                <input type="checkbox" name="statuses[]" value="<?php echo esc_attr($status_key); ?>" checked>
                                <?php echo esc_html($status_label); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e('Forms in the trash are never exported.', 'elzo-forms'); ?></p>
                    </fieldset>

                    <?php if ($view['can']['export_sensitive_settings']) : ?>
                        <fieldset class="elzo-forms-ie-fieldset">
                            <legend class="elzo-forms-ie-legend"><?php esc_html_e('Sensitive settings', 'elzo-forms'); ?></legend>
                            <label class="elzo-forms-ie-option">
                                <input type="checkbox" name="include_sensitive" value="1">
                                <?php esc_html_e('Include sensitive settings, such as API keys and secrets', 'elzo-forms'); ?>
                            </label>
                            <p class="description elzo-forms-ie-warning"><?php esc_html_e('Anyone who has the file can read these settings. Include them only when the file stays private, for example as a backup.', 'elzo-forms'); ?></p>
                        </fieldset>
                    <?php endif; ?>

                    <?php submit_button(__('Download export file', 'elzo-forms'), 'primary', 'submit', false); ?>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($view['can']['import_forms']) : ?>
        <div class="elzo-forms-ie-panel" id="<?php echo esc_attr(Admin_Controller::IMPORT_ANCHOR); ?>">
            <h2 class="elzo-forms-ie-panel-title"><?php esc_html_e('Import forms', 'elzo-forms'); ?></h2>

            <?php if (!empty($pending['rows'])) : ?>
                <p class="elzo-forms-ie-file">
                    <?php
                    /* translators: %s: File name. */
                    printf(esc_html__('File: %s', 'elzo-forms'), '<strong>' . esc_html($pending['name']) . '</strong>');
                    ?>
                </p>
                <?php if ($pending['notes']) : ?>
                    <ul class="elzo-forms-ie-list">
                        <?php foreach ($pending['notes'] as $note) : ?>
                            <li><?php echo esc_html($note); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($pending['submission_count'] > 0) : ?>
                    <p class="description">
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %s: Number of submissions. */
                            _n('This file also contains %s submission. Import it on the Submissions tab.', 'This file also contains %s submissions. Import them on the Submissions tab.', $pending['submission_count'], 'elzo-forms'),
                            number_format_i18n($pending['submission_count'])
                        ));
                        ?>
                    </p>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url($admin_post_url); ?>" id="elzo-forms-ie-confirm-forms">
                    <input type="hidden" name="action" value="elzo_forms_import_confirm">
                    <input type="hidden" name="type" value="forms">
                    <?php wp_nonce_field('elzo_forms_import_confirm_forms'); ?>
                    <?php
                    $form_rows = $pending['rows'];
                    $form_rows_context = 'forms';
                    include __DIR__ . '/admin-import-export-form-rows.php';
                    ?>
                </form>

                <div class="elzo-forms-ie-actions">
                    <button type="submit" form="elzo-forms-ie-confirm-forms" class="button button-primary"><?php esc_html_e('Import', 'elzo-forms'); ?></button>
                    <form method="post" action="<?php echo esc_url($admin_post_url); ?>" class="elzo-forms-ie-inline-form">
                        <input type="hidden" name="action" value="elzo_forms_import_cancel">
                        <input type="hidden" name="type" value="forms">
                        <?php wp_nonce_field('elzo_forms_import_cancel_forms'); ?>
                        <button type="submit" class="button"><?php esc_html_e('Cancel', 'elzo-forms'); ?></button>
                    </form>
                </div>
            <?php else : ?>
                <?php if (!empty($pending['error'])) : ?>
                    <div class="notice notice-error inline"><p><?php echo esc_html($pending['error']); ?></p></div>
                <?php endif; ?>
                <p class="description"><?php esc_html_e('Upload a JSON file exported from Elzo Forms. You can review the forms and choose what happens to each one before anything is imported.', 'elzo-forms'); ?></p>
                <?php
                $upload_type = 'forms';
                include __DIR__ . '/admin-import-export-upload.php';
                ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
