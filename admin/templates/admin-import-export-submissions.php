<?php
/**
 * Import / Export screen: Submissions tab.
 *
 * @var array $view Prepared by \ElzoForms\ImportExport\Admin_Controller::render_page().
 */

use ElzoForms\ImportExport\Admin_Controller;

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included inside Admin_Controller::render_page(), so its variables are function-scoped, not globals.

$admin_post_url = admin_url('admin-post.php');
$pending = $view['pending'];
$selection_count = count($view['selection']);
?>
<div class="elzo-forms-ie-panels">
    <?php if ($view['can']['export_submissions']) : ?>
        <div class="elzo-forms-ie-panel" id="<?php echo esc_attr(Admin_Controller::EXPORT_ANCHOR); ?>">
            <h2 class="elzo-forms-ie-panel-title"><?php esc_html_e('Export submissions', 'elzo-forms'); ?></h2>
            <p class="description"><?php esc_html_e('Download submissions as CSV for spreadsheets, or as JSON to import them into Elzo Forms on this or another site.', 'elzo-forms'); ?></p>

            <form method="post" action="<?php echo esc_url($admin_post_url); ?>" class="elzo-forms-ie-form">
                <input type="hidden" name="action" value="elzo_forms_export_submissions">
                <?php wp_nonce_field('elzo_forms_export_submissions'); ?>

                <?php if ($selection_count > 0) : ?>
                    <div class="notice notice-info inline">
                        <p>
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: Number of submissions. */
                                _n('%s submission is selected for export.', '%s submissions are selected for export.', $selection_count, 'elzo-forms'),
                                number_format_i18n($selection_count)
                            ));
                            ?>
                        </p>
                        <p>
                            <label class="elzo-forms-ie-option">
                                <input type="checkbox" name="use_selection" value="1" checked data-elzo-ie-use-selection>
                                <?php esc_html_e('Export only the selected submissions', 'elzo-forms'); ?>
                            </label>
                        </p>
                        <p class="description"><?php esc_html_e('Clear this to export other submissions, filtered by form, status and date.', 'elzo-forms'); ?></p>
                    </div>
                <?php endif; ?>

                <?php // The screen script hides the filters while the selection is exported. ?>
                <div class="elzo-forms-ie-filters" data-elzo-ie-filters>
                    <?php
                    $form_choices = $view['form_choices'];
                    $form_choice_notes = [];
                    include __DIR__ . '/admin-import-export-form-choices.php';
                    ?>

                    <div class="elzo-forms-ie-field">
                        <label for="elzo-forms-ie-export-status"><?php esc_html_e('Status', 'elzo-forms'); ?></label>
                        <select id="elzo-forms-ie-export-status" name="status">
                            <option value="normal" selected><?php esc_html_e('Not spam', 'elzo-forms'); ?></option>
                            <option value="spam"><?php esc_html_e('Spam', 'elzo-forms'); ?></option>
                            <option value="all"><?php esc_html_e('All', 'elzo-forms'); ?></option>
                        </select>
                    </div>

                    <div class="elzo-forms-ie-field elzo-forms-ie-dates">
                        <div>
                            <label for="elzo-forms-ie-date-from"><?php esc_html_e('From', 'elzo-forms'); ?></label>
                            <input type="date" id="elzo-forms-ie-date-from" name="date_from">
                        </div>
                        <div>
                            <label for="elzo-forms-ie-date-to"><?php esc_html_e('To', 'elzo-forms'); ?></label>
                            <input type="date" id="elzo-forms-ie-date-to" name="date_to">
                        </div>
                    </div>
                </div>

                <fieldset class="elzo-forms-ie-fieldset">
                    <legend class="elzo-forms-ie-legend"><?php esc_html_e('Format', 'elzo-forms'); ?></legend>
                    <label class="elzo-forms-ie-option">
                        <input type="radio" name="format" value="csv" checked data-elzo-ie-format>
                        <?php esc_html_e('CSV, for spreadsheets and data analysis', 'elzo-forms'); ?>
                    </label>
                    <label class="elzo-forms-ie-option">
                        <input type="radio" name="format" value="json" data-elzo-ie-format>
                        <?php esc_html_e('JSON, for backups and moving submissions to another site', 'elzo-forms'); ?>
                    </label>
                </fieldset>

                <fieldset class="elzo-forms-ie-fieldset">
                    <legend class="elzo-forms-ie-legend"><?php esc_html_e('Options', 'elzo-forms'); ?></legend>
                    <label class="elzo-forms-ie-option">
                        <input type="checkbox" name="include_metadata" value="1">
                        <?php esc_html_e('Include submitter metadata', 'elzo-forms'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('IP address, user agent and WordPress user ID are personal data and are left out unless you include them. The submission date, form and page are always included.', 'elzo-forms'); ?></p>

                    <div class="elzo-forms-ie-option-group" data-elzo-ie-csv-only-group>
                        <?php // A cleared checkbox is not submitted, so the hidden field states that the row is left out. ?>
                        <input type="hidden" name="include_headers" value="0">
                        <label class="elzo-forms-ie-option">
                            <input type="checkbox" name="include_headers" value="1" checked data-elzo-ie-csv-only>
                            <?php esc_html_e('Include a header row with column names (CSV only)', 'elzo-forms'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Leave it out to append the rows to an existing spreadsheet.', 'elzo-forms'); ?></p>
                    </div>

                    <div class="elzo-forms-ie-option-group" data-elzo-ie-json-only-group>
                        <label class="elzo-forms-ie-option">
                            <input type="checkbox" name="include_forms" value="1" data-elzo-ie-json-only>
                            <?php esc_html_e('Include the form definitions (JSON only)', 'elzo-forms'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Makes the file a complete migration package: importing it can create the forms as well. Sensitive settings are not included.', 'elzo-forms'); ?></p>
                    </div>
                </fieldset>

                <?php submit_button(__('Download export file', 'elzo-forms'), 'primary', 'submit', false); ?>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($view['can']['import_submissions']) : ?>
        <div class="elzo-forms-ie-panel" id="<?php echo esc_attr(Admin_Controller::IMPORT_ANCHOR); ?>">
            <h2 class="elzo-forms-ie-panel-title"><?php esc_html_e('Import submissions', 'elzo-forms'); ?></h2>

            <?php if (!empty($pending['summary'])) : ?>
                <?php $summary = $pending['summary']; ?>
                <p class="elzo-forms-ie-file">
                    <?php
                    /* translators: %s: File name. */
                    printf(esc_html__('File: %s', 'elzo-forms'), '<strong>' . esc_html($pending['name']) . '</strong>');
                    ?>
                </p>
                <ul class="elzo-forms-ie-list">
                    <?php foreach ($pending['notes'] as $note) : ?>
                        <li><?php echo esc_html($note); ?></li>
                    <?php endforeach; ?>
                    <li>
                        <?php
                        /* translators: %s: Number of submissions. */
                        echo esc_html(sprintf(_n('This file contains %s submission.', 'This file contains %s submissions.', $summary['total'], 'elzo-forms'), number_format_i18n($summary['total'])));
                        ?>
                    </li>
                    <?php if ($summary['spam'] > 0) : ?>
                        <li>
                            <?php
                            /* translators: %s: Number of submissions. */
                            echo esc_html(sprintf(_n('%s is marked as spam and is imported as spam.', '%s are marked as spam and are imported as spam.', $summary['spam'], 'elzo-forms'), number_format_i18n($summary['spam'])));
                            ?>
                        </li>
                    <?php endif; ?>
                    <?php if ($summary['duplicates'] > 0) : ?>
                        <li>
                            <?php
                            /* translators: %s: Number of submissions. */
                            echo esc_html(sprintf(_n('%s was imported before and is skipped.', '%s were imported before and are skipped.', $summary['duplicates'], 'elzo-forms'), number_format_i18n($summary['duplicates'])));
                            ?>
                        </li>
                    <?php endif; ?>
                    <?php if ($summary['invalid'] > 0) : ?>
                        <li>
                            <?php
                            /* translators: %s: Number of submissions. */
                            echo esc_html(sprintf(_n('%s has no field data and is skipped.', '%s have no field data and are skipped.', $summary['invalid'], 'elzo-forms'), number_format_i18n($summary['invalid'])));
                            ?>
                        </li>
                    <?php endif; ?>
                </ul>

                <form method="post" action="<?php echo esc_url($admin_post_url); ?>" id="elzo-forms-ie-confirm-submissions">
                    <input type="hidden" name="action" value="elzo_forms_import_confirm">
                    <input type="hidden" name="type" value="submissions">
                    <?php wp_nonce_field('elzo_forms_import_confirm_submissions'); ?>

                    <?php if (!empty($pending['rows'])) : ?>
                        <h3><?php esc_html_e('Forms in this file', 'elzo-forms'); ?></h3>
                        <p class="description"><?php esc_html_e('Choose which of these forms to import. A form that already exists on this site is kept by default, and its submissions are added to it.', 'elzo-forms'); ?></p>
                        <?php
                        $form_rows = $pending['rows'];
                        $form_rows_context = 'submissions';
                        include __DIR__ . '/admin-import-export-form-rows.php';
                        ?>
                    <?php endif; ?>

                    <h3><?php esc_html_e('Assign submissions to forms', 'elzo-forms'); ?></h3>
                    <table class="widefat striped elzo-forms-ie-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Form in the file', 'elzo-forms'); ?></th>
                                <th scope="col"><?php esc_html_e('Submissions', 'elzo-forms'); ?></th>
                                <th scope="col"><?php esc_html_e('Import into', 'elzo-forms'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary['mappings'] as $mapping) : ?>
                                <?php
                                $mapping_label = $mapping['title'] !== '' ? $mapping['title'] : ($mapping['key'] !== '' ? $mapping['key'] : __('Unknown form', 'elzo-forms'));
                                $mapping_select_label = sprintf(
                                    /* translators: %s: Form title. */
                                    __('Destination for the submissions of %s', 'elzo-forms'),
                                    $mapping_label
                                );
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($mapping_label); ?></strong>
                                        <?php if ($mapping['key'] !== '') : ?>
                                            <br><code><?php echo esc_html($mapping['key']); ?></code>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html(number_format_i18n($mapping['count'])); ?></td>
                                    <td>
                                        <select name="mapping[<?php echo esc_attr((string) $mapping['index']); ?>]" aria-label="<?php echo esc_attr($mapping_select_label); ?>">
                                            <?php if ($mapping['has_form']) : ?>
                                                <option value="package" <?php selected($mapping['default'], 'package'); ?>><?php esc_html_e('The form from this file', 'elzo-forms'); ?></option>
                                            <?php endif; ?>
                                            <?php if ($pending['destinations']) : ?>
                                                <optgroup label="<?php esc_attr_e('Forms on this site', 'elzo-forms'); ?>">
                                                    <?php foreach ($pending['destinations'] as $form_id => $form_label) : ?>
                                                        <option value="<?php echo esc_attr((string) $form_id); ?>" <?php selected($mapping['default'], (string) $form_id); ?>><?php echo esc_html($form_label); ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>
                                            <option value="skip" <?php selected($mapping['default'], 'skip'); ?>><?php esc_html_e('Skip these submissions', 'elzo-forms'); ?></option>
                                        </select>
                                        <?php if (!$mapping['has_form'] && !$mapping['match']) : ?>
                                            <p class="elzo-forms-ie-warning"><?php esc_html_e('No form with this key exists on this site. Choose a form, or these submissions are skipped.', 'elzo-forms'); ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="description"><?php esc_html_e('Imported submissions keep their original date, field values and spam status. Nothing that runs for new submissions runs for them, such as notifications or spam checks. Uploaded files are not copied: file fields keep their original links.', 'elzo-forms'); ?></p>
                    <p class="description"><?php esc_html_e('If an import is interrupted, run it again: submissions that were already imported are skipped.', 'elzo-forms'); ?></p>
                </form>

                <div class="elzo-forms-ie-actions">
                    <button type="submit" form="elzo-forms-ie-confirm-submissions" class="button button-primary"><?php esc_html_e('Import submissions', 'elzo-forms'); ?></button>
                    <form method="post" action="<?php echo esc_url($admin_post_url); ?>" class="elzo-forms-ie-inline-form">
                        <input type="hidden" name="action" value="elzo_forms_import_cancel">
                        <input type="hidden" name="type" value="submissions">
                        <?php wp_nonce_field('elzo_forms_import_cancel_submissions'); ?>
                        <button type="submit" class="button"><?php esc_html_e('Cancel', 'elzo-forms'); ?></button>
                    </form>
                </div>
            <?php else : ?>
                <?php if (!empty($pending['error'])) : ?>
                    <div class="notice notice-error inline"><p><?php echo esc_html($pending['error']); ?></p></div>
                <?php endif; ?>
                <p class="description"><?php esc_html_e('Upload a JSON file exported from Elzo Forms. You can choose the destination form of each group of submissions before anything is imported. CSV files cannot be imported.', 'elzo-forms'); ?></p>
                <?php
                $upload_type = 'submissions';
                include __DIR__ . '/admin-import-export-upload.php';
                ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
