<?php
/**
 * Import / Export screen: forms of an uploaded file.
 *
 * @var array  $form_rows         Rows from \ElzoForms\ImportExport\Form_Importer::preview().
 * @var string $form_rows_context "forms" or "submissions".
 */

use ElzoForms\ImportExport\Admin_Controller;

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included inside Admin_Controller::render_page(), so its variables are function-scoped, not globals.

$action_labels = [
    'create' => __('Import', 'elzo-forms'),
    'copy' => __('Create a new copy', 'elzo-forms'),
    'replace' => __('Replace existing form', 'elzo-forms'),
    'skip' => __('Skip', 'elzo-forms'),
];
$skip_existing_label = $form_rows_context === 'submissions'
    ? __('Skip, keep the existing form', 'elzo-forms')
    : __('Skip existing form', 'elzo-forms');
?>
<table class="widefat striped elzo-forms-ie-table">
    <thead>
        <tr>
            <th scope="col"><?php esc_html_e('Form', 'elzo-forms'); ?></th>
            <th scope="col"><?php esc_html_e('Status', 'elzo-forms'); ?></th>
            <th scope="col"><?php esc_html_e('On this site', 'elzo-forms'); ?></th>
            <th scope="col"><?php esc_html_e('Action', 'elzo-forms'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($form_rows as $row) : ?>
            <tr data-elzo-ie-row>
                <td>
                    <strong><?php echo esc_html($row['title'] !== '' ? $row['title'] : __('(no title)', 'elzo-forms')); ?></strong>
                    <?php if ($row['key'] !== '') : ?>
                        <br><code><?php echo esc_html($row['key']); ?></code>
                    <?php endif; ?>
                    <?php foreach ($row['errors'] as $row_error) : ?>
                        <p class="elzo-forms-ie-error"><?php echo esc_html($row_error); ?></p>
                    <?php endforeach; ?>
                    <?php if ($row['warnings']) : ?>
                        <ul class="elzo-forms-ie-list elzo-forms-ie-warnings">
                            <?php foreach ($row['warnings'] as $row_warning) : ?>
                                <li><?php echo esc_html($row_warning); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html(Admin_Controller::status_label($row['status'])); ?></td>
                <td>
                    <?php if ($row['existing']) : ?>
                        <?php if ($row['existing']['edit_url'] !== '') : ?>
                            <a href="<?php echo esc_url($row['existing']['edit_url']); ?>"><?php echo esc_html($row['existing']['title'] !== '' ? $row['existing']['title'] : __('(no title)', 'elzo-forms')); ?></a>
                        <?php else : ?>
                            <?php echo esc_html($row['existing']['title'] !== '' ? $row['existing']['title'] : __('(no title)', 'elzo-forms')); ?>
                        <?php endif; ?>
                        <br><span class="elzo-forms-ie-muted"><?php esc_html_e('A form with this key exists.', 'elzo-forms'); ?></span>
                    <?php else : ?>
                        <span class="elzo-forms-ie-muted"><?php esc_html_e('New form', 'elzo-forms'); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $row_action_label = sprintf(
                        /* translators: %s: Form title. */
                        __('Action for %s', 'elzo-forms'),
                        $row['title'] !== '' ? $row['title'] : $row['key']
                    );
                    ?>
                    <select name="form_action[<?php echo esc_attr((string) $row['index']); ?>]" data-elzo-ie-action aria-label="<?php echo esc_attr($row_action_label); ?>">
                        <?php foreach ($row['actions'] as $row_action) : ?>
                            <option value="<?php echo esc_attr($row_action); ?>" <?php selected($row['default'], $row_action); ?>>
                                <?php echo esc_html($row_action === 'skip' && $row['existing'] ? $skip_existing_label : ($action_labels[$row_action] ?? $row_action)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (in_array('replace', $row['actions'], true)) : ?>
                        <div class="elzo-forms-ie-replace-note">
                            <p class="description"><?php esc_html_e('Replacing overwrites the existing form\'s fields and settings. Its ID, status and submissions stay, so shortcodes and blocks keep working.', 'elzo-forms'); ?></p>
                            <?php foreach ($row['replace_warnings'] as $replace_warning) : ?>
                                <p class="description"><strong><?php echo esc_html($replace_warning); ?></strong></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
