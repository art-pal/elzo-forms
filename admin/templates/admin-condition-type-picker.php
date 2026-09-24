<?php
    defined('ABSPATH') || exit;

    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside the admin meta-box callback scope.
    $condition_picker_groups = $condition_picker_groups ?? \ElzoForms\Admin\Condition_Type_Picker::get_item_groups();
    $condition_picker_id = $condition_picker_id ?? 'elzo-forms-condition-type-picker';
    $condition_picker_has_locked_items = false;
    foreach ($condition_picker_groups as $condition_picker_group) {
        if (array_filter($condition_picker_group['items'], static function (array $item): bool {
            return !empty($item['pro']);
        })) {
            $condition_picker_has_locked_items = true;
            break;
        }
    }
    $condition_pro_url = $condition_picker_has_locked_items
        ? \ElzoForms\Utilities\Admin::get_pro_url('field_logic_condition_modal')
        : '';
?>
<div class="elzo-forms-field-picker elzo-forms-condition-type-picker" id="<?php echo esc_attr($condition_picker_id); ?>" role="dialog" aria-label="<?php echo esc_attr__('Choose condition type', 'elzo-forms'); ?>" data-label-change="<?php echo esc_attr__('Choose condition type', 'elzo-forms'); ?>" hidden>
    <div class="elzo-forms-field-picker-search">
        <i class="elzo-icon elzo-icon-search" aria-hidden="true"></i>
        <input type="search" id="<?php echo esc_attr($condition_picker_id . '-search'); ?>" class="elzo-forms-field-picker-search-input" placeholder="<?php echo esc_attr__('Search conditions', 'elzo-forms'); ?>" aria-label="<?php echo esc_attr__('Search conditions', 'elzo-forms'); ?>" role="combobox" aria-autocomplete="list" aria-expanded="true" aria-controls="<?php echo esc_attr($condition_picker_id . '-list'); ?>" autocomplete="off" autocapitalize="off" spellcheck="false">
    </div>
    <div id="<?php echo esc_attr($condition_picker_id . '-list'); ?>" class="elzo-forms-field-picker-list" role="listbox" aria-label="<?php echo esc_attr__('Condition types', 'elzo-forms'); ?>">
        <div class="elzo-forms-field-picker-results" role="presentation" hidden></div>
        <?php foreach ($condition_picker_groups as $condition_picker_group): ?>
            <?php $condition_picker_group_label_id = $condition_picker_id . '-group-' . sanitize_html_class($condition_picker_group['key']); ?>
            <div class="elzo-forms-field-picker-group" role="group" aria-labelledby="<?php echo esc_attr($condition_picker_group_label_id); ?>">
                <div id="<?php echo esc_attr($condition_picker_group_label_id); ?>" class="elzo-forms-field-picker-group-label" role="presentation"><?php echo esc_html($condition_picker_group['label']); ?></div>
                <?php foreach ($condition_picker_group['items'] as $condition_picker_item): ?>
                    <?php $condition_picker_aria_label = $condition_picker_item['label']; ?>
                    <?php if ($condition_picker_item['pro']): ?>
                        <?php $condition_picker_aria_label .= ' — ' . __('PRO', 'elzo-forms'); ?>
                    <?php endif; ?>
                    <div id="<?php echo esc_attr($condition_picker_item['id']); ?>" class="elzo-forms-field-picker-option" role="option" aria-selected="false" aria-label="<?php echo esc_attr($condition_picker_aria_label); ?>" data-picker-type="<?php echo esc_attr($condition_picker_item['type']); ?>" data-picker-keywords="<?php echo esc_attr(implode('|', $condition_picker_item['keywords'])); ?>" data-picker-available="<?php echo $condition_picker_item['available'] ? '1' : '0'; ?>">
                        <?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg($condition_picker_item['icon']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?>
                        <span class="elzo-forms-field-picker-option-label"><?php echo esc_html($condition_picker_item['label']); ?></span>
                        <?php if ($condition_picker_item['pro']): ?>
                            <span class="elzo-forms-pro-badge" aria-hidden="true"><?php esc_html_e('PRO', 'elzo-forms'); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="elzo-forms-field-picker-status" role="status" data-empty-message="<?php echo esc_attr__('No conditions found', 'elzo-forms'); ?>"></p>
</div>

<?php if ($condition_picker_has_locked_items): ?>
<div class="elzo-forms-admin-modal elzo-forms-condition-pro-modal" id="elzo-forms-condition-pro-modal" hidden>
    <div class="elzo-forms-admin-modal-overlay" data-condition-pro-modal-close></div>
    <div class="elzo-forms-admin-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="elzo-forms-condition-pro-modal-title" tabindex="-1">
        <div class="elzo-forms-admin-modal-header">
            <span class="elzo-forms-condition-pro-modal-mark">
                <?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('logic'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?>
            </span>
            <div class="elzo-forms-condition-pro-modal-heading">
                <span class="elzo-forms-condition-pro-modal-eyebrow"><?php esc_html_e('Elzo Forms PRO', 'elzo-forms'); ?></span>
                <h2 id="elzo-forms-condition-pro-modal-title" class="elzo-forms-admin-modal-title"><?php esc_html_e('Advanced conditional logic', 'elzo-forms'); ?></h2>
            </div>
            <button type="button" class="elzo-forms-admin-modal-close" data-condition-pro-modal-close aria-label="<?php echo esc_attr__('Close', 'elzo-forms'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
        </div>
        <div class="elzo-forms-admin-modal-body">
            <p class="elzo-forms-condition-pro-modal-intro"><?php esc_html_e('Show or hide fields using more context from the current visitor and page.', 'elzo-forms'); ?></p>
            <div class="elzo-forms-condition-pro-features" role="list">
                <div class="elzo-forms-condition-pro-feature" role="listitem">
                    <span class="elzo-forms-condition-pro-feature-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('url'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span>
                    <span><strong><?php esc_html_e('URL', 'elzo-forms'); ?></strong><small><?php esc_html_e('Target specific links and paths', 'elzo-forms'); ?></small></span>
                </div>
                <div class="elzo-forms-condition-pro-feature" role="listitem">
                    <span class="elzo-forms-condition-pro-feature-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('user'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span>
                    <span><strong><?php esc_html_e('User', 'elzo-forms'); ?></strong><small><?php esc_html_e('Use roles and account details', 'elzo-forms'); ?></small></span>
                </div>
                <div class="elzo-forms-condition-pro-feature" role="listitem">
                    <span class="elzo-forms-condition-pro-feature-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('cookie'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span>
                    <span><strong><?php esc_html_e('Cookie', 'elzo-forms'); ?></strong><small><?php esc_html_e('Respond to browser state', 'elzo-forms'); ?></small></span>
                </div>
                <div class="elzo-forms-condition-pro-feature" role="listitem">
                    <span class="elzo-forms-condition-pro-feature-icon"><?php echo \ElzoForms\Admin\Field_Picker::get_icon_svg('date'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG artwork. ?></span>
                    <span><strong><?php esc_html_e('Date and Time', 'elzo-forms'); ?></strong><small><?php esc_html_e('Schedule field visibility', 'elzo-forms'); ?></small></span>
                </div>
            </div>
            <p class="elzo-forms-condition-pro-modal-note"><?php esc_html_e('These conditions are available in Elzo Forms PRO.', 'elzo-forms'); ?></p>
        </div>
        <div class="elzo-forms-admin-modal-footer">
            <button type="button" class="button" data-condition-pro-modal-close><?php esc_html_e('Close', 'elzo-forms'); ?></button>
            <a class="button button-primary" href="<?php echo esc_url($condition_pro_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Learn more about Elzo Forms PRO', 'elzo-forms'); ?></a>
        </div>
    </div>
</div>
<?php endif; ?>
