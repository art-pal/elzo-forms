<?php
/**
 * File Field class.
 *
 * @package ElzoForms\Field
 */

namespace ElzoForms\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Field_File extends Field {

    /**
     * Render saved URLs as safe file links, with previews for local raster images.
     *
     * External/imported URLs remain links only; their extension proves nothing
     * about their contents and must never trigger a remote image request.
     *
     * @param mixed $value Saved URL or list of URLs.
     * @param array $context Submission presentation context.
     * @return string Safe HTML.
     */
    public function render_submission_value($value, array $context = []): string {
        $email = ($context['channel'] ?? 'admin') === 'email';
        $items = is_array($value) ? $value : [$value];
        $rows = [];
        foreach ($items as $item) {
            if ($item === null || $item === '') {
                continue;
            }
            $parts = is_string($item) ? wp_parse_url($item) : null;
            $url = is_string($item) ? esc_url($item, ['http', 'https']) : '';
            if (!$url || !is_array($parts) || empty($parts['host']) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
                $text = parent::render_submission_value($item, $context);
                $rows[] = $email ? '<tr><td colspan="2">' . $text . '</td></tr>' : '<li>' . $text . '</li>';
                continue;
            }

            $filename = rawurldecode(wp_basename($parts['path'] ?? ''));
            $name = $filename !== '' ? $filename : ($parts['host'] ?? $item);
            // This is a filename label only, not a MIME/image trust decision.
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $extension = preg_match('/\A[A-Za-z0-9]{1,10}\z/', $extension) ? strtoupper($extension) : '';
            $preview = \ElzoForms\Upload\Uploaded_File::preview_url($item, $email);
            $rows[] = $this->render_submission_file_link($url, esc_html($name), esc_url($preview), esc_html($extension), $email);
        }

        if (!$rows) {
            return '-';
        }
        if ($email) {
            $spacer = '<tr aria-hidden="true"><td colspan="2" height="12" style="height:12px;font-size:0;line-height:12px;">&nbsp;</td></tr>';

            return '<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">'
                . implode($spacer, $rows) . '</table>';
        }

        return '<ul class="elzo-submission-files">' . implode('', $rows) . '</ul>';
    }

    /**
     * Channel-specific markup; URL resolution and image checks stay shared.
     *
     * @param string $url Escaped original URL.
     * @param string $name Escaped filename.
     * @param string $preview Escaped preview URL, empty when unavailable.
     * @param string $extension Escaped extension label, empty when unavailable.
     * @param bool $email Whether to use email-compatible markup.
     * @return string Safe HTML for one file.
     */
    private function render_submission_file_link(string $url, string $name, string $preview, string $extension, bool $email): string {
        if ($email) {
            $extension_label = $extension !== ''
                ? '<span style="display:block;margin-top:6px;font-family:Arial,sans-serif;font-size:11px;font-weight:600;line-height:16px;letter-spacing:0.5px;">' . $extension . '</span>'
                : '';
            // A real square raster avoids relying on object-fit support in email.
            $visual = $preview !== ''
                ? '<img src="' . $preview . '" alt="" width="100" height="100" style="display:block;width:100px;height:100px;border:0;border-radius:4px;" />'
                : '<span aria-hidden="true" style="display:block;width:100px;height:100px;line-height:100px;text-align:center;font-size:0;color:#646970;background-color:#f0f0f1;border-radius:4px;">'
                    . '<span style="display:inline-block;vertical-align:middle;line-height:normal;"><span style="display:block;font-size:32px;line-height:32px;">&#128196;</span>'
                    . $extension_label . '</span></span>';

            return '<tr><td width="100" height="100" valign="middle" style="width:100px;height:100px;padding:0;vertical-align:middle;">'
                . '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" style="display:block;text-decoration:none;">' . $visual . '</a></td>'
                . '<td valign="middle" style="padding:0 0 0 12px;vertical-align:middle;overflow-wrap:anywhere;word-break:break-word;">'
                . '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" style="color:#0073aa;text-decoration:underline;">' . $name . '</a></td></tr>';
        }

        $visual = $preview !== ''
            ? '<img class="elzo-submission-file-preview" src="' . $preview . '" alt="" width="100" height="100" loading="lazy" />'
            : '<span class="dashicons dashicons-media-default" aria-hidden="true"></span>'
                . ($extension !== '' ? '<span class="elzo-submission-file-extension">' . $extension . '</span>' : '');

        return '<li><a class="elzo-submission-file-link" href="' . $url . '" target="_blank" rel="noopener noreferrer">'
            . '<span class="elzo-submission-file-visual" aria-hidden="true">' . $visual . '</span>'
            . '<span class="elzo-submission-file-name">' . $name . '</span></a></li>';
    }

    /**
     * Get field defaults.
     */
    protected function get_defaults(): array {
        return array_merge(parent::get_defaults(), [
            'type' => 'file',
            'allowed_types' => [],
            'max_size' => 0,
            'multiple' => false,
            'min_files' => 0,
            'max_files' => 0,
            'max_file_size' => 0,
            'allowed_file_types' => '',
        ]);
    }

    /**
     * Get allowed operators for conditional logic.
     *
     * A file field compares against the upload URLs it submits, one per
     * uploaded file, so only the operators that mean something against those
     * are offered. An empty value with "is" / "is not" asks whether the field
     * holds a file at all, and "contains" matches the extension the URL keeps.
     *
     * The excluded ones would each be a trap: the URL ends in the upload
     * token rather than the file name, so "ends with" can never match an
     * extension; every URL starts with the same uploads path, so "starts
     * with" says nothing about the file; and the URL is not a number, so the
     * numeric comparisons are meaningless.
     *
     * A field that accepts several files can also be asked how many were
     * uploaded, which no value operator can answer.
     *
     * @return array|null
     */
    public function get_logic_operators(): ?array {
        $operators = ['==', '!=', 'like', 'not_like'];

        return empty($this->get('multiple'))
            ? $operators
            : array_merge($operators, \ElzoForms\Utilities\Conditional_Logic::get_count_operators());
    }

    /**
     * The stored name is randomized, so only the extension is worth matching.
     *
     * @return string
     */
    public function get_logic_value_placeholder(): string {
        return __('.pdf, or empty for any uploaded file', 'elzo-forms');
    }

    /**
     * Validate field value.
     *
     * @param mixed $value Field value (file URLs)
     * @return true|\WP_Error
     */
    public function validate($value) {
        $urls = self::normalize_file_urls($value);
        $file_count = count($urls);
        $multiple = !empty($this->get('multiple'));
        $required = $this->is_required();

        if ($multiple) {
            $min_files = absint($this->get('min_files', 0));
            $max_files = absint($this->get('max_files', 0));

            if ($max_files > 0 && $min_files > $max_files) {
                $max_files = $min_files;
            }
        } else {
            // A single-file field needs no minimum: "required" already covers it.
            $min_files = 0;
            $max_files = 1;
        }

        if ($required && $file_count < 1) {
            return new \WP_Error(
                'required_file',
                /* translators: %s: Field title in bold. */
                sprintf(esc_html__('Field %s is required', 'elzo-forms'), '<strong>"' . esc_html($this->get_title()) . '"</strong>')
            );
        }

        /*
         * Min files constrains an upload that has been started, it does not force
         * one: an optional field stays submittable while nothing is uploaded.
         * Marking the field required is what makes an empty upload invalid.
         */
        if ($min_files > 0 && $file_count > 0 && $file_count < $min_files) {
            return new \WP_Error(
                'min_files',
                /* translators: 1: Field title, 2: Minimum number of files. */
                sprintf(esc_html__('Field %1$s requires at least %2$d files', 'elzo-forms'), '<strong>"' . esc_html($this->get_title()) . '"</strong>', $min_files)
            );
        }

        if ($max_files > 0 && $file_count > $max_files) {
            return new \WP_Error(
                'max_files',
                /* translators: 1: Field title, 2: Maximum number of files. */
                sprintf(esc_html__('Field %1$s allows up to %2$d files', 'elzo-forms'), '<strong>"' . esc_html($this->get_title()) . '"</strong>', $max_files)
            );
        }

        foreach ($urls as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return new \WP_Error(
                    'invalid_file_url',
                    /* translators: %s: Field title. */
                    sprintf(esc_html__('Invalid file URL for %s', 'elzo-forms'), esc_html($this->get_title()))
                );
            }
        }

        return true;
    }

    /**
     * Sanitize and verify submitted upload references without moving files.
     *
     * @param mixed $value Raw submitted file URL or URL list.
     * @return array|\WP_Error Verified, sanitized temporary upload URLs.
     */
    public function sanitize_input($value) {
        if (is_array($value)) {
            foreach ($value as $file_url) {
                if (!is_string($file_url)) {
                    return $this->get_invalid_upload_error();
                }
            }
        } elseif (!is_string($value)) {
            return $this->get_invalid_upload_error();
        }

        $file_urls = self::normalize_file_urls($value);
        $sanitized_urls = [];

        foreach ($file_urls as $file_url) {
            $sanitized_url = esc_url_raw($file_url);
            if (empty($sanitized_url) || !filter_var($sanitized_url, FILTER_VALIDATE_URL)) {
                return $this->get_invalid_upload_error();
            }

            $verified_upload = \ElzoForms\Upload\File_Upload_Handler::resolve_submitted_file_url(
                $sanitized_url,
                (string) $this->get_id(),
                $this->get_form_id()
            );
            if (!$verified_upload || !file_exists((string) $verified_upload['path'])) {
                return $this->get_invalid_upload_error();
            }

            $sanitized_urls[] = $sanitized_url;
        }

        return array_values(array_unique($sanitized_urls));
    }

    /**
     * Move verified uploads after every submitted field has passed validation.
     *
     * Upload sessions are consumed only after all files have moved. If a move
     * fails, completed moves are rolled back and the sessions remain available
     * for a safe retry.
     *
     * @param mixed $value Sanitized and validated temporary upload URLs.
     * @return array|\WP_Error Permanent upload URLs, or an error on failure.
     */
    public function finalize_submission_value($value) {
        $file_urls = self::normalize_file_urls($value);
        if (empty($file_urls)) {
            return [];
        }

        $verified_uploads = [];
        foreach ($file_urls as $file_url) {
            $verified_upload = \ElzoForms\Upload\File_Upload_Handler::resolve_submitted_file_url(
                $file_url,
                (string) $this->get_id(),
                $this->get_form_id()
            );
            if (!$verified_upload || !file_exists((string) $verified_upload['path'])) {
                return $this->get_invalid_upload_error();
            }

            $verified_uploads[] = $verified_upload;
        }

        $upload_dir = wp_upload_dir();
        $custom_upload_dir = \ElzoForms\Upload\File_Upload_Handler::create_permanent_upload_directory($upload_dir);
        if ($custom_upload_dir === '') {
            return new \WP_Error(
                'file_finalize_failed',
                esc_html__('The uploaded file could not be saved. Please try again.', 'elzo-forms')
            );
        }

        if (!class_exists('\WP_Filesystem_Base', false)) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        }

        if (!class_exists('\WP_Filesystem_Direct', false)) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        }

        $filesystem = new \WP_Filesystem_Direct(false);
        $completed_moves = [];
        $permanent_urls = [];

        foreach ($verified_uploads as $verified_upload) {
            $source_path = (string) $verified_upload['path'];
            $file_name = \ElzoForms\Upload\File_Upload_Handler::generate_random_upload_filename(basename($source_path), $custom_upload_dir);
            if ($file_name === '') {
                foreach (array_reverse($completed_moves) as $completed_move) {
                    $filesystem->move($completed_move['destination'], $completed_move['source'], true);
                }

                return new \WP_Error(
                    'file_finalize_failed',
                    esc_html__('The uploaded file could not be saved. Please try again.', 'elzo-forms')
                );
            }
            $destination_path = $custom_upload_dir . '/' . $file_name;

            if (!$filesystem->move($source_path, $destination_path, true)) {
                foreach (array_reverse($completed_moves) as $completed_move) {
                    $filesystem->move($completed_move['destination'], $completed_move['source'], true);
                }

                return new \WP_Error(
                    'file_finalize_failed',
                    esc_html__('The uploaded file could not be saved. Please try again.', 'elzo-forms')
                );
            }

            $completed_moves[] = [
                'source' => $source_path,
                'destination' => $destination_path,
            ];
            $permanent_urls[] = esc_url_raw(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $destination_path));
        }

        foreach ($verified_uploads as $verified_upload) {
            \ElzoForms\Upload\File_Upload_Handler::consume_upload_session((string) $verified_upload['upload_id']);
        }

        return $permanent_urls;
    }

    /**
     * Sanitize a file value through the complete one-call workflow.
     *
     * The submission handler runs sanitize_input() and
     * finalize_submission_value() separately so the file is moved only after
     * every field has validated. This composition is what a caller reaching
     * for sanitize() alone gets instead.
     *
     * @param mixed $value Field value (file URLs array).
     * @return array
     */
    public function sanitize($value) {
        $sanitized_value = $this->sanitize_input($value);
        if (is_wp_error($sanitized_value)) {
            return [];
        }

        $finalized_value = $this->finalize_submission_value($sanitized_value);
        return is_wp_error($finalized_value) ? [] : $finalized_value;
    }

    /**
     * Get the generic error for an invalid or expired upload reference.
     *
     * @return \WP_Error
     */
    private function get_invalid_upload_error(): \WP_Error {
        return new \WP_Error(
            'invalid_file_upload',
            /* translators: %s: Field title. */
            sprintf(esc_html__('Invalid or expired upload for %s', 'elzo-forms'), esc_html($this->get_title()))
        );
    }

    /**
     * Validate MIME types.
     *
     * @param string $file_path File path
     * @return bool
     */
    public function validate_mime_type(string $file_path): bool {
        $allowed_types = $this->get('allowed_types', []);

        if (empty($allowed_types)) {
            return true; // No restrictions
        }

        $file_type = wp_check_filetype($file_path);
        return in_array($file_type['type'], $allowed_types, true);
    }

    /**
     * Prepare data for template rendering.
     *
     * @param array $context Additional context data
     * @return array Data to be extracted in template
     */
    public function get_data(array $context = []): array {
        $data = parent::get_data($context);

        $styles = \ElzoForms\Utilities\Helpers::get_field_file_styles();
        $style = !empty($this->get('style')) && in_array($this->get('style'), array_keys($styles), true)
            ? $this->get('style')
            : array_key_first($styles);

        $multiple = !empty($this->get('multiple'));
        $min_files = $multiple ? absint($this->get('min_files', 0)) : 0;
        $max_files = $multiple ? absint($this->get('max_files', 0)) : 0;

        if ($max_files > 0 && $min_files > $max_files) {
            $max_files = $min_files;
        }

        $data['form_id'] = $context['form_id'] ?? null;
        $data['style'] = $style;
        $data['multiple'] = $multiple;
        $data['min_files'] = $multiple && $min_files > 0 ? $min_files : '';
        $data['max_files'] = $multiple && $max_files > 0 ? $max_files : '';
        $max_file_size_bytes = \ElzoForms\Upload\File_Upload_Handler::get_effective_max_file_size_bytes($this->get('max_file_size', 0));
        $data['max_file_size'] = round($max_file_size_bytes / 1024 / 1024, 2);
        $configured_allowed_file_types = !empty($this->get('allowed_file_types')) ? $this->get('allowed_file_types') : '';
        $default_allowed_file_types = \ElzoForms\Upload\File_Upload_Handler::get_default_allowed_file_types_string();
        $data['allowed_file_types'] = $configured_allowed_file_types ?: $default_allowed_file_types;
        $data['file_upload_text'] = !empty($this->get('file_upload_text'))
            ? $this->get('file_upload_text')
            : ($context['texts_settings']['file_upload_text'] ?? '');
        $data['file_upload_button_text'] = !empty($this->get('file_upload_button_text'))
            ? $this->get('file_upload_button_text')
            : ($context['texts_settings']['file_upload_button_text'] ?? '');

        // Keep each request below the server limit while the complete upload is
        // independently capped by max_file_size.
        $data['max_chunk_size'] = max(0.1, round(($max_file_size_bytes / 1024 / 1024) * 0.8, 2));

        // File icon color from style settings
        $style_settings = $context['style_settings'] ?? [];
        $primary_color = $style_settings['primary_color'] ?? '#000000';
        $data['file_icon_color'] = str_replace('#', '%23', $primary_color);

        // Descriptive text for file types and size
        $data['file_types_text'] = !empty($data['allowed_file_types'])
            /* translators: %s: Comma-separated list of accepted file types. */
            ? sprintf(__('Accepted file types: %s', 'elzo-forms'), $data['allowed_file_types'])
            : null;
        $data['file_size_text'] = $data['max_file_size']
            /* translators: %s: Maximum file size in megabytes. */
            ? sprintf(__('Allowed file size: up to %s MB', 'elzo-forms'), $data['max_file_size'])
            : null;

        return $data;
    }

    /**
     * Render field-specific settings in admin for a specific tab.
     *
     * @param string $tab The settings tab (general, view, logic, admin, etc.)
     */
    public function render_field_settings(string $tab): void {
        $field = $this->to_array();
        $field_id = $this->get_id();
        $step_index = $this->get('step_index', 0);
        $field_index = $this->get('index', 0);

        if ($tab === 'general') {
            $multiple = !empty($this->get('multiple'));
            $min_files = !empty($this->get('min_files')) ? absint($this->get('min_files')) : '';
            $max_files = !empty($this->get('max_files')) ? absint($this->get('max_files')) : '';

            if ($max_files && $min_files && $min_files > $max_files) {
                $max_files = $min_files;
            }

            $max_file_size = !empty($this->get('max_file_size')) ? absint($this->get('max_file_size')) : '';
            $allowed_file_types = !empty($this->get('allowed_file_types')) ? $this->get('allowed_file_types') : '';
            $default_allowed_file_types = \ElzoForms\Upload\File_Upload_Handler::get_default_allowed_file_types_string();
        ?>
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-allowed-file-types-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Allowed file types', 'elzo-forms'); ?></label>
            <?php /* translators: %s is the default allowed file types. */ ?>
            <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][allowed_file_types]" value="<?php echo esc_attr($allowed_file_types); ?>" id="elzo-forms-field-allowed-file-types-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" placeholder="<?php echo esc_attr(sprintf(__('Default: %s', 'elzo-forms'), $default_allowed_file_types)); ?>">
        </div>
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-max-file-size-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Max file size (MB)', 'elzo-forms'); ?></label>
            <input type="number" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][max_file_size]" value="<?php echo esc_attr($max_file_size); ?>" id="elzo-forms-field-max-file-size-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" placeholder="-">
        </div>
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-multiple-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label">
                <input type="checkbox" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][multiple]" value="Checked" id="elzo-forms-field-multiple-<?php echo esc_attr($field_id); ?>" <?php checked($multiple); ?> class="elzo-forms-field-check">
                <?php esc_html_e('Allow multiple files', 'elzo-forms'); ?>
            </label>
        </div>
        <div class="elzo-forms-row elzo-forms-row-2-cols elzo-forms-field-control-group" data-ef-logic='[[{"type":"input","operator":"==","settings":{"id":"elzo-forms-field-multiple-<?php echo esc_attr($field_id); ?>","value":"Checked"}}]]' <?php echo $multiple ? '' : 'style="display:none"'; ?>>
            <div class="elzo-forms-column">
                <div class="elzo-forms-field-control-group">
                    <label for="elzo-forms-field-min-files-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Minimum files', 'elzo-forms'); ?></label>
                    <input type="number" min="1" step="1" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][min_files]" value="<?php echo esc_attr($min_files); ?>" id="elzo-forms-field-min-files-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" placeholder="-">
                </div>
            </div>
            <div class="elzo-forms-column">
                <div class="elzo-forms-field-control-group">
                    <label for="elzo-forms-field-max-files-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Maximum files', 'elzo-forms'); ?></label>
                    <input type="number" min="1" step="1" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][max_files]" value="<?php echo esc_attr($max_files); ?>" id="elzo-forms-field-max-files-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" placeholder="-">
                </div>
            </div>
        </div>
        <?php
        } elseif ($tab === 'view') {
            $field = $this->to_array();
            $field_id = $this->get_id();
            $step_index = $this->get('step_index', 0);
            $field_index = $this->get('index', 0);

            $styles = \ElzoForms\Utilities\Helpers::get_field_file_styles();
            $style = !empty($this->get('style')) && in_array($this->get('style'), array_keys($styles), true) ? $this->get('style') : array_key_first($styles);
            $default_texts_settings = \ElzoForms\Services\Settings::get_default_texts();
            $file_upload_text = !empty($this->get('file_upload_text')) ? $this->get('file_upload_text') : '';
            $file_upload_button_text = !empty($this->get('file_upload_button_text')) ? $this->get('file_upload_button_text') : '';
        ?>
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-style-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('Style', 'elzo-forms'); ?></label>
            <select name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][style]" class="elzo-forms-field-control elzo-forms-field-style-select" id="elzo-forms-field-style-<?php echo esc_attr($field_id); ?>">
                <?php foreach($styles as $option_value => $option_label): ?>
                    <option value="<?php echo esc_attr($option_value); ?>" <?php selected($style, $option_value); ?>><?php echo esc_html($option_label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-file-upload-text-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('File upload text', 'elzo-forms'); ?></label>
            <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][file_upload_text]" value="<?php echo esc_attr($file_upload_text); ?>" id="elzo-forms-field-file-upload-text-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" placeholder="<?php echo esc_attr($default_texts_settings['file_upload_text']); ?>">
        </div>
        <div class="elzo-forms-field-control-group">
            <label for="elzo-forms-field-file-upload-button-text-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control-label"><?php esc_html_e('File upload button text', 'elzo-forms'); ?></label>
            <input type="text" name="elzo_form_fields[<?php echo esc_attr($step_index); ?>][fields][<?php echo esc_attr($field_index); ?>][file_upload_button_text]" value="<?php echo esc_attr($file_upload_button_text); ?>" id="elzo-forms-field-file-upload-button-text-<?php echo esc_attr($field_id); ?>" class="elzo-forms-field-control" placeholder="<?php echo esc_attr($default_texts_settings['file_upload_button_text']); ?>">
        </div>
        <?php
        }

        parent::render_field_settings($tab);
    }

    /**
     * Normalize file URLs to a unique non-empty list.
     *
     * @param mixed $value Field value from submission payload
     * @return array
     */
    protected static function normalize_file_urls($value): array {
        if (is_array($value)) {
            $urls = $value;
        } elseif ($value === null || $value === '') {
            $urls = [];
        } else {
            $urls = [$value];
        }

        $urls = array_map(static function ($url) {
            return is_string($url) ? trim($url) : '';
        }, $urls);

        return array_values(array_unique(array_filter($urls, static function ($url) {
            return $url !== '';
        })));
    }
}
