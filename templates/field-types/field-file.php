<?php
/**
 * The template for displaying file upload field
 *
 * This template can be overridden by copying it to yourtheme/elzo-forms/field-types/field-file.php.
 *
 * HOWEVER, on occasion Elzo Forms will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         Plugin documentation
 * @package     ElzoForms\Templates
 * @version     1.2.0
 *
 * @var array $d Field data prepared for rendering
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Available variables (passed directly):
 * @var string $style                 Upload style (drag-and-drop, button)
 * @var bool   $required              Whether the field is required
 * @var int    $min_files             Minimum number of files
 * @var int    $max_files             Maximum number of files
 * @var string $file_icon_color       Color for file icon
 * @var string $name                  Field name
 * @var string $file_upload_text      Upload area text
 * @var string $file_types_text       Allowed file types text
 * @var string $file_size_text        Max file size text
 * @var string $id                    Field ID
 * @var bool   $multiple              Whether multiple files allowed
 * @var string $allowed_file_types    Allowed file types (accept attribute)
 * @var int    $form_id               Form ID
 * @var int    $field_id              Field ID
 * @var int    $max_file_size         Maximum file size in bytes
 * @var int    $max_chunk_size        Maximum chunk size for upload
 * @var string $file_upload_button_text Button text
 */

?>
<div class="elzo-forms-file-drop-area elzo-forms-file-drop-area-style-<?php echo esc_attr((string) ($style ?? '')); ?> <?php echo $required ? 'elzo-forms-field-required' : ''; ?>" data-min-files="<?php echo esc_attr((string) ($min_files ?? '')); ?>" data-max-files="<?php echo esc_attr((string) ($max_files ?? '')); ?>">
    <div class="elzo-forms-file-drop-area-upload-list-item-template elzo-forms-file-drop-area-upload-list-item elzo-forms-d-flex" style="display:none">
        <div class="elzo-forms-file-drop-area-upload-list-item-thumbnail">
            <img src='data:image/svg+xml,%3csvg viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg"%3e%3cpath d="M5 1C3.91067 1 3 1.91067 3 3V19C3 20.0893 3.91067 21 5 21H17C18.0893 21 19 20.0893 19 19V7L13 1H5ZM12 8V3L17 8H12Z" fill="<?php echo esc_attr((string) ($file_icon_color ?? '')); ?>"/%3e%3c/svg%3e' width="40" height="40" alt="<?php echo esc_attr__('File', 'elzo-forms'); ?>">
        </div>
        <div class="elzo-forms-d-flex elzo-forms-flex-column elzo-forms-flex-1">
            <div class="elzo-forms-d-flex elzo-forms-align-items-center">
                <div class="elzo-forms-file-drop-area-upload-list-item-name-box elzo-forms-flex-1"></div>
                <button type="button" class="elzo-forms-file-drop-area-upload-list-item-remove" aria-label="<?php echo esc_attr__('Remove', 'elzo-forms'); ?>">&times;</button>
            </div>
            <div class="elzo-forms-d-flex elzo-forms-align-items-center">
                <div class="elzo-forms-file-drop-area-upload-list-item-progress elzo-forms-flex-1" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="<?php echo esc_attr__('Upload progress', 'elzo-forms'); ?>">
                    <div class="elzo-forms-file-drop-area-upload-list-item-progress-bar" style="width:0"></div>
                </div>
                <div class="elzo-forms-file-drop-area-upload-list-item-progress-percentage elzo-forms-text-825" aria-hidden="true">0%</div>
            </div>
        </div>
        <input type="hidden" class="elzo-forms-file-drop-area-upload-list-item-input" name="<?php echo esc_attr((string) ($name ?? '')); ?>[]" value="">
    </div>
    <div class="elzo-forms-file-drop-area-upload-list">
        
    </div>
    <?php if($style === 'drag-and-drop'){ ?>
        <div class="elzo-forms-file-drop-area-text"><?php echo esc_html($file_upload_text); ?></div>
        <div class="elzo-forms-file-drop-area-sub-text elzo-forms-text-825"><?php echo esc_html(implode('. ', array_filter([$file_types_text, $file_size_text]))); ?></div>
    <?php } ?>
    <input type="file" id="<?php echo esc_attr((string) ($id ?? '')); ?>" class="elzo-forms-file-upload-input" <?php if (!empty($described_by)) { ?>aria-describedby="<?php echo esc_attr($described_by); ?>"<?php } ?> <?php echo $multiple ? 'multiple' : ''; ?> <?php echo $allowed_file_types ? 'accept="' . esc_attr((string) $allowed_file_types) . '"' : ''; ?> data-form-id="<?php echo esc_attr((string) ($form_id ?? '')); ?>" data-field-id="<?php echo esc_attr((string) ($field_id ?? '')); ?>" data-max-file-size="<?php echo esc_attr((string) ($max_file_size ?? '')); ?>" data-max-chunk="<?php echo esc_attr((string) ($max_chunk_size ?? '')); ?>" style="display:none">
    <label for="<?php echo esc_attr((string) ($id ?? '')); ?>" class="elzo-forms-file-drop-area-button"><?php echo esc_html($file_upload_button_text); ?></label>
    <?php if($style !== 'drag-and-drop'){ ?>
        <span class="elzo-forms-file-drop-area-sub-text elzo-forms-text-825"><?php echo esc_html(implode('. ', array_filter([$file_types_text, $file_size_text]))); ?></span>
    <?php } ?>
</div>
