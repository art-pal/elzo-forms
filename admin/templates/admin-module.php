<?php
/**
 * Single Module Card Template
 * Variables available: $id, $module, $schema, $is_compatible, $is_plugin_settings, $is_form_settings, $config_warnings
 * And context-specific variables set before inclusion
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included from admin-settings-modules.php within admin callback/template scope, so variables here are not global.

?>
<div class="elzo-forms-module-card <?php echo ($is_plugin_settings ? ($is_enabled ? 'module-enabled' : 'module-disabled') : ($effective_enabled ? 'module-enabled' : 'module-disabled')); ?>">
    <div class="elzo-forms-module-card-header">
        <h3 class="elzo-forms-module-name">
            <?php if (!empty($config_warnings)): ?>
                <button type="button" class="elzo-forms-module-warning-icon" data-module-id="<?php echo esc_attr($id); ?>" title="<?php esc_attr_e('Configuration warning. Click to view details.', 'elzo-forms'); ?>">
                    <span class="dashicons dashicons-warning"></span>
                </button>
            <?php endif; ?>
            <?php echo esc_html($module->get_name()); ?>
        </h3>
        <?php if ($is_plugin_settings): ?>
            <label class="elzo-forms-module-toggle">
                <input type="checkbox" 
                       name="elzo_forms_modules_enabled[<?php echo esc_attr($id); ?>]" 
                       value="1" 
                       <?php checked($is_enabled); ?>
                       <?php disabled(!$is_compatible); ?>
                       class="elzo-forms-module-toggle-checkbox">
                <span class="elzo-forms-module-toggle-label"><?php esc_html_e('Enable', 'elzo-forms'); ?></span>
            </label>
        <?php else: ?>
            <select name="elzo_forms_form_modules[<?php echo esc_attr($id); ?>][status]" class="elzo-forms-module-status-select">
                <option value="inherit" <?php selected($status, 'inherit'); ?>><?php esc_html_e('Auto', 'elzo-forms'); ?> (<?php echo $global_on ? esc_html__('Enabled', 'elzo-forms') : esc_html__('Disabled', 'elzo-forms'); ?>)</option>
                <option value="enabled" <?php selected($status, 'enabled'); ?>><?php esc_html_e('Enabled', 'elzo-forms'); ?></option>
                <option value="disabled" <?php selected($status, 'disabled'); ?>><?php esc_html_e('Disabled', 'elzo-forms'); ?></option>
            </select>
        <?php endif; ?>
    </div>
    
    <p class="elzo-forms-module-description"><?php echo esc_html($module->get_description()); ?></p>
    
    <div class="elzo-forms-module-meta">
        <span class="elzo-forms-module-version"><?php
            /* translators: %s: Module version number. */
            echo sprintf(esc_html__('Version: %s', 'elzo-forms'), esc_html($module->get_version()));
        ?></span>
        <?php if (!$is_compatible): ?>
            <span class="elzo-forms-module-incompatible">⚠ <?php esc_html_e('Not compatible', 'elzo-forms'); ?></span>
        <?php endif; ?>
    </div>
    

    
    <?php if ($show_settings): ?>
        <div class="elzo-forms-module-actions">
            <button type="button" class="button elzo-forms-module-settings-button" data-module-id="<?php echo esc_attr($id); ?>">
                <?php esc_html_e('Settings', 'elzo-forms'); ?>
            </button>
        </div>
    <?php elseif ($is_plugin_settings && $is_enabled): ?>
        <div class="elzo-forms-module-actions">
            <span class="elzo-forms-module-no-settings"><?php esc_html_e('No configurable settings', 'elzo-forms'); ?></span>
        </div>
    <?php endif; ?>
</div>

<?php if ($show_settings): ?>
    <!-- Modal for this module's settings -->
    <div id="elzo-forms-module-modal-<?php echo esc_attr($id); ?>" class="elzo-forms-module-modal" style="display: none;">
        <div class="elzo-forms-module-modal-overlay"></div>
        <div class="elzo-forms-module-modal-content">
            <div class="elzo-forms-module-modal-header">
                <h2><?php
                    /* translators: %s: Module name. */
                    echo esc_html(sprintf(esc_html__('%s Settings', 'elzo-forms'), $module->get_name()));
                ?></h2>
                <button type="button" class="elzo-forms-module-modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            
            <div class="elzo-forms-module-modal-body">
                <?php if (!empty($config_warnings)): ?>
                    <?php foreach ($config_warnings as $warning): ?>
                        <div class="elzo-forms-module-warning-box">
                            <span class="dashicons dashicons-warning"></span>
                            <span><?php echo esc_html($warning); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="elzo-forms-module-settings-fields">
                    <?php foreach ($schema as $key => $field):
                        $default = $field['default'] ?? '';

                        if ($is_plugin_settings) {
                            // Global settings
                            $value = $settings[$key] ?? $default;
                            $field_name = 'elzo_forms_module_' . $id . '_settings[' . $key . ']';
                            $is_overridden = false;
                        } else {
                            // Per-form settings
                            $value = isset($per_form_settings[$key])
                                ? $per_form_settings[$key]
                                : (isset($global_settings[$key]) ? $global_settings[$key] : $default);
                            $field_name = 'elzo_forms_form_modules[' . $id . '][settings][' . $key . ']';
                            $is_overridden = isset($per_form_settings[$key]);
                        }
                    ?>
                        <div class="elzo-forms-module-setting-field <?php echo $is_overridden ? 'elzo-forms-field-overridden' : ''; ?>">
                            <label for="<?php echo esc_attr($field_name); ?>" class="elzo-forms-setting-label">
                                <?php echo esc_html($field['label'] ?? ucfirst(str_replace('_', ' ', $key))); ?>
                                <?php if ($is_overridden): ?>
                                    <span class="elzo-forms-override-badge" title="<?php esc_attr_e('This setting overrides the global value', 'elzo-forms'); ?>" >⚙</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php if (($field['type'] ?? 'text') === 'select'): ?>
                                <select name="<?php echo esc_attr($field_name); ?>" id="<?php echo esc_attr($field_name); ?>" class="elzo-forms-setting-input">
                                    <?php foreach (($field['options'] ?? []) as $option): ?>
                                        <option value="<?php echo esc_attr($option); ?>" <?php selected($value, $option); ?>>
                                            <?php echo esc_html(ucfirst($option)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif (($field['type'] ?? 'text') === 'boolean'): ?>
                                <label class="elzo-forms-setting-checkbox">
                                    <input type="checkbox" name="<?php echo esc_attr($field_name); ?>" value="1" <?php checked(!empty($value)); ?> />
                                    <span><?php echo esc_html($field['description'] ?? ''); ?></span>
                                </label>
                            <?php else: ?>
                                <input type="text" class="elzo-forms-setting-input" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($value); ?>" id="<?php echo esc_attr($field_name); ?>" />
                            <?php endif; ?>
                            
                            <?php if (!empty($field['description']) && ($field['type'] ?? 'text') !== 'boolean'): ?>
                                <p class="elzo-forms-setting-description"><?php echo wp_kses_post($field['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="elzo-forms-module-modal-footer">
                <button type="button" class="button button-primary elzo-forms-module-modal-close">
                    <?php esc_html_e('Done', 'elzo-forms'); ?>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>
