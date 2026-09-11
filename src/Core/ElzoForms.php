<?php
/**
 * Primary bootstrap class for Elzo Forms.
 *
 * Responsibilities (initial phase):
 * - Define constants
 * - Provide singleton access
 * - Prepare autoloading and deferred service boot methods
 *
 * Will later own: settings registry, service container, form/submission managers.
 */

namespace ElzoForms\Core;

// Exit if accessed directly
defined('ABSPATH') || exit;

class ElzoForms {
    /** @var array */
    protected $settings = [];

    /** @var ElzoForms */
    private static $instance;

    /**
     * Singleton accessor.
     */
    public static function instance(): ElzoForms {
        if (!self::$instance) {
            self::$instance = new self();
            self::$instance->initialize();
        }
        return self::$instance;
    }

    /**
     * Define core constants (idempotent).
     */
    protected function define_constants(): void {
        $this->define('FILE', dirname(__DIR__, 2) . '/elzo-forms.php');
        $this->define('PATH', dirname(__DIR__, 2) . '/');
        $this->define('PRO_URL', 'https://elzoforms.com/pro');
    }

    /**
     * Initialize plugin bootstrap.
     */
    protected function initialize(): void {
        $this->define_constants();

        $this->settings = [
            'version' => ELZO_FORMS_VERSION,
            'path'    => ELZO_FORMS_PATH,
        ];

        // Initialize template loader
        \ElzoForms\Utilities\Template_Loader::init();

        // Register core hooks.
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     */
    protected function register_hooks(): void {
        add_action('init', [$this, 'register_post_types'], 5);
        add_action('init', [$this, 'init_plugin']);
        add_action('wp_ajax_elzo_forms_submit', [$this, 'handle_form_submission']);
        add_action('wp_ajax_nopriv_elzo_forms_submit', [$this, 'handle_form_submission']);
        add_shortcode('elzo_form', [$this, 'render_form_shortcode']);
        \ElzoForms\Blocks\Form_Block::init();

        // Register AJAX handlers.
        \ElzoForms\Admin\Ajax_Handler::init();
        \ElzoForms\Upload\File_Upload_Handler::init();
        \ElzoForms\Upload\Submission_File_Cleanup::init();

        // Initialize JSON storage.
        add_action('init', [\ElzoForms\Form\Form_JSON_Storage::class, 'init'], 10);
    }

    /**
     * Register custom post types.
     */
    public function register_post_types(): void {
        // Include and execute CPT registration.
        include_once ELZO_FORMS_PATH . 'includes/post-types.php';
    }

    /**
     * Initialize plugin components on 'init' hook.
     */
    public function init_plugin(): void {
        // Load admin or frontend files conditionally.
        if (is_admin()) {
            $this->include_admin();
        } else {
            $this->include_frontend();
        }
    }

    /**
     * Include admin files.
     */
    protected function include_admin(): void {
        include_once ELZO_FORMS_PATH . 'admin/admin.php';
    }

    /**
     * Include frontend files.
     */
    protected function include_frontend(): void {
        include_once ELZO_FORMS_PATH . 'includes/front-end.php';
    }

    /**
     * Handle form submission via AJAX.
     */
    public function handle_form_submission(): void {
        $nonce = isset($_POST['elzo_forms_nonce']) && is_scalar($_POST['elzo_forms_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['elzo_forms_nonce']))
            : '';

        if (!$nonce || !wp_verify_nonce($nonce, 'elzo_forms_action')) {
            if (wp_doing_ajax()) {
                wp_send_json_error([
                    'message' => __('Security check failed', 'elzo-forms'),
                ], 403);
            }
            return;
        }

        if (!isset($_POST['elzo_form_id'])) {
            return;
        }

        // Include form handling file.
        include_once ELZO_FORMS_PATH . 'includes/form-handling.php';

        // Redirect after processing if not AJAX.
        if (!wp_doing_ajax()) {
            $redirect_url = '';

            if (!empty($elzo_forms_submission_response['success']) && !empty($elzo_forms_submission_response['data']['redirect_url'])) {
                $redirect_url = esc_url_raw($elzo_forms_submission_response['data']['redirect_url']);
            }

            if (!$redirect_url) {
                $redirect_url = wp_get_referer();
            }

            if (!$redirect_url) {
                $redirect_url = home_url('/');
            }

            // Keep status query arg only for fallback redirects to current page.
            if (empty($elzo_forms_submission_response['success']) || empty($elzo_forms_submission_response['data']['redirect_url'])) {
                $redirect_url = add_query_arg('status', 'success', $redirect_url);
            }

            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Render form shortcode.
     */
    public function render_form_shortcode($atts): string {
        $atts = shortcode_atts([
            'id' => 0,
            'echo' => false,
            'max_width' => '',
            'form_align' => '',
            'text_align' => '',
        ], $atts, 'elzo_form');

        return \ElzoForms\Form\Form::render_by_id($atts);
    }

    /**
     * Plugin activation callback.
     */
    public static function activate(): void {
        // Protect existing upload roots immediately; new files receive opaque
        // names and subdirectories when they are uploaded.
        \ElzoForms\Upload\File_Upload_Handler::initialize_upload_directories();
        \ElzoForms\Upload\File_Upload_Handler::schedule_cleanup();

        // Update and compile styles if function exists.
        if (function_exists('elzo_forms_update_and_compile_styles')) {
            elzo_forms_update_and_compile_styles();
        }
    }

    /**
     * Get a setting by key.
     */
    public function get_setting(string $key, $default = null) {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Define constant helper.
     */
    protected function define(string $name, $value): void {
        if (!defined('ELZO_FORMS_' . $name)) {
            define('ELZO_FORMS_' . $name, $value);
        }
    }
}
