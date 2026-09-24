<?php
/**
 * Import / Export screen: forms to export.
 *
 * "Select all" stands for every form, whether or not its checkbox is ticked;
 * a submissions export then also includes submissions whose form no longer
 * exists. The screen script keeps it in step with the form checkboxes and
 * shows the search field, which filters the list without being submitted.
 *
 * @var array<int, string> $form_choices      Form labels, by form ID.
 * @var array<int, string> $form_choice_notes Notes shown after a label, by form ID.
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included inside Admin_Controller::render_page(), so its variables are function-scoped, not globals.

?>
<fieldset class="elzo-forms-ie-fieldset" data-elzo-ie-picker>
    <legend class="elzo-forms-ie-legend"><?php esc_html_e('Forms', 'elzo-forms'); ?></legend>

    <?php if ($form_choices) : ?>
        <div class="elzo-forms-admin-search elzo-forms-ie-choice-search" data-elzo-ie-choice-search hidden>
            <label for="elzo-forms-ie-form-search" class="elzo-forms-admin-search-label-icon">
                <i class="elzo-icon elzo-icon-search" aria-hidden="true"></i>
                <span class="screen-reader-text"><?php esc_html_e('Search forms', 'elzo-forms'); ?></span>
            </label>
            <input type="search" id="elzo-forms-ie-form-search" class="elzo-forms-admin-search-input" placeholder="<?php esc_attr_e('Search forms', 'elzo-forms'); ?>" autocomplete="off">
            <button type="button" class="elzo-forms-admin-search-clear" style="display:none" aria-label="<?php esc_attr_e('Clear search', 'elzo-forms'); ?>"><i class="elzo-icon elzo-icon-close" aria-hidden="true"></i></button>
        </div>
    <?php endif; ?>

    <div class="elzo-forms-ie-choice-list">
        <label class="elzo-forms-ie-option elzo-forms-ie-toggle-all">
            <input type="checkbox" name="all_forms" value="1" checked data-elzo-ie-toggle-all>
            <?php esc_html_e('Select all', 'elzo-forms'); ?>
        </label>
        <?php foreach ($form_choices as $choice_id => $choice_label) : ?>
            <label class="elzo-forms-ie-option">
                <input type="checkbox" name="form_ids[]" value="<?php echo esc_attr((string) $choice_id); ?>" checked data-elzo-ie-choice>
                <?php echo esc_html($choice_label); ?>
                <?php if (!empty($form_choice_notes[$choice_id])) : ?>
                    <span class="elzo-forms-ie-muted"><?php echo esc_html($form_choice_notes[$choice_id]); ?></span>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
        <p class="elzo-forms-ie-muted elzo-forms-ie-choice-empty" data-elzo-ie-choice-empty hidden><?php esc_html_e('No forms match your search.', 'elzo-forms'); ?></p>
    </div>
</fieldset>
