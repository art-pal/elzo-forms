<?php
/**
 * Import / Export screen: upload form.
 *
 * @var array  $view        Prepared by \ElzoForms\ImportExport\Admin_Controller::render_page().
 * @var string $upload_type "forms" or "submissions".
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included inside Admin_Controller::render_page(), so its variables are function-scoped, not globals.

$upload_input_id = 'elzo-forms-ie-file-' . $upload_type;
?>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="elzo-forms-ie-form">
    <input type="hidden" name="action" value="elzo_forms_import_upload">
    <input type="hidden" name="type" value="<?php echo esc_attr($upload_type); ?>">
    <?php wp_nonce_field('elzo_forms_import_upload_' . $upload_type); ?>

    <div class="elzo-forms-ie-dropzone" data-elzo-ie-dropzone>
        <label for="<?php echo esc_attr($upload_input_id); ?>" class="elzo-forms-ie-dropzone-text"><?php esc_html_e('Drop a JSON file here, or choose one:', 'elzo-forms'); ?></label>
        <input type="file" id="<?php echo esc_attr($upload_input_id); ?>" name="import_file" accept=".json,application/json" required data-elzo-ie-file>
        <span class="elzo-forms-ie-dropzone-file" data-elzo-ie-file-name aria-live="polite"></span>
    </div>
    <p class="description">
        <?php
        /* translators: %s: Maximum upload file size. */
        printf(esc_html__('Maximum file size: %s.', 'elzo-forms'), esc_html(size_format($view['max_upload_size'])));
        ?>
    </p>

    <?php submit_button(__('Upload and preview', 'elzo-forms'), 'secondary', 'submit', false); ?>
</form>
