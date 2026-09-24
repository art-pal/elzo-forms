<?php
/**
 * Import / Export admin screen and request handlers.
 *
 * Downloads and every step that writes data go through admin-post.php with a
 * nonce and a capability check. The screen itself only renders forms and
 * previews; the preview of an uploaded file is rebuilt from the stored upload
 * on every request, so nothing a browser posts back is trusted beyond the
 * choices it makes.
 *
 * @package ElzoForms\ImportExport
 */

namespace ElzoForms\ImportExport;

// Exit if accessed directly
defined('ABSPATH') || exit;

final class Admin_Controller {

    /** Admin page slug. */
    public const PAGE_SLUG = 'elzo-forms-import-export';

    /** Bulk action of the forms and submissions lists. */
    public const BULK_ACTION = 'elzo_forms_export';

    /** Parent menu of the screen. */
    private const PARENT_SLUG = 'edit.php?post_type=elzo_form';

    /** User meta holding submissions selected in the submissions list. */
    private const SELECTION_META = '_elzo_forms_export_selection';

    /** Upper bound of a stored submission selection. */
    private const MAX_SELECTION = 5000;

    /** Transient prefix of the per-user result notice. */
    private const NOTICE_TRANSIENT_PREFIX = 'elzo_forms_import_export_notice_';

    /** Upper bound of detail lines in a result notice. */
    private const MAX_NOTICE_DETAILS = 30;

    /** Anchor of the Export panel on the screen. */
    public const EXPORT_ANCHOR = 'elzo-forms-ie-export';

    /** Anchor of the Import panel on the screen. */
    public const IMPORT_ANCHOR = 'elzo-forms-ie-import';

    /** @var string */
    private static $hook_suffix = '';

    /** @var bool */
    private static $initialized = false;

    /**
     * Register admin hooks.
     *
     * @return void
     */
    public static function init(): void {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        add_action('admin_menu', [__CLASS__, 'register_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_title_actions']);

        add_action('admin_post_elzo_forms_export_forms', [__CLASS__, 'handle_export_forms']);
        add_action('admin_post_elzo_forms_export_submissions', [__CLASS__, 'handle_export_submissions']);
        add_action('admin_post_elzo_forms_export_submission', [__CLASS__, 'handle_export_submission']);
        add_action('admin_post_elzo_forms_import_upload', [__CLASS__, 'handle_upload']);
        add_action('admin_post_elzo_forms_import_confirm', [__CLASS__, 'handle_confirm']);
        add_action('admin_post_elzo_forms_import_cancel', [__CLASS__, 'handle_cancel']);

        add_filter('post_row_actions', [__CLASS__, 'add_form_row_action'], 10, 2);
        add_filter('bulk_actions-edit-elzo_form', [__CLASS__, 'add_forms_bulk_action']);
        add_filter('bulk_actions-edit-elzo_submission', [__CLASS__, 'add_submissions_bulk_action']);
        add_filter('handle_bulk_actions-edit-elzo_form', [__CLASS__, 'handle_forms_bulk_action'], 10, 3);
        add_filter('handle_bulk_actions-edit-elzo_submission', [__CLASS__, 'handle_submissions_bulk_action'], 10, 3);
    }

    /**
     * Get the capability an operation requires.
     *
     * @param string $operation "page", "export_forms", "export_sensitive_settings",
     *                          "import_forms", "export_submissions" or "import_submissions".
     * @return string
     */
    public static function capability(string $operation): string {
        /**
         * Filters the capability an Import / Export operation requires.
         *
         * @filter elzo_forms/import_export/capability
         * @param string $capability Required capability. Default "manage_options".
         * @param string $operation  "page", "export_forms", "export_sensitive_settings",
         *                           "import_forms", "export_submissions" or "import_submissions".
         */
        $capability = apply_filters('elzo_forms/import_export/capability', 'manage_options', $operation);

        return is_string($capability) && $capability !== '' ? $capability : 'manage_options';
    }

    /**
     * Whether the current user may perform an operation.
     *
     * @param string $operation Operation, see capability().
     * @return bool
     */
    public static function can(string $operation): bool {
        return current_user_can(self::capability($operation));
    }

    /**
     * Get the URL of the screen.
     *
     * @param string $tab "forms" or "submissions".
     * @param array $args Extra query arguments.
     * @return string
     */
    public static function page_url(string $tab = 'forms', array $args = []): string {
        return add_query_arg(array_merge([
            'post_type' => 'elzo_form',
            'page' => self::PAGE_SLUG,
            'tab' => $tab === 'submissions' ? 'submissions' : 'forms',
        ], $args), admin_url('edit.php'));
    }

    /**
     * Register the submenu page.
     *
     * @return void
     */
    public static function register_page(): void {
        $hook_suffix = add_submenu_page(
            self::PARENT_SLUG,
            __('Import / Export', 'elzo-forms'),
            __('Import / Export', 'elzo-forms'),
            self::capability('page'),
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );

        self::$hook_suffix = is_string($hook_suffix) ? $hook_suffix : '';
    }

    /**
     * Load the screen's assets on the screen only.
     *
     * @param string $hook_suffix Current admin page.
     * @return void
     */
    public static function enqueue_assets($hook_suffix): void {
        if (self::$hook_suffix === '' || $hook_suffix !== self::$hook_suffix) {
            return;
        }

        $base_url = plugin_dir_url(ELZO_FORMS_FILE);
        wp_enqueue_style('elzo-forms-import-export', $base_url . 'assets/css/elzo-forms-import-export.css', [], ELZO_FORMS_VERSION);
        wp_enqueue_script('elzo-forms-import-export', $base_url . 'assets/js/elzo-forms-import-export.js', [], ELZO_FORMS_VERSION, true);
        wp_localize_script('elzo-forms-import-export', 'ElzoFormsImportExport', [
            /* translators: %s: File name. */
            'selectedFile' => __('Selected file: %s', 'elzo-forms'),
        ]);
    }

    /**
     * Add Import and Export buttons next to the heading of the form and
     * submission lists and edit screens.
     *
     * WordPress has no hook for the buttons beside a screen heading, so a
     * small script places them there, as it does for "Add New". Without
     * the script the screen stays reachable from the admin menu.
     *
     * @return void
     */
    public static function enqueue_title_actions(): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->base, ['edit', 'post'], true)) {
            return;
        }

        $actions = self::title_actions((string) $screen->id, $screen->base === 'post' ? get_post() : null);
        if (!$actions) {
            return;
        }

        wp_enqueue_script('elzo-forms-title-actions', plugin_dir_url(ELZO_FORMS_FILE) . 'assets/js/elzo-forms-title-actions.js', [], ELZO_FORMS_VERSION, true);
        wp_localize_script('elzo-forms-title-actions', 'ElzoFormsTitleActions', ['actions' => $actions]);
    }

    /**
     * Get the Import and Export buttons of a screen.
     *
     * On a list screen both buttons open the Import / Export screen. On an
     * edit screen Export takes the item shown: a form downloads at once, a
     * submission opens the export options with the submission selected.
     *
     * @param string $screen_id Screen ID: "edit-elzo_form", "elzo_form",
     *                          "edit-elzo_submission" or "elzo_submission".
     * @param \WP_Post|null $post Post of an edit screen.
     * @return array<int, array{label: string, url: string, title: string}>
     */
    public static function title_actions(string $screen_id, ?\WP_Post $post = null): array {
        $screens = [
            'edit-elzo_form' => ['forms', null],
            'elzo_form' => ['forms', 'elzo_form'],
            'edit-elzo_submission' => ['submissions', null],
            'elzo_submission' => ['submissions', 'elzo_submission'],
        ];
        if (!isset($screens[$screen_id]) || !self::can('page')) {
            return [];
        }

        [$type, $post_type] = $screens[$screen_id];
        $actions = [];

        if (self::can('import_' . $type)) {
            $actions[] = [
                'label' => __('Import', 'elzo-forms'),
                'url' => self::page_url($type) . '#' . self::IMPORT_ANCHOR,
                'title' => $type === 'forms' ? __('Import forms from a JSON file', 'elzo-forms') : __('Import submissions from a JSON file', 'elzo-forms'),
            ];
        }

        if (!self::can('export_' . $type)) {
            return $actions;
        }

        if ($post_type === null) {
            $actions[] = [
                'label' => __('Export', 'elzo-forms'),
                'url' => self::page_url($type) . '#' . self::EXPORT_ANCHOR,
                'title' => $type === 'forms' ? __('Export forms to a JSON file', 'elzo-forms') : __('Export submissions to a CSV or JSON file', 'elzo-forms'),
            ];

            return $actions;
        }

        // A new or trashed item has nothing to export.
        if (!$post instanceof \WP_Post || $post->post_type !== $post_type || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            return $actions;
        }

        $actions[] = $type === 'forms'
            ? [
                'label' => __('Export', 'elzo-forms'),
                'url' => self::form_export_url((int) $post->ID),
                'title' => __('Download the saved version of this form as a JSON file', 'elzo-forms'),
            ]
            : [
                'label' => __('Export', 'elzo-forms'),
                'url' => add_query_arg([
                    'action' => 'elzo_forms_export_submission',
                    'post' => (int) $post->ID,
                    '_wpnonce' => wp_create_nonce('elzo_forms_export_submission_' . (int) $post->ID),
                ], admin_url('admin-post.php')),
                'title' => __('Choose a format and download this submission', 'elzo-forms'),
            ];

        return $actions;
    }

    /**
     * Get the URL that downloads one form.
     *
     * @param int $form_id Form ID.
     * @return string
     */
    private static function form_export_url(int $form_id): string {
        return add_query_arg([
            'action' => 'elzo_forms_export_forms',
            'form_ids' => $form_id,
            '_wpnonce' => wp_create_nonce('elzo_forms_export_forms'),
        ], admin_url('admin-post.php'));
    }

    /**
     * Render the screen.
     *
     * @return void
     */
    public static function render_page(): void {
        if (!self::can('page')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'elzo-forms'), '', ['response' => 403]);
        }

        $tab = self::query_key('tab') === 'submissions' ? 'submissions' : 'forms';
        $view = [
            'tab' => $tab,
            'tabs' => [
                'forms' => [
                    'label' => __('Forms', 'elzo-forms'),
                    'url' => self::page_url('forms'),
                ],
                'submissions' => [
                    'label' => __('Submissions', 'elzo-forms'),
                    'url' => self::page_url('submissions'),
                ],
            ],
            'notice' => self::pull_notice(),
            'can' => [],
            'max_upload_size' => (int) wp_max_upload_size(),
        ];

        foreach (['export_forms', 'export_sensitive_settings', 'import_forms', 'export_submissions', 'import_submissions'] as $operation) {
            $view['can'][$operation] = self::can($operation);
        }

        $view = array_merge($view, $tab === 'submissions' ? self::submissions_view($view['can']) : self::forms_view($view['can']));

        include ELZO_FORMS_PATH . 'admin/templates/admin-import-export.php';
    }

    /**
     * Prepare the Forms tab.
     *
     * @param array<string, bool> $can Permitted operations.
     * @return array
     */
    private static function forms_view(array $can): array {
        $statuses = [];
        foreach (Form_Exporter::STATUSES as $status) {
            $statuses[$status] = self::status_label($status);
        }

        return [
            'forms' => $can['export_forms'] ? (new Form_Exporter())->get_forms() : [],
            'statuses' => $statuses,
            'pending' => $can['import_forms'] ? self::pending_view(Pending_Import::TYPE_FORMS) : null,
        ];
    }

    /**
     * Prepare the Submissions tab.
     *
     * @param array<string, bool> $can Permitted operations.
     * @return array
     */
    private static function submissions_view(array $can): array {
        return [
            'form_choices' => self::form_choices(),
            'selection' => $can['export_submissions'] && self::query_key('selection') === '1' ? self::get_selection() : [],
            'pending' => $can['import_submissions'] ? self::pending_view(Pending_Import::TYPE_SUBMISSIONS) : null,
        ];
    }

    /**
     * Prepare the preview of a stored upload.
     *
     * @param string $type Pending import type.
     * @return array|null
     */
    private static function pending_view(string $type): ?array {
        $pending = Pending_Import::get($type);
        if (!$pending) {
            return null;
        }

        // Reading holds the stored and the decrypted file at once, so the
        // limit is raised first.
        wp_raise_memory_limit('admin');
        $contents = Pending_Import::read($type);
        $package = $contents !== null ? Package::parse($contents) : null;
        unset($contents);
        if (!$package instanceof Package) {
            Pending_Import::discard($type);
            return [
                'error' => is_wp_error($package) ? $package->get_error_message() : __('The uploaded file could not be read. Upload it again.', 'elzo-forms'),
            ];
        }

        $view = [
            'name' => $pending['name'],
            'notes' => self::package_notes($package),
        ];

        if ($type === Pending_Import::TYPE_FORMS) {
            $view['rows'] = (new Form_Importer())->preview($package, Form_Importer::ACTION_COPY);
            $view['submission_count'] = count($package->submissions());
        } else {
            $view['rows'] = $package->forms() ? (new Form_Importer())->preview($package, Form_Importer::ACTION_SKIP) : [];
            $view['summary'] = (new Submission_Importer())->preview($package);
            $view['destinations'] = self::form_choices();
        }

        return $view;
    }

    /**
     * Describe a package for the preview.
     *
     * @param Package $package Parsed package.
     * @return string[]
     */
    private static function package_notes(Package $package): array {
        $notes = [];

        if ($package->is_legacy()) {
            $notes[] = __('This is a single-form file of a developer JSON source. It is imported like an export file.', 'elzo-forms');
        }

        $timestamp = $package->exported_at() !== '' ? strtotime($package->exported_at()) : false;
        $date = $timestamp ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : '';
        if ($package->plugin_version() !== '' && $date) {
            /* translators: 1: Elzo Forms version. 2: Export date. */
            $notes[] = sprintf(__('Exported with Elzo Forms %1$s on %2$s.', 'elzo-forms'), $package->plugin_version(), $date);
        } elseif ($package->plugin_version() !== '') {
            /* translators: %s: Elzo Forms version. */
            $notes[] = sprintf(__('Exported with Elzo Forms %s.', 'elzo-forms'), $package->plugin_version());
        }

        if ($package->includes_sensitive()) {
            $notes[] = __('This file contains sensitive settings such as API keys. Delete it once it is no longer needed.', 'elzo-forms');
        }

        return $notes;
    }

    /**
     * Get the forms an administrator can pick, by ID.
     *
     * @return array<int, string>
     */
    private static function form_choices(): array {
        $choices = [];
        $posts = get_posts([
            'post_type' => 'elzo_form',
            'post_status' => Form_Exporter::STATUSES,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        foreach ($posts as $post) {
            if ($post instanceof \WP_Post) {
                $choices[(int) $post->ID] = self::form_label($post);
            }
        }

        return $choices;
    }

    /**
     * Get the display label of a form.
     *
     * @param \WP_Post $post Form post.
     * @return string
     */
    public static function form_label(\WP_Post $post): string {
        $title = (string) $post->post_title;

        return $title !== '' ? $title : __('(no title)', 'elzo-forms');
    }

    /**
     * Get the label of a post status.
     *
     * @param string $status Post status.
     * @return string
     */
    public static function status_label(string $status): string {
        $labels = [
            'publish' => __('Published', 'elzo-forms'),
            'draft' => __('Draft', 'elzo-forms'),
            'pending' => __('Pending', 'elzo-forms'),
            'private' => __('Private', 'elzo-forms'),
            'future' => __('Scheduled', 'elzo-forms'),
        ];

        return $labels[$status] ?? ($status !== '' ? $status : __('Draft', 'elzo-forms'));
    }

    /**
     * Download the requested forms.
     *
     * @return void
     */
    public static function handle_export_forms(): void {
        self::verify('elzo_forms_export_forms', 'export_forms');

        // "Select all" exports every form, whatever else is ticked. Row and bulk
        // actions send the chosen form IDs only.
        $form_ids = self::request_ids('form_ids');
        $all = self::request_bool('all_forms');

        if (!$all && !$form_ids) {
            self::redirect_with_notice('forms', 'error', __('Select at least one form to export.', 'elzo-forms'));
        }

        $statuses = self::request_list('statuses');
        if (!$statuses && self::request_key('statuses_submitted') === '') {
            // Row and bulk actions export the chosen forms in any status but Trash.
            $statuses = Form_Exporter::STATUSES;
        }

        $include_sensitive = self::request_bool('include_sensitive') && self::can('export_sensitive_settings');
        $exporter = new Form_Exporter();
        $posts = $exporter->get_forms($all ? [] : $form_ids, $statuses);

        if (!$posts) {
            self::redirect_with_notice('forms', 'error', __('No forms match the selected options.', 'elzo-forms'));
        }

        $json = wp_json_encode($exporter->build_package($posts, $include_sensitive), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            self::redirect_with_notice('forms', 'error', __('The export file could not be created.', 'elzo-forms'));
        }

        $name = count($posts) === 1 ? 'elzo-form-' . Form_Exporter::form_key($posts[0]) : 'elzo-forms-forms';
        self::start_download($name . '-' . self::file_timestamp() . '.json', 'application/json');

        $handle = fopen('php://output', 'wb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streams the download to the response body.
        fwrite($handle, $json); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streams the download to the response body.
        fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the response body stream.
        exit;
    }

    /**
     * Download the requested submissions.
     *
     * @return void
     */
    public static function handle_export_submissions(): void {
        self::verify('elzo_forms_export_submissions', 'export_submissions');

        $format = self::request_key('format') === 'json' ? 'json' : 'csv';
        $use_selection = self::request_bool('use_selection');
        $exporter = new Submission_Exporter(self::submission_export_filters($format));
        $filters = $exporter->get_filters();

        if ($use_selection && !$filters['ids']) {
            self::redirect_with_notice('submissions', 'error', __('The selection of submissions is no longer available. Select them in the Submissions list again.', 'elzo-forms'));
        }

        if (!$use_selection && !self::request_bool('all_forms') && !$filters['form_ids']) {
            self::redirect_with_notice('submissions', 'error', __('Select at least one form to export.', 'elzo-forms'));
        }

        if ($filters['date_from'] !== '' && $filters['date_to'] !== '' && $filters['date_from'] > $filters['date_to']) {
            self::redirect_with_notice('submissions', 'error', __('The start date is after the end date.', 'elzo-forms'));
        }

        if (!self::has_submissions($exporter)) {
            self::redirect_with_notice('submissions', 'error', __('No submissions match the selected options.', 'elzo-forms'));
        }

        self::start_download('elzo-forms-submissions-' . self::file_timestamp() . '.' . $format, $format === 'json' ? 'application/json' : 'text/csv');

        $handle = fopen('php://output', 'wb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streams the download to the response body.
        if ($format === 'json') {
            $exporter->write_json($handle);
        } else {
            $exporter->write_csv($handle);
        }
        fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the response body stream.
        exit;
    }

    /**
     * Read the filters of a submissions export request.
     *
     * The caller verifies the nonce first.
     *
     * @param string $format "csv" or "json".
     * @return array Filters for Submission_Exporter.
     */
    public static function submission_export_filters(string $format): array {
        $filters = [
            'include_metadata' => self::request_bool('include_metadata'),
            // A request without the option keeps the header row.
            'include_headers' => self::request_value('include_headers') === null || self::request_bool('include_headers'),
            'include_forms' => $format === 'json' && self::request_bool('include_forms'),
        ];

        if (self::request_bool('use_selection')) {
            // Selected submissions are exported as chosen: the form, status
            // and date filters do not narrow them. The IDs come from the
            // user's own stored selection, never from the request.
            return array_merge($filters, [
                'status' => Submission_Exporter::STATUS_ALL,
                'ids' => self::get_selection(),
            ]);
        }

        return array_merge($filters, [
            // "Select all" covers every form, including submissions whose form is gone.
            'form_ids' => self::request_bool('all_forms') ? [] : self::request_ids('form_ids'),
            'status' => self::request_key('status'),
            'date_from' => self::request_text('date_from'),
            'date_to' => self::request_text('date_to'),
        ]);
    }

    /**
     * Open the submissions export with one submission selected.
     *
     * @return void
     */
    public static function handle_export_submission(): void {
        $post_id = self::request_int('post');
        self::verify('elzo_forms_export_submission_' . $post_id, 'export_submissions');

        $post = $post_id > 0 ? get_post($post_id) : null;
        if (!$post instanceof \WP_Post || $post->post_type !== 'elzo_submission' || $post->post_status === 'trash') {
            self::redirect_with_notice('submissions', 'error', __('The submission to export was not found.', 'elzo-forms'));
        }

        self::store_selection([$post_id]);

        wp_safe_redirect(self::page_url('submissions', ['selection' => '1']) . '#' . self::EXPORT_ANCHOR);
        exit;
    }

    /**
     * Whether any submission matches an export.
     *
     * @param Submission_Exporter $exporter Configured exporter.
     * @return bool
     */
    private static function has_submissions(Submission_Exporter $exporter): bool {
        $args = $exporter->query_args(0);
        $args['posts_per_page'] = 1;
        $args['fields'] = 'ids';

        $query = new \WP_Query($args);

        return !empty($query->posts);
    }

    /**
     * Validate an uploaded file and keep it for the preview.
     *
     * @return void
     */
    public static function handle_upload(): void {
        $type = self::request_type();
        self::verify('elzo_forms_import_upload_' . $type, 'import_' . $type);

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Nonce verified above; the upload is validated field by field below.
        $file = isset($_FILES['import_file']) && is_array($_FILES['import_file']) ? $_FILES['import_file'] : null;
        $error = $file && isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if (!$file || $error === UPLOAD_ERR_NO_FILE) {
            self::redirect_with_notice($type, 'error', __('Choose a file to import.', 'elzo-forms'));
        }

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            self::redirect_with_notice($type, 'error', __('The file is larger than this site accepts.', 'elzo-forms'));
        }

        $tmp_name = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';
        $name = isset($file['name']) && is_string($file['name']) ? sanitize_file_name($file['name']) : '';
        $size = isset($file['size']) ? (int) $file['size'] : 0;

        if ($error !== UPLOAD_ERR_OK || $tmp_name === '' || !is_uploaded_file($tmp_name)) {
            self::redirect_with_notice($type, 'error', __('The file could not be uploaded.', 'elzo-forms'));
        }

        if (strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'json') {
            self::redirect_with_notice($type, 'error', __('Only Elzo Forms JSON files can be imported.', 'elzo-forms'));
        }

        if ($size <= 0 || $size > (int) wp_max_upload_size()) {
            self::redirect_with_notice($type, 'error', __('The file is empty or larger than this site accepts.', 'elzo-forms'));
        }

        // Reading and decoding the file need the memory, so the limit is raised
        // and checked before the file is read.
        wp_raise_memory_limit('admin');
        $size_error = Package::check_size($size);
        if ($size_error) {
            self::redirect_with_notice($type, 'error', $size_error->get_error_message());
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the verified PHP upload temp file.
        $contents = file_get_contents($tmp_name);
        if (!is_string($contents)) {
            self::redirect_with_notice($type, 'error', __('The file could not be read.', 'elzo-forms'));
        }

        $package = Package::parse($contents);
        if (is_wp_error($package)) {
            self::redirect_with_notice($type, 'error', $package->get_error_message());
        }

        if ($type === Pending_Import::TYPE_FORMS && !$package->forms()) {
            self::redirect_with_notice($type, 'error', $package->submissions()
                ? __('This file contains no forms. Import its submissions on the Submissions tab.', 'elzo-forms')
                : __('This file contains no forms.', 'elzo-forms'));
        }

        if ($type === Pending_Import::TYPE_SUBMISSIONS && !$package->submissions()) {
            self::redirect_with_notice($type, 'error', $package->forms()
                ? __('This file contains no submissions. Import its forms on the Forms tab.', 'elzo-forms')
                : __('This file contains no submissions.', 'elzo-forms'));
        }

        // Only the file is kept; the decoded copy is released before it is encrypted.
        unset($package);
        $stored = Pending_Import::store($type, $contents, $name);
        unset($contents);
        if (is_wp_error($stored)) {
            self::redirect_with_notice($type, 'error', $stored->get_error_message());
        }

        wp_safe_redirect(self::page_url($type));
        exit;
    }

    /**
     * Import a previewed file.
     *
     * @return void
     */
    public static function handle_confirm(): void {
        $type = self::request_type();
        self::verify('elzo_forms_import_confirm_' . $type, 'import_' . $type);

        // Reading holds the stored and the decrypted file at once, so the
        // limit is raised first.
        wp_raise_memory_limit('admin');
        $contents = Pending_Import::read($type);
        $package = $contents !== null ? Package::parse($contents) : null;
        unset($contents);
        if (!$package instanceof Package) {
            Pending_Import::discard($type);
            self::redirect_with_notice($type, 'error', __('The uploaded file is no longer available. Upload it again.', 'elzo-forms'));
        }

        self::extend_time_limit();
        $decisions = self::request_choices('form_action');

        if ($type === Pending_Import::TYPE_FORMS) {
            $form_result = (new Form_Importer())->import($package, $decisions, Form_Importer::ACTION_COPY);
            Pending_Import::discard($type);
            self::store_notice(self::forms_notice($form_result));
        } else {
            $form_result = $package->forms()
                ? (new Form_Importer())->import($package, $decisions, Form_Importer::ACTION_SKIP)
                : null;
            $submission_result = (new Submission_Importer())->import($package, self::request_choices('mapping'), $form_result ? $form_result['form_map'] : []);
            Pending_Import::discard($type);
            self::store_notice(self::submissions_notice($submission_result, $form_result));
        }

        wp_safe_redirect(self::page_url($type));
        exit;
    }

    /**
     * Discard a previewed file.
     *
     * @return void
     */
    public static function handle_cancel(): void {
        $type = self::request_type();
        self::verify('elzo_forms_import_cancel_' . $type, 'import_' . $type);

        Pending_Import::discard($type);

        wp_safe_redirect(self::page_url($type));
        exit;
    }

    /**
     * Add an Export row action to forms.
     *
     * @param array $actions Row actions.
     * @param \WP_Post $post Post.
     * @return array
     */
    public static function add_form_row_action($actions, $post) {
        if (!is_array($actions) || !$post instanceof \WP_Post || $post->post_type !== 'elzo_form' || $post->post_status === 'trash' || !self::can('export_forms')) {
            return $actions;
        }

        $actions['elzo_forms_export'] = sprintf('<a href="%s">%s</a>', esc_url(self::form_export_url((int) $post->ID)), esc_html__('Export', 'elzo-forms'));

        return $actions;
    }

    /**
     * Add the Export bulk action to the forms list.
     *
     * @param array $actions Bulk actions.
     * @return array
     */
    public static function add_forms_bulk_action($actions) {
        if (is_array($actions) && !self::is_trash_view() && self::can('export_forms')) {
            $actions[self::BULK_ACTION] = __('Export', 'elzo-forms');
        }

        return $actions;
    }

    /**
     * Add the Export bulk action to the submissions list.
     *
     * @param array $actions Bulk actions.
     * @return array
     */
    public static function add_submissions_bulk_action($actions) {
        if (is_array($actions) && !self::is_trash_view() && self::can('export_submissions')) {
            $actions[self::BULK_ACTION] = __('Export', 'elzo-forms');
        }

        return $actions;
    }

    /**
     * Send selected forms to the forms export.
     *
     * WordPress has verified the list's bulk action nonce at this point.
     *
     * @param string $redirect_to Redirect URL.
     * @param string $action Bulk action.
     * @param array $post_ids Selected IDs.
     * @return string
     */
    public static function handle_forms_bulk_action($redirect_to, $action, $post_ids) {
        if ($action !== self::BULK_ACTION || !self::can('export_forms')) {
            return $redirect_to;
        }

        $form_ids = array_values(array_unique(array_filter(array_map('absint', (array) $post_ids))));
        if (!$form_ids) {
            return $redirect_to;
        }

        return add_query_arg([
            'action' => 'elzo_forms_export_forms',
            'form_ids' => implode(',', $form_ids),
            '_wpnonce' => wp_create_nonce('elzo_forms_export_forms'),
        ], admin_url('admin-post.php'));
    }

    /**
     * Keep selected submissions for the submissions export.
     *
     * WordPress has verified the list's bulk action nonce at this point.
     *
     * @param string $redirect_to Redirect URL.
     * @param string $action Bulk action.
     * @param array $post_ids Selected IDs.
     * @return string
     */
    public static function handle_submissions_bulk_action($redirect_to, $action, $post_ids) {
        if ($action !== self::BULK_ACTION || !self::can('export_submissions')) {
            return $redirect_to;
        }

        $ids = array_slice(array_values(array_unique(array_filter(array_map('absint', (array) $post_ids)))), 0, self::MAX_SELECTION);
        if (!$ids) {
            return $redirect_to;
        }

        self::store_selection($ids);

        return self::page_url('submissions', ['selection' => '1']);
    }

    /**
     * Keep the submissions the current user chose to export.
     *
     * @param int[] $ids Submission IDs.
     * @return void
     */
    private static function store_selection(array $ids): void {
        update_user_meta(get_current_user_id(), self::SELECTION_META, $ids);
    }

    /**
     * Get the submissions the current user selected in the submissions list.
     *
     * @return int[]
     */
    private static function get_selection(): array {
        $ids = get_user_meta(get_current_user_id(), self::SELECTION_META, true);

        return is_array($ids) ? array_slice(array_values(array_filter(array_map('absint', $ids))), 0, self::MAX_SELECTION) : [];
    }

    /**
     * Build the result notice of a forms import.
     *
     * @param array $result Form import result.
     * @return array
     */
    private static function forms_notice(array $result): array {
        $parts = self::form_count_parts($result);
        $details = self::form_details($result);

        return [
            'type' => $result['failed'] > 0 ? ($result['created'] + $result['replaced'] > 0 ? 'warning' : 'error') : ($details ? 'warning' : 'success'),
            'message' => $parts
                /* translators: %s: Comma-separated import counts, such as "2 forms created, 1 form skipped". */
                ? sprintf(__('Import finished: %s.', 'elzo-forms'), implode(', ', $parts))
                : __('Import finished. Nothing was imported.', 'elzo-forms'),
            'details' => $details,
        ];
    }

    /**
     * Build the result notice of a submissions import.
     *
     * @param array $result Submission import result.
     * @param array|null $form_result Result of the forms imported from the same file.
     * @return array
     */
    private static function submissions_notice(array $result, ?array $form_result): array {
        $parts = [];

        if ($result['imported'] > 0) {
            /* translators: %d: Number of submissions. */
            $parts[] = sprintf(_n('%d submission imported', '%d submissions imported', $result['imported'], 'elzo-forms'), $result['imported']);
        }
        if ($result['spam'] > 0) {
            /* translators: %d: Number of submissions. */
            $parts[] = sprintf(_n('%d of them as spam', '%d of them as spam', $result['spam'], 'elzo-forms'), $result['spam']);
        }
        if ($result['duplicates'] > 0) {
            /* translators: %d: Number of submissions. */
            $parts[] = sprintf(_n('%d already imported before', '%d already imported before', $result['duplicates'], 'elzo-forms'), $result['duplicates']);
        }
        if ($result['skipped'] > 0) {
            /* translators: %d: Number of submissions. */
            $parts[] = sprintf(_n('%d skipped', '%d skipped', $result['skipped'], 'elzo-forms'), $result['skipped']);
        }
        if ($result['failed'] > 0) {
            /* translators: %d: Number of submissions. */
            $parts[] = sprintf(_n('%d could not be imported', '%d could not be imported', $result['failed'], 'elzo-forms'), $result['failed']);
        }

        $details = [];
        if ($form_result) {
            $form_parts = self::form_count_parts($form_result);
            if ($form_parts) {
                /* translators: %s: Comma-separated form import counts. */
                $details[] = sprintf(__('Forms: %s.', 'elzo-forms'), implode(', ', $form_parts));
            }
            $details = array_merge($details, self::form_details($form_result));
        }

        $failed = $result['failed'] > 0 || ($form_result && $form_result['failed'] > 0);

        return [
            'type' => $failed ? ($result['imported'] > 0 ? 'warning' : 'error') : 'success',
            'message' => $parts
                /* translators: %s: Comma-separated import counts, such as "2 forms created, 1 form skipped". */
                ? sprintf(__('Import finished: %s.', 'elzo-forms'), implode(', ', $parts))
                : __('Import finished. Nothing was imported.', 'elzo-forms'),
            'details' => array_slice($details, 0, self::MAX_NOTICE_DETAILS),
        ];
    }

    /**
     * Summarize form import counts.
     *
     * @param array $result Form import result.
     * @return string[]
     */
    private static function form_count_parts(array $result): array {
        $parts = [];

        if ($result['created'] > 0) {
            /* translators: %d: Number of forms. */
            $parts[] = sprintf(_n('%d form created', '%d forms created', $result['created'], 'elzo-forms'), $result['created']);
        }
        if ($result['replaced'] > 0) {
            /* translators: %d: Number of forms. */
            $parts[] = sprintf(_n('%d form replaced', '%d forms replaced', $result['replaced'], 'elzo-forms'), $result['replaced']);
        }
        if ($result['skipped'] > 0) {
            /* translators: %d: Number of forms. */
            $parts[] = sprintf(_n('%d form skipped', '%d forms skipped', $result['skipped'], 'elzo-forms'), $result['skipped']);
        }
        if ($result['failed'] > 0) {
            /* translators: %d: Number of forms. */
            $parts[] = sprintf(_n('%d form not imported', '%d forms not imported', $result['failed'], 'elzo-forms'), $result['failed']);
        }

        return $parts;
    }

    /**
     * List the messages of imported forms.
     *
     * @param array $result Form import result.
     * @return string[]
     */
    private static function form_details(array $result): array {
        $details = [];

        foreach ($result['forms'] as $form) {
            foreach ($form['messages'] as $message) {
                /* translators: 1: Form title. 2: Message. */
                $details[] = sprintf(__('%1$s: %2$s', 'elzo-forms'), $form['title'], $message);
            }
        }

        return array_slice($details, 0, self::MAX_NOTICE_DETAILS);
    }

    /**
     * Keep a result notice for the current user's next page view.
     *
     * @param array $notice Notice: type, message and details.
     * @return void
     */
    private static function store_notice(array $notice): void {
        set_transient(self::NOTICE_TRANSIENT_PREFIX . get_current_user_id(), $notice, 5 * MINUTE_IN_SECONDS);
    }

    /**
     * Take the current user's pending result notice.
     *
     * @return array|null
     */
    private static function pull_notice(): ?array {
        $key = self::NOTICE_TRANSIENT_PREFIX . get_current_user_id();
        $notice = get_transient($key);
        if (!is_array($notice) || empty($notice['message'])) {
            return null;
        }

        delete_transient($key);

        return [
            'type' => in_array($notice['type'] ?? '', ['success', 'warning', 'error'], true) ? $notice['type'] : 'info',
            'message' => (string) $notice['message'],
            'details' => array_map('strval', isset($notice['details']) && is_array($notice['details']) ? $notice['details'] : []),
        ];
    }

    /**
     * Redirect back to a tab with a notice.
     *
     * @param string $tab Tab.
     * @param string $type Notice type.
     * @param string $message Message.
     * @return void
     */
    private static function redirect_with_notice(string $tab, string $type, string $message): void {
        self::store_notice([
            'type' => $type,
            'message' => $message,
            'details' => [],
        ]);

        wp_safe_redirect(self::page_url($tab));
        exit;
    }

    /**
     * Stop a request that lacks the capability or a valid nonce.
     *
     * @param string $nonce_action Nonce action.
     * @param string $operation Operation, see capability().
     * @return void
     */
    private static function verify(string $nonce_action, string $operation): void {
        check_admin_referer($nonce_action);

        if (!self::can($operation)) {
            wp_die(esc_html__('You do not have permission to do this.', 'elzo-forms'), '', ['response' => 403]);
        }
    }

    /**
     * Prepare the response for a file download.
     *
     * @param string $filename File name.
     * @param string $content_type MIME type.
     * @return void
     */
    private static function start_download(string $filename, string $content_type): void {
        self::extend_time_limit();

        // Buffered output would hold the whole file in memory.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        nocache_headers();
        header('Content-Type: ' . $content_type . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Get the date and time part of an export file name.
     *
     * The time keeps files exported on the same day apart.
     *
     * @return string Site-local time as Y-m-d-H-i-s.
     */
    private static function file_timestamp(): string {
        return (string) wp_date('Y-m-d-H-i-s');
    }

    /**
     * Allow a long export or import to finish.
     *
     * @return void
     */
    private static function extend_time_limit(): void {
        if (function_exists('set_time_limit')) {
            set_time_limit(0); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_set_time_limit -- Large exports and imports run in one request.
        }
    }

    /**
     * Whether the current list shows the trash.
     *
     * @return bool
     */
    private static function is_trash_view(): bool {
        return self::query_key('post_status') === 'trash';
    }

    /**
     * Get the pending import type of a request.
     *
     * @return string
     */
    private static function request_type(): string {
        $type = self::request_key('type');
        if (!in_array($type, [Pending_Import::TYPE_FORMS, Pending_Import::TYPE_SUBMISSIONS], true)) {
            wp_die(esc_html__('Invalid request.', 'elzo-forms'), '', ['response' => 400]);
        }

        return $type;
    }

    /**
     * Read a raw request value.
     *
     * Every caller verifies the nonce first and sanitizes the value through
     * one of the typed readers below.
     *
     * @param string $key Request key.
     * @return mixed
     */
    private static function request_value(string $key) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Callers verify the nonce; typed readers sanitize the value.
        if (isset($_POST[$key])) {
            return wp_unslash($_POST[$key]);
        }

        if (isset($_GET[$key])) {
            return wp_unslash($_GET[$key]);
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        return null;
    }

    /**
     * @param string $key Request key.
     * @return string
     */
    private static function request_key(string $key): string {
        $value = self::request_value($key);

        return is_scalar($value) ? sanitize_key((string) $value) : '';
    }

    /**
     * @param string $key Request key.
     * @return string
     */
    private static function request_text(string $key): string {
        $value = self::request_value($key);

        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }

    /**
     * @param string $key Request key.
     * @return int
     */
    private static function request_int(string $key): int {
        $value = self::request_value($key);

        return is_scalar($value) ? absint($value) : 0;
    }

    /**
     * @param string $key Request key.
     * @return bool
     */
    private static function request_bool(string $key): bool {
        $value = self::request_value($key);

        return is_scalar($value) && (string) $value !== '' && (string) $value !== '0';
    }

    /**
     * Read a list of IDs given as an array or a comma-separated string.
     *
     * @param string $key Request key.
     * @return int[]
     */
    private static function request_ids(string $key): array {
        $value = self::request_value($key);
        if (is_scalar($value)) {
            $value = explode(',', (string) $value);
        }

        return is_array($value) ? array_values(array_unique(array_filter(array_map('absint', array_filter($value, 'is_scalar'))))) : [];
    }

    /**
     * Read a list of machine names.
     *
     * @param string $key Request key.
     * @return string[]
     */
    private static function request_list(string $key): array {
        $value = self::request_value($key);

        return is_array($value) ? array_values(array_filter(array_map(static function ($item): string {
            return is_scalar($item) ? sanitize_key((string) $item) : '';
        }, $value))) : [];
    }

    /**
     * Read choices keyed by row index.
     *
     * @param string $key Request key.
     * @return array<int, string>
     */
    private static function request_choices(string $key): array {
        $value = self::request_value($key);
        $choices = [];

        foreach (is_array($value) ? $value : [] as $index => $choice) {
            if (is_scalar($choice) && (is_int($index) || ctype_digit((string) $index))) {
                $choices[(int) $index] = sanitize_key((string) $choice);
            }
        }

        return $choices;
    }

    /**
     * Read a sanitized query argument of the current screen.
     *
     * @param string $key Query key.
     * @return string
     */
    private static function query_key(string $key): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
        return isset($_GET[$key]) && is_scalar($_GET[$key]) ? sanitize_key(wp_unslash((string) $_GET[$key])) : '';
    }
}
