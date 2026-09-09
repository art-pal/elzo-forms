<?php

defined('ABSPATH') || exit;

    // Styles settings
?>
<div class="elzo-forms-admin-search-section">
    <div class="elzo-forms-admin-search">
        <label for="elzo-forms-admin-settings-form-search-input" class="elzo-forms-admin-search-label-icon"><i class="elzo-icon elzo-icon-search"></i></label>
        <input type="search" class="elzo-forms-admin-search-input" id="elzo-forms-admin-settings-form-search-input" placeholder="<?php echo esc_attr__('Search style settings', 'elzo-forms'); ?>">
        <button type="button" class="elzo-forms-admin-search-clear" style="display:none"><i class="elzo-icon elzo-icon-close"></i></button>
    </div>
    <div class="elzo-forms-admin-search-nothing-found" style="display:none">
        <p><?php esc_html_e('No settings found', 'elzo-forms'); ?></p>
    </div>
    <table class="form-table">
        <tbody class="elzo-forms-admin-search-list">
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_disable_theme_styles"><?php esc_html_e('Disable styles', 'elzo-forms'); ?></label></th>
                <td>
                    <label for="elzo_forms_style_settings_disable_theme_styles">
                        <input type="checkbox" name="elzo_forms_style_settings[disable_theme_styles]" id="elzo_forms_style_settings_disable_theme_styles" value="1" <?php echo checked(!empty($style_settings['disable_theme_styles']), 1, false); ?> >
                        <?php esc_html_e('Disable theme CSS', 'elzo-forms'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_primary_color"><?php esc_html_e('Primary color', 'elzo-forms'); ?></label></th>
                <td>
                    <div class="elzo-color-picker-wrapper">
                        <input type="hidden" name="elzo_forms_style_settings[primary_color]" class="elzo-color-picker-value" value="<?php echo esc_attr($style_settings['primary_color']??'') ?>" placeholder="#0073aa">
                        <input type="hidden" name="elzo_forms_style_settings[primary_color_dark]" class="elzo-color-picker-value-dark" value="<?php echo esc_attr($style_settings['primary_color_dark']??'') ?>" placeholder="#005580">
                        <input type="hidden" name="elzo_forms_style_settings[primary_color_hover]" class="elzo-color-picker-value-hover" value="<?php echo esc_attr($style_settings['primary_color_hover']??'') ?>" placeholder="#1a81b3">
                        <input type="hidden" name="elzo_forms_style_settings[primary_color_50]" class="elzo-color-picker-value-50" value="<?php echo esc_attr($style_settings['primary_color_50']??'') ?>" placeholder="rgba(0, 116, 170, 0.5)">
                        <input type="hidden" name="elzo_forms_style_settings[primary_color_25]" class="elzo-color-picker-value-25" value="<?php echo esc_attr($style_settings['primary_color_25']??'') ?>" placeholder="rgba(0, 116, 170, 0.25)">
                        <div class="elzo-color-picker-facade">
                            <div class="elzo-color-picker-facade-preview" style="background-color:<?php echo esc_attr(!empty($style_settings['primary_color']) ? $style_settings['primary_color'] : '#0073aa'); ?>"></div>
                            <button type="button" class="elzo-color-picker-facade-toggler button"><?php esc_html_e('Select color', 'elzo-forms'); ?></button>
                            <button type="button" class="elzo-color-picker-facade-clear button" <?php echo !empty($style_settings['primary_color'])&&$style_settings['primary_color']!='#0073aa'?'':'style="display:none"'; ?>>&times;</button>
                        </div>
                        <input type="text" value="<?php echo esc_attr($style_settings['primary_color']??'') ?>" id="elzo_forms_style_settings_primary_color" class="elzo-color-picker" data-default-color="#0073aa">
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_primary_text_color"><?php esc_html_e('Primary text color', 'elzo-forms'); ?></label></th>
                <td>
                    <div class="elzo-color-picker-wrapper">
                        <input type="hidden" name="elzo_forms_style_settings[primary_text_color]" class="elzo-color-picker-value" value="<?php echo esc_attr($style_settings['primary_text_color']??'') ?>" placeholder="#ffffff">
                        <div class="elzo-color-picker-facade">
                            <div class="elzo-color-picker-facade-preview" style="background-color:<?php echo esc_attr(!empty($style_settings['primary_text_color']) ? $style_settings['primary_text_color'] : '#ffffff'); ?>"></div>
                            <button type="button" class="elzo-color-picker-facade-toggler button"><?php esc_html_e('Select color', 'elzo-forms'); ?></button>
                            <button type="button" class="elzo-color-picker-facade-clear button" <?php echo !empty($style_settings['primary_text_color'])&&$style_settings['primary_text_color']!='#ffffff'?'':'style="display:none"'; ?>>&times;</button>
                        </div>
                        <input type="text" value="<?php echo esc_attr($style_settings['primary_text_color']??'') ?>" id="elzo_forms_style_settings_primary_text_color" class="elzo-color-picker" data-default-color="#ffffff">
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_error_color"><?php esc_html_e('Error color', 'elzo-forms'); ?></label></th>
                <td>
                    <div class="elzo-color-picker-wrapper">
                        <input type="hidden" name="elzo_forms_style_settings[error_color]" class="elzo-color-picker-value" value="<?php echo esc_attr($style_settings['error_color']??'') ?>" placeholder="#dc3545">
                        <input type="hidden" name="elzo_forms_style_settings[error_color_light]" class="elzo-color-picker-value-light" value="<?php echo esc_attr($style_settings['error_color_light']??'') ?>" placeholder="#ffeaec">
                        <input type="hidden" name="elzo_forms_style_settings[error_color_50]" class="elzo-color-picker-value-50" value="<?php echo esc_attr($style_settings['error_color_50']??'') ?>" placeholder="rgba(220, 53, 70, 0.5)">
                        <input type="hidden" name="elzo_forms_style_settings[error_color_25]" class="elzo-color-picker-value-25" value="<?php echo esc_attr($style_settings['error_color_25']??'') ?>" placeholder="rgba(220, 53, 70, 0.25)">
                        <div class="elzo-color-picker-facade">
                            <div class="elzo-color-picker-facade-preview" style="background-color:<?php echo esc_attr(!empty($style_settings['error_color']) ? $style_settings['error_color'] : '#dc3545'); ?>"></div>
                            <button type="button" class="elzo-color-picker-facade-toggler button"><?php esc_html_e('Select color', 'elzo-forms'); ?></button>
                            <button type="button" class="elzo-color-picker-facade-clear button" <?php echo !empty($style_settings['error_color'])&&$style_settings['error_color']!='#dc3545'?'':'style="display:none"'; ?>>&times;</button>
                        </div>
                        <input type="text" value="<?php echo esc_attr($style_settings['error_color']??'') ?>" id="elzo_forms_style_settings_error_color" class="elzo-color-picker" data-default-color="#dc3545">
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_success_color"><?php esc_html_e('Success color', 'elzo-forms'); ?></label></th>
                <td>
                    <div class="elzo-color-picker-wrapper">
                        <input type="hidden" name="elzo_forms_style_settings[success_color]" class="elzo-color-picker-value" value="<?php echo esc_attr($style_settings['success_color']??'') ?>" placeholder="#0073aa">
                        <input type="hidden" name="elzo_forms_style_settings[success_color_light]" class="elzo-color-picker-value-light" value="<?php echo esc_attr($style_settings['success_color_light']??'') ?>" placeholder="#e0edf4">
                        <div class="elzo-color-picker-facade">
                            <div class="elzo-color-picker-facade-preview" style="background-color:<?php echo esc_attr(!empty($style_settings['success_color']) ? $style_settings['success_color'] : '#0073aa'); ?>"></div>
                            <button type="button" class="elzo-color-picker-facade-toggler button"><?php esc_html_e('Select color', 'elzo-forms'); ?></button>
                            <button type="button" class="elzo-color-picker-facade-clear button" <?php echo !empty($style_settings['success_color'])&&$style_settings['success_color']!='#0073aa'?'':'style="display:none"'; ?>>&times;</button>
                        </div>
                        <input type="text" value="<?php echo esc_attr($style_settings['success_color']??'') ?>" id="elzo_forms_style_settings_success_color" class="elzo-color-picker" data-default-color="#0073aa">
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_light_color"><?php esc_html_e('Light color', 'elzo-forms'); ?></label></th>
                <td>
                    <div class="elzo-color-picker-wrapper">
                        <input type="hidden" name="elzo_forms_style_settings[light_color]" class="elzo-color-picker-value" value="<?php echo esc_attr($style_settings['light_color']??'') ?>" placeholder="#f1f1f1">
                        <div class="elzo-color-picker-facade">
                            <div class="elzo-color-picker-facade-preview" style="background-color:<?php echo esc_attr(!empty($style_settings['light_color']) ? $style_settings['light_color'] : '#f1f1f1'); ?>"></div>
                            <button type="button" class="elzo-color-picker-facade-toggler button"><?php esc_html_e('Select color', 'elzo-forms'); ?></button>
                            <div class="elzo-color-picker-opacity-box" title="<?php echo esc_attr__('Opacity', 'elzo-forms'); ?>">
                                <input type="number" name="elzo_forms_style_settings[light_color_opacity]" min="0" max="100" class="elzo-color-picker-opacity" value="<?php echo esc_attr($style_settings['light_color_opacity']??'') ?>" placeholder="100" size="3"> %
                            </div>
                            <button type="button" class="elzo-color-picker-facade-clear button" <?php echo !empty($style_settings['light_color'])&&$style_settings['light_color']!='#f1f1f1'?'':'style="display:none"'; ?>>&times;</button>
                        </div>
                        <input type="text" name="elzo_forms_style_settings[light_color_hex]" value="<?php echo esc_attr($style_settings['light_color_hex']??'') ?>" id="elzo_forms_style_settings_light_color" class="elzo-color-picker" data-default-color="#f1f1f1">
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_input_border_color"><?php esc_html_e('Input border color', 'elzo-forms'); ?></label></th>
                <td>
                    <div class="elzo-color-picker-wrapper">
                        <input type="hidden" name="elzo_forms_style_settings[input_border_color]" class="elzo-color-picker-value" value="<?php echo esc_attr($style_settings['input_border_color']??'') ?>" placeholder="#cccccc">
                        <div class="elzo-color-picker-facade">
                            <div class="elzo-color-picker-facade-preview" style="background-color:<?php echo esc_attr(!empty($style_settings['input_border_color']) ? $style_settings['input_border_color'] : '#cccccc'); ?>"></div>
                            <button type="button" class="elzo-color-picker-facade-toggler button"><?php esc_html_e('Select color', 'elzo-forms'); ?></button>
                            <div class="elzo-color-picker-opacity-box" title="<?php echo esc_attr__('Opacity', 'elzo-forms'); ?>">
                                <input type="number" name="elzo_forms_style_settings[input_border_color_opacity]" min="0" max="100" class="elzo-color-picker-opacity" value="<?php echo esc_attr($style_settings['input_border_color_opacity']??'') ?>" placeholder="100" size="3"> %
                            </div>
                            <button type="button" class="elzo-color-picker-facade-clear button" <?php echo !empty($style_settings['input_border_color'])&&$style_settings['input_border_color']!='#cccccc'?'':'style="display:none"'; ?>>&times;</button>
                        </div>
                        <input type="text" name="elzo_forms_style_settings[input_border_color_hex]" value="<?php echo esc_attr($style_settings['input_border_color_hex']??'') ?>" id="elzo_forms_style_settings_input_border_color" class="elzo-color-picker" data-default-color="#cccccc">
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_input_text_color"><?php esc_html_e('Input text color', 'elzo-forms'); ?></label></th>
                <td>
                    <div class="elzo-color-picker-wrapper">
                        <input type="hidden" name="elzo_forms_style_settings[input_text_color]" class="elzo-color-picker-value" value="<?php echo esc_attr($style_settings['input_text_color']??'') ?>" placeholder="#000000">
                        <div class="elzo-color-picker-facade">
                            <div class="elzo-color-picker-facade-preview" style="background-color:<?php echo esc_attr(!empty($style_settings['input_text_color']) ? $style_settings['input_text_color'] : '#000000'); ?>"></div>
                            <button type="button" class="elzo-color-picker-facade-toggler button"><?php esc_html_e('Select color', 'elzo-forms'); ?></button>
                            <div class="elzo-color-picker-opacity-box" title="<?php echo esc_attr__('Opacity', 'elzo-forms'); ?>">
                                <input type="number" name="elzo_forms_style_settings[input_text_color_opacity]" min="0" max="100" class="elzo-color-picker-opacity" value="<?php echo esc_attr($style_settings['input_text_color_opacity']??'') ?>" placeholder="100" size="3"> %
                            </div>
                            <button type="button" class="elzo-color-picker-facade-clear button" <?php echo !empty($style_settings['input_text_color'])&&$style_settings['input_text_color']!='#000000'?'':'style="display:none"'; ?>>&times;</button>
                        </div>
                        <input type="text" name="elzo_forms_style_settings[input_text_color_hex]" value="<?php echo esc_attr($style_settings['input_text_color_hex']??'') ?>" id="elzo_forms_style_settings_input_text_color" class="elzo-color-picker" data-default-color="#000000">
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_input_background_color"><?php esc_html_e('Input background color', 'elzo-forms'); ?></label></th>
                <td>
                    <div class="elzo-color-picker-wrapper">
                        <input type="hidden" name="elzo_forms_style_settings[input_background_color]" class="elzo-color-picker-value" value="<?php echo esc_attr($style_settings['input_background_color']??'') ?>" placeholder="#ffffff">
                        <div class="elzo-color-picker-facade">
                            <div class="elzo-color-picker-facade-preview" style="background-color:<?php echo esc_attr(!empty($style_settings['input_background_color']) ? $style_settings['input_background_color'] : '#ffffff'); ?>"></div>
                            <button type="button" class="elzo-color-picker-facade-toggler button"><?php esc_html_e('Select color', 'elzo-forms'); ?></button>
                            <div class="elzo-color-picker-opacity-box" title="<?php echo esc_attr__('Opacity', 'elzo-forms'); ?>">
                                <input type="number" name="elzo_forms_style_settings[input_background_color_opacity]" min="0" max="100" class="elzo-color-picker-opacity" value="<?php echo esc_attr($style_settings['input_background_color_opacity']??'') ?>" placeholder="100" size="3"> %
                            </div>
                            <button type="button" class="elzo-color-picker-facade-clear button" <?php echo !empty($style_settings['input_background_color'])&&$style_settings['input_background_color']!='#ffffff'?'':'style="display:none"'; ?>>&times;</button>
                        </div>
                        <input type="text" name="elzo_forms_style_settings[input_background_color_hex]" value="<?php echo esc_attr($style_settings['input_background_color_hex']??'') ?>" id="elzo_forms_style_settings_input_background_color" class="elzo-color-picker" data-default-color="#ffffff">
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_floating_background_color"><?php esc_html_e('Floating elements background color', 'elzo-forms'); ?></label></th>
                <td>
                    <div class="elzo-color-picker-wrapper">
                        <input type="hidden" name="elzo_forms_style_settings[floating_background_color]" class="elzo-color-picker-value" value="<?php echo esc_attr($style_settings['floating_background_color']??'') ?>" placeholder="#ffffff">
                        <input type="hidden" name="elzo_forms_style_settings[floating_background_color_hover]" class="elzo-color-picker-value-hover" value="<?php echo esc_attr($style_settings['floating_background_color_hover']??'') ?>" placeholder="#eeeeee">
                        <div class="elzo-color-picker-facade">
                            <div class="elzo-color-picker-facade-preview" style="background-color:<?php echo esc_attr(!empty($style_settings['floating_background_color']) ? $style_settings['floating_background_color'] : '#ffffff'); ?>"></div>
                            <button type="button" class="elzo-color-picker-facade-toggler button"><?php esc_html_e('Select color', 'elzo-forms'); ?></button>
                            <button type="button" class="elzo-color-picker-facade-clear button" <?php echo !empty($style_settings['floating_background_color'])&&$style_settings['floating_background_color']!='#ffffff'?'':'style="display:none"'; ?>>&times;</button>
                        </div>
                        <input type="text" value="<?php echo esc_attr($style_settings['floating_background_color']??'') ?>" id="elzo_forms_style_settings_floating_background_color" class="elzo-color-picker" data-default-color="#ffffff">
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_floating_text_color"><?php esc_html_e('Floating elements text color', 'elzo-forms'); ?></label></th>
                <td>
                    <div class="elzo-color-picker-wrapper">
                        <input type="hidden" name="elzo_forms_style_settings[floating_text_color]" class="elzo-color-picker-value" value="<?php echo esc_attr($style_settings['floating_text_color']??'') ?>" placeholder="#000000">
                        <div class="elzo-color-picker-facade">
                            <div class="elzo-color-picker-facade-preview" style="background-color:<?php echo esc_attr(!empty($style_settings['floating_text_color']) ? $style_settings['floating_text_color'] : '#000000'); ?>"></div>
                            <button type="button" class="elzo-color-picker-facade-toggler button"><?php esc_html_e('Select color', 'elzo-forms'); ?></button>
                            <button type="button" class="elzo-color-picker-facade-clear button" <?php echo !empty($style_settings['floating_text_color'])&&$style_settings['floating_text_color']!='#000000'?'':'style="display:none"'; ?>>&times;</button>
                        </div>
                        <input type="text" value="<?php echo esc_attr($style_settings['floating_text_color']??'') ?>" id="elzo_forms_style_settings_floating_text_color" class="elzo-color-picker" data-default-color="#000000">
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_input_style"><?php esc_html_e('Input style', 'elzo-forms'); ?></label></th>
                <td>
                    <select name="elzo_forms_style_settings[input_style]" id="elzo_forms_style_settings_input_style">
                        <option value="default" <?php echo selected($style_settings['input_style']??'', 'default', false) ?>><?php esc_html_e('Default', 'elzo-forms'); ?></option>
                        <option value="without-border" <?php echo selected($style_settings['input_style']??'', 'without-border', false) ?>><?php esc_html_e('Without border', 'elzo-forms'); ?></option>
                        <option value="border-bottom" <?php echo selected($style_settings['input_style']??'', 'border-bottom', false) ?>><?php esc_html_e('Border bottom', 'elzo-forms'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_input_border_width"><?php esc_html_e('Input border width', 'elzo-forms'); ?></label></th>
                <td>
                    <input type="number" name="elzo_forms_style_settings[input_border_width]" id="elzo_forms_style_settings_input_border_width" value="<?php echo esc_attr($style_settings['input_border_width']??'') ?>" class="small-text" min="0" placeholder="1"> <?php esc_html_e('px', 'elzo-forms'); ?>
                    <input type="hidden" name="elzo_forms_style_settings[input_border_width_05]" value="<?php echo esc_attr($style_settings['input_border_width_05']??'') ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_input_border_radius"><?php esc_html_e('Input border radius', 'elzo-forms'); ?></label></th>
                <td>
                    <input type="number" name="elzo_forms_style_settings[input_border_radius]" id="elzo_forms_style_settings_input_border_radius" value="<?php echo esc_attr($style_settings['input_border_radius']??'') ?>" class="small-text" min="0" placeholder="5"> <?php esc_html_e('px', 'elzo-forms'); ?>
                    <input type="hidden" name="elzo_forms_style_settings[input_border_radius_05]" value="<?php echo esc_attr($style_settings['input_border_radius_05']??'') ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="elzo_forms_style_settings_input_font_size"><?php esc_html_e('Input font size', 'elzo-forms'); ?></label></th>
                <td>
                    <input type="number" name="elzo_forms_style_settings[input_font_size]" id="elzo_forms_style_settings_input_font_size" value="<?php echo esc_attr($style_settings['input_font_size']??'') ?>" class="small-text" min="0" placeholder="16"> <?php esc_html_e('px', 'elzo-forms'); ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>
